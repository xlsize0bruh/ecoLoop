"""Marketplace and project workflows. Call within a JsonStore transaction."""
import copy
from datetime import date as calendar_date, datetime, timezone
import hashlib
import hmac
import json
import math
import os
from pathlib import Path
import re
import secrets
import time
import uuid

CATALOG = json.loads(Path(__file__).with_name('catalog.json').read_text('utf-8'))
ACTIVE_KITS = ('pending', 'approved', 'ready')
ACTIVE_EXCHANGES = ('pending', 'accepted')


class AppError(Exception):
    def __init__(self, message, status=400):
        super().__init__(message)
        self.status = status


def fail(message, status=400):
    raise AppError(message, status)


def text(value, maximum=2000, minimum=0):
    if not isinstance(value, str):
        fail('Please fill in the required text fields.')
    value = value.strip()
    if not minimum <= len(value) <= maximum:
        fail(f'Text must be between {minimum} and {maximum} characters.')
    return value


def number(value, maximum=10000, minimum=0, integer=True):
    if isinstance(value, bool) or not isinstance(value, (str, int, float)):
        fail('Please enter a valid quantity or value.')
    try:
        n = float(value)
    except (ValueError, TypeError, OverflowError):
        fail('Please enter a valid quantity or value.')
    if not math.isfinite(n) or not minimum <= n <= maximum or (integer and not n.is_integer()):
        fail('Please enter a valid quantity or value.')
    return int(n) if integer else n


def pick(value, values, default=None):
    value = default if value is None else value
    if not isinstance(value, str) or value not in values:
        fail('Please choose a supported option.')
    return value


def date(value, default=''):
    value = value or default
    if not isinstance(value, str):
        fail('Please choose a valid date.')
    if value:
        try:
            if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', value):
                raise ValueError()
            calendar_date.fromisoformat(value)
        except ValueError:
            fail('Please choose a valid date.')
    return value


def hash_password(password):
    salt = secrets.token_hex(16)
    key = hashlib.scrypt(password.encode(), salt=salt.encode(), n=16384, r=8, p=1, dklen=64)
    return salt + '.' + key.hex()


def verify_password(password, encoded):
    try:
        salt, key = encoded.split('.')
        actual = hashlib.scrypt(password.encode(), salt=salt.encode(), n=16384, r=8, p=1, dklen=64)
        return hmac.compare_digest(actual, bytes.fromhex(key))
    except (ValueError, TypeError):
        return False


class EcoLoop:
    def __init__(self, data, now=None, community=None, collection=None):
        self.data = data
        self.now = now or (lambda: int(time.time() * 1000))
        self.community = community or os.getenv('COMMUNITY_NAME', 'Springdale Community')
        self.collection = collection or os.getenv('COLLECTION_POINT', 'School exchange desk · weekdays, 12–2 pm')

    def rows(self, table, **where):
        return [r for r in self.data[table] if all(r.get(k) == v for k, v in where.items())]

    def row(self, table, **where):
        return next(iter(self.rows(table, **where)), None)

    def required(self, table, row_id, label):
        r = self.row(table, id=row_id)
        if r is None:
            fail(label + ' not found.', 404)
        return r

    def add(self, table, **data):
        data.setdefault('id', str(uuid.uuid4()))
        data.setdefault('created_at', self.now())
        self.data[table].append(data)
        return data

    def serial(self, table, **data):
        data['id'] = max((r['id'] for r in self.data[table]), default=0) + 1
        return self.add(table, **data)

    def today(self):
        return datetime.fromtimestamp(self.now() / 1000, timezone.utc).date().isoformat()

    def user(self, user_id):
        u = self.row('users', id=user_id)
        if not u:
            fail('Sign in to continue.', 401)
        return {k: v for k, v in u.items() if k != 'password'}

    def organiser(self, user):
        if user['role'] != 'organiser':
            fail('This action is for community organisers.', 403)

    def notify(self, user_id, body, target='requests'):
        if user_id:
            self.serial('notifications', user_id=user_id, body=body, target=target, read_at=None)

    def notify_organisers(self, body, target='organiser'):
        for u in self.rows('users', role='organiser'):
            self.notify(u['id'], body, target)

    def create_user(self, data, role='member'):
        name = text(data.get('name'), 60, 2)
        email = text(data.get('email'), 180, 3).lower()
        if not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+', email):
            fail('Enter a valid email address.')
        password = text(data.get('password'), 200, 10)
        if self.row('users', email=email):
            fail('An account with this email already exists.', 409)
        u = self.add('users', name=name, email=email, password=hash_password(password),
                     role=pick(role, ('member', 'organiser')), community=self.community)
        return self.user(u['id'])

    def login(self, email, password):
        email, password = text(email, 180).lower(), text(password, 200)
        u = self.row('users', email=email)
        dummy = '0' * 32 + '.' + '0' * 128
        valid = verify_password(password, u['password'] if u else dummy)
        if not u or not valid:
            fail('Email or password is incorrect.', 401)
        return self.user(u['id'])

    def wallet(self, user_id):
        total = sum(r['amount'] for r in self.rows('ledger', user_id=user_id))
        held = sum(r['amount'] for r in self.rows('holds', user_id=user_id, status='active'))
        return {'total': total, 'held': held, 'available': total - held}

    def entry(self, user_id, amount, kind, ref, description, key):
        if self.row('ledger', event_key=key):
            fail('This credit transaction has already been recorded.', 409)
        self.serial('ledger', user_id=user_id, amount=amount, kind=kind, reference=ref,
                    description=description, event_key=key)

    def hold(self, user_id, ref, amount):
        if self.wallet(user_id)['available'] < amount:
            fail('You need more available credits. Contribute useful items to the exchange shelf first.', 409)
        if self.row('holds', reference=ref):
            fail('This credit reservation already exists.', 409)
        self.data['holds'].append(dict(reference=ref, user_id=user_id, amount=amount,
                                       status='active', created_at=self.now()))

    def end_hold(self, ref, status):
        hold = self.row('holds', reference=ref, status='active')
        if hold:
            hold['status'] = status

    def item(self, item_id):
        i = self.required('items', item_id, 'Item')
        return {**i, 'owner_name': self.user(i['owner_id'])['name'] if i['owner_id'] else 'Community shelf',
                'available': i['quantity'] - i['reserved']}

    def validate_image(self, user, value):
        image = text(value or '', 200)
        if image and not self.row('uploads', path=image, user_id=user['id']):
            fail('Choose a photo you uploaded.')
        return image

    def validate_item(self, data, user):
        if not isinstance(data, dict):
            fail('Please provide the item details.')
        return dict(title=text(data.get('title'), 100, 3), description=text(data.get('description'), 2500, 8),
                    category=pick(data.get('category'), CATALOG['categories'], 'Materials'),
                    material=pick(data.get('material'), CATALOG['materials'], 'other'),
                    condition=pick(data.get('condition'), CATALOG['conditions'], 'good'),
                    unit=pick(data.get('unit'), CATALOG['units'], 'piece'),
                    quantity=number(data.get('quantity', 1), 1000, 1),
                    credits=number(data.get('credits', 0), 1000),
                    mode=pick(data.get('mode'), ('credits', 'barter', 'gift'), 'credits'),
                    project_eligible=0 if data.get('project_eligible') is False else 1,
                    width=number(data.get('width') or 0, 10000, integer=False),
                    height=number(data.get('height') or 0, 10000, integer=False),
                    thickness=number(data.get('thickness') or 0, 1000, integer=False),
                    available_by=date(data.get('available_by'), self.today()),
                    image=self.validate_image(user, data.get('image')))

    def insert_item(self, data, owner_id, **extra):
        row = {**data, 'owner_id': owner_id, 'contributor_id': None, 'source_id': None,
               'source_project_id': None, 'reserved': 0, 'archived': 0, 'community': self.community, **extra}
        if row['mode'] != 'credits':
            row['credits'] = 0
        return self.item(self.add('items', **row)['id'])

    def create_item(self, user, data):
        item = self.validate_item(data, user)
        if item['mode'] == 'credits' and item['credits'] < 1:
            fail('Set a credit value of at least 1 per unit.')
        return self.insert_item(item, user['id'])

    def edit_item(self, user, item_id, data):
        current = self.required('items', item_id, 'Item')
        if current['owner_id'] != user['id']:
            fail('You can only edit your own listings.', 403)
        if current['reserved']:
            fail('This listing has an active request. Finish or cancel it before editing.', 409)
        if current['source_id']:
            fail('Relisted material keeps its original quantity and specifications. Archive it to remove it.', 409)
        value = self.validate_item(data, user)
        if value['mode'] == 'credits' and value['credits'] < 1:
            fail('Set a credit value of at least 1.')
        if value['mode'] != 'credits':
            value['credits'] = 0
        current.update(value)
        return self.item(item_id)

    def archive_item(self, user, item_id):
        item = self.required('items', item_id, 'Item')
        if item['owner_id'] != user['id']:
            fail('You can only archive your own listings.', 403)
        if item['reserved']:
            fail('Cancel the active request before archiving this item.', 409)
        item['archived'] = 1
        return {'ok': True}

    def reserve(self, item_id, quantity):
        i = self.required('items', item_id, 'Item')
        if i['archived'] or i['quantity'] - i['reserved'] < quantity:
            fail('An item is no longer available in the required quantity. Refresh the match.', 409)
        i['reserved'] += quantity

    def release(self, item_id, quantity):
        self.required('items', item_id, 'Item')['reserved'] -= quantity

    def consume(self, item_id, quantity):
        i = self.required('items', item_id, 'Item')
        i['quantity'] -= quantity
        i['reserved'] -= quantity

    def contribute(self, user, data):
        values = data.get('items')
        if not isinstance(values, list) or not 1 <= len(values) <= 10 or not all(isinstance(i, dict) for i in values):
            fail('Add between 1 and 10 contribution items.')
        items = [self.validate_item({**i, 'mode': 'credits'}, user) for i in values]
        if any(i['credits'] < 1 for i in items):
            fail('Each contribution needs a proposed credit value.')
        total = sum(i['quantity'] * i['credits'] for i in items)
        if total > 10000:
            fail('A contribution can total at most 10,000 credits.')
        c = self.add('contributions', user_id=user['id'], items=items, total=total,
                     status='pending', note='', reviewed_by=None, reviewed_at=None)
        self.notify_organisers(f"{user['name']} offered {len(items)} item(s) for the exchange shelf.")
        return {'id': c['id'], 'total': total, 'status': 'pending'}

    def review_contribution(self, user, contribution_id, data):
        self.organiser(user)
        c = self.required('contributions', contribution_id, 'Contribution')
        if c['user_id'] == user['id']:
            fail('Another organiser must review your contribution.', 403)
        if c['status'] != 'pending':
            fail('This contribution has already been reviewed.', 409)
        accept = pick(data.get('decision'), ('accept', 'decline')) == 'accept'
        note = text(data.get('note') or '', 1000)
        if accept:
            if data.get('physically_received') is not True:
                fail('Confirm the items have been received and checked first.')
            if self.reconcile()['paused']:
                fail('New credit issuance is paused by the organising team.', 409)
            if not self.reconcile()['balanced']:
                fail('Resolve the stock reconciliation before issuing more credits.', 409)
            for value in c['items']:
                i = self.insert_item(value, None, contributor_id=c['user_id'])
                self.data['contribution_items'].append(dict(contribution_id=c['id'], item_id=i['id'], initial_quantity=value['quantity']))
            self.entry(c['user_id'], c['total'], 'contribution', c['id'],
                       'Accepted contribution to the community shelf', 'contribution:' + c['id'])
        c.update(status='accepted' if accept else 'declined', note=note,
                 reviewed_by=user['id'], reviewed_at=self.now())
        self.notify(c['user_id'], f"Your contribution was accepted. {c['total']} credits are ready to use." if accept
                    else 'Your contribution needs a different arrangement. Check the organiser’s note.', 'wallet')
        return {'ok': True}

    def reverse_contribution(self, user, contribution_id):
        self.organiser(user)
        c = self.required('contributions', contribution_id, 'Contribution')
        if c['status'] != 'accepted':
            fail('This accepted contribution could not be found.', 404)
        for link in self.rows('contribution_items', contribution_id=c['id']):
            i = self.item(link['item_id'])
            if i['quantity'] != link['initial_quantity'] or i['reserved'] or i['archived']:
                fail('Some contributed goods have already been reserved or redeemed.', 409)
        if self.wallet(c['user_id'])['available'] < c['total']:
            fail('The contributor has already spent or committed these credits.', 409)
        for link in self.rows('contribution_items', contribution_id=c['id']):
            self.required('items', link['item_id'], 'Item').update(quantity=0, archived=1)
        self.entry(c['user_id'], -c['total'], 'reversal', c['id'],
                   'Contribution returned; matching credits retired', 'contribution-reversal:' + c['id'])
        c.update(status='reversed', reviewed_at=self.now())
        self.notify(c['user_id'], 'Your contribution was reversed and the matching credits retired. Collect your original items.', 'wallet')
        return {'ok': True}

    def reconcile(self):
        stock = sum(i['quantity'] * i['credits'] for i in self.rows('items', owner_id=None))
        outstanding = sum(e['amount'] for e in self.data['ledger'])
        return dict(stock=stock, outstanding=outstanding, difference=stock-outstanding,
                    balanced=stock == outstanding, paused=self.data['settings'].get('issuance_paused') == '1')

    def set_paused(self, user, paused):
        self.organiser(user)
        self.data['settings']['issuance_paused'] = '1' if paused else '0'
        return self.reconcile()

    def validate_project(self, data):
        reqs = data.get('requirements')
        if not isinstance(reqs, list) or not 1 <= len(reqs) <= 20 or not all(isinstance(r, dict) for r in reqs):
            fail('Add between 1 and 20 material requirements.')
        requirements = []
        for r in reqs:
            material = pick(r.get('material'), CATALOG['materials'], 'other')
            alternative = pick(r['alternative'], CATALOG['materials']) if r.get('alternative') else ''
            if alternative == material:
                alternative = ''
            requirements.append(dict(title=text(r.get('title'), 100, 2), material=material,
                quantity=number(r.get('quantity'), 1000, 1), unit=pick(r.get('unit'), CATALOG['units'], 'piece'),
                width=number(r.get('width') or 0, 10000, integer=False),
                height=number(r.get('height') or 0, 10000, integer=False),
                thickness=number(r.get('thickness') or 0, 1000, integer=False),
                condition=pick(r.get('condition'), CATALOG['conditions'], 'fair'),
                alternative=alternative, approve_alternative=r.get('approve_alternative') is True))
        return dict(name=text(data.get('name'), 100, 3), description=text(data.get('description'), 3000, 8),
                    requirements=requirements, already_have=text(data.get('already_have') or '', 1000),
                    needed_by=date(data.get('needed_by')))

    def create_project(self, user, data):
        return copy.deepcopy(self.add('projects', **self.validate_project(data), user_id=user['id'],
                         status='draft', completed_note='', completed_image='', used_materials=[],
                         shared=0, completed_at=None))

    def project(self, user, project_id):
        p = self.required('projects', project_id, 'Project')
        if p['user_id'] != user['id'] and user['role'] != 'organiser':
            fail('This project belongs to another member.', 403)
        return copy.deepcopy(p)

    def edit_project(self, user, project_id, data):
        p = self.project(user, project_id)
        if p['user_id'] != user['id']:
            fail('Only the maker can change this project.', 403)
        if p['status'] not in ('draft', 'cancelled'):
            fail('Cancel the active kit before changing requirements.', 409)
        self.required('projects', project_id, 'Project').update(**self.validate_project(data), status='draft')
        return self.project(user, project_id)

    def match(self, user, project_id):
        p = self.project(user, project_id)
        inventory = [self.item(i['id']) for i in self.data['items']
                     if not i['archived'] and i['quantity'] > i['reserved'] and i['mode'] in ('credits', 'gift')
                     and i['project_eligible'] and i['community'] == user['community'] and i['owner_id'] != p['user_id']]
        inventory.sort(key=lambda i: (i['credits'], i['created_at'], i['id']))
        allocated, results = {}, []
        for index, r in enumerate(p['requirements']):
            def suitable(i):
                return (i['unit'] == r['unit'] and all(i[k] >= r[k] for k in ('width', 'height', 'thickness'))
                        and CATALOG['ranks'][i['condition']] >= CATALOG['ranks'][r['condition']]
                        and (not p['needed_by'] or i['available_by'] <= p['needed_by']))
            exact = [i for i in inventory if i['material'] == r['material'] and suitable(i)
                     and (r['material'] != 'other' or i['title'].lower() == r['title'].lower())]
            alternatives = [i for i in inventory if r['alternative'] and i['material'] == r['alternative'] and suitable(i)]
            needed, lines = r['quantity'], []
            for i in exact + (alternatives if r['approve_alternative'] else []):
                quantity = min(needed, i['quantity'] - i['reserved'] - allocated.get(i['id'], 0))
                if quantity <= 0:
                    continue
                allocated[i['id']] = allocated.get(i['id'], 0) + quantity
                lines.append(dict(item_id=i['id'], title=i['title'], owner_id=i['owner_id'],
                                  owner_name=i['owner_name'], quantity=quantity, unit=i['unit'], cost=quantity*i['credits'],
                                  image=i['image'], alternative=i['material'] != r['material'], requirement_index=index))
                needed -= quantity
                if not needed:
                    break
            results.append({**r, 'index': index, 'lines': lines, 'missing': needed, 'matched': r['quantity']-needed,
                            'alternatives': [{k: i[k] for k in ('id', 'title', 'owner_name', 'available')} for i in alternatives]
                            if needed and not r['approve_alternative'] else []})
        lines = [l for r in results for l in r['lines']]
        fingerprint = hashlib.sha256(json.dumps({'lines': lines, 'requirements': p['requirements']},
                                                sort_keys=True, ensure_ascii=False).encode()).hexdigest()
        return dict(project=p, requirements=results, total=sum(l['cost'] for l in lines),
                    complete=all(not r['missing'] for r in results), fingerprint=fingerprint,
                    owners=len({l['owner_id'] or 'shelf' for l in lines}), wallet=self.wallet(user['id']))

    def request_kit(self, user, data):
        p = self.project(user, data.get('project_id'))
        if p['user_id'] != user['id'] or p['status'] not in ('draft', 'cancelled'):
            fail('This project already has an active or collected kit.', 409)
        match = self.match(user, p['id'])
        if not match['complete']:
            fail('Some required materials are missing. Keep this project as a draft.', 409)
        if match['fingerprint'] != data.get('fingerprint'):
            fail('Availability or value changed. Review the refreshed kit before requesting it.', 409)
        kit_id = str(uuid.uuid4())
        self.hold(user['id'], kit_id, match['total'])
        self.add('kits', id=kit_id, project_id=p['id'], user_id=user['id'], total=match['total'],
                 status='pending', expires_at=self.now()+48*3600000, collected_at=None)
        owners = set()
        for r in match['requirements']:
            for l in r['lines']:
                self.reserve(l['item_id'], l['quantity'])
                self.add('kit_lines', kit_id=kit_id, requirement_index=l['requirement_index'],
                         item_id=l['item_id'], supplier_id=l['owner_id'], quantity=l['quantity'], cost=l['cost'],
                         status='pending' if l['owner_id'] else 'received')
                owners.add(l['owner_id'])
        for owner_id in owners:
            self.notify(owner_id, f"{user['name']} requested materials for “{p['name']}”. Please review your contribution.")
        self.required('projects', p['id'], 'Project')['status'] = 'requested'
        self.refresh_kit(kit_id)
        return self.kit(user, kit_id)

    def kit(self, user, kit_id):
        k = self.required('kits', kit_id, 'Kit')
        p = self.required('projects', k['project_id'], 'Project')
        lines = []
        for l in self.rows('kit_lines', kit_id=kit_id):
            i = self.item(l['item_id'])
            lines.append({**l, **{f: i[f] for f in ('title', 'unit', 'image', 'material')}, 'supplier_name': i['owner_name']})
        if k['user_id'] != user['id'] and user['role'] != 'organiser' and not any(l['supplier_id'] == user['id'] for l in lines):
            fail('You are not a participant in this kit.', 403)
        return {**k, 'name': p['name'], 'description': p['description'], 'lines': lines,
                'maker_name': self.user(k['user_id'])['name']}

    def refresh_kit(self, kit_id):
        k = self.required('kits', kit_id, 'Kit')
        if k['status'] not in ACTIVE_KITS:
            return
        lines = self.rows('kit_lines', kit_id=kit_id)
        status = 'ready' if all(l['status'] == 'received' for l in lines) else (
            'approved' if all(l['status'] != 'pending' for l in lines) else 'pending')
        if status != k['status']:
            self.notify(k['user_id'], 'Your complete kit is ready to collect at the exchange desk.' if status == 'ready'
                        else 'All material owners approved your kit. The desk is arranging collection.', 'projects')
        k['status'] = status

    def approve_kit(self, user, kit_id):
        k = self.kit(user, kit_id)
        if not any(l['supplier_id'] == user['id'] for l in k['lines']):
            fail('Only the material supplier can approve this request.', 403)
        if k['status'] not in ('pending', 'approved'):
            fail('This kit is no longer awaiting approval.', 409)
        for l in self.rows('kit_lines', kit_id=kit_id, supplier_id=user['id'], status='pending'):
            l['status'] = 'accepted'
        self.refresh_kit(kit_id)
        return {'ok': True}

    def receive_kit_line(self, user, kit_id, line_id):
        self.organiser(user)
        k = self.kit(user, kit_id)
        if k['status'] not in ('pending', 'approved'):
            fail('This kit is not awaiting materials.', 409)
        line = self.row('kit_lines', kit_id=kit_id, id=line_id, status='accepted')
        if not line:
            fail('The owner must approve this material before it can be checked in.', 409)
        line['status'] = 'received'
        self.refresh_kit(kit_id)
        return {'ok': True}

    def cancel_kit_internal(self, k, status='cancelled'):
        if k['status'] not in ACTIVE_KITS:
            fail('This kit cannot be cancelled after collection.', 409)
        lines = self.rows('kit_lines', kit_id=k['id'])
        for l in lines:
            awaiting_return = l['status'] == 'received' and l['supplier_id']
            if not awaiting_return:
                self.release(l['item_id'], l['quantity'])
            l['status'] = 'return_due' if awaiting_return else 'cancelled'
        self.required('kits', k['id'], 'Kit')['status'] = status
        self.end_hold(k['id'], 'released')
        self.required('projects', k['project_id'], 'Project')['status'] = 'cancelled'
        for uid in {k['user_id']} | {l['supplier_id'] for l in lines}:
            self.notify(uid, 'A project kit was cancelled. Credits are released; delivered materials will be returned.')

    def cancel_kit(self, user, kit_id):
        self.cancel_kit_internal(self.kit(user, kit_id))
        return {'ok': True}

    def return_kit_line(self, user, kit_id, line_id):
        self.organiser(user)
        self.kit(user, kit_id)
        line = self.row('kit_lines', kit_id=kit_id, id=line_id, status='return_due')
        if not line:
            fail('This material is not awaiting return.', 409)
        self.release(line['item_id'], line['quantity'])
        line['status'] = 'returned'
        return {'ok': True}

    def collect_kit(self, user, kit_id):
        k = self.kit(user, kit_id)
        if k['user_id'] != user['id']:
            fail('Only the maker can confirm receiving this kit.', 403)
        if k['status'] == 'collected':
            return {'ok': True}
        if k['status'] != 'ready':
            fail('Wait until the entire kit has been checked in.', 409)
        hold = self.row('holds', reference=kit_id, status='active')
        if not hold or hold['amount'] != k['total']:
            fail('The credit hold needs to be reviewed by an organiser.', 409)
        self.entry(user['id'], -k['total'], 'kit', k['id'], 'Materials for ' + k['name'], 'kit-debit:' + k['id'])
        for l in k['lines']:
            self.consume(l['item_id'], l['quantity'])
            if l['supplier_id'] and l['cost']:
                self.entry(l['supplier_id'], l['cost'], 'supply', k['id'], 'Materials supplied for ' + k['name'], 'kit-line:' + l['id'])
            self.required('kit_lines', l['id'], 'Kit line')['status'] = 'collected'
            self.notify(l['supplier_id'], f"Your materials were collected. {l['cost']} credits received.", 'wallet')
        self.end_hold(kit_id, 'captured')
        self.required('kits', kit_id, 'Kit').update(status='collected', collected_at=self.now())
        self.required('projects', k['project_id'], 'Project')['status'] = 'building'
        return {'ok': True}

    def finish_project(self, user, project_id, data):
        p = self.project(user, project_id)
        if p['user_id'] != user['id'] or p['status'] != 'building':
            fail('Collect your kit before recording a finished project.', 409)
        k = self.row('kits', project_id=p['id'], status='collected')
        lines = self.rows('kit_lines', kit_id=k['id'])
        values = data.get('used', {})
        if not isinstance(values, dict):
            fail('Please enter the quantities used.')
        used = [dict(line_id=l['id'], item_id=l['item_id'], quantity=number(values.get(l['id'], l['quantity']), l['quantity'])) for l in lines]
        self.required('projects', project_id, 'Project').update(status='completed',
            completed_note=text(data.get('note'), 2000, 8), completed_image=self.validate_image(user, data.get('image')),
            used_materials=used, shared=1 if data.get('shared') is True else 0, completed_at=self.now())
        fields = ('title', 'description', 'category', 'material', 'condition', 'unit', 'credits', 'mode',
                  'project_eligible', 'width', 'height', 'thickness', 'available_by', 'image')
        for line, usage in zip(lines, used):
            remaining = line['quantity'] - usage['quantity']
            if remaining:
                i = self.item(line['item_id'])
                value = {f: i[f] for f in fields}
                value.update(title='Leftover · ' + i['title'], quantity=remaining)
                self.insert_item(value, user['id'], source_id=i['id'], source_project_id=p['id'])
        for w in self.rows('wants', project_id=p['id']):
            w['status'] = 'fulfilled'
        return {'ok': True}

    def relist_creation(self, user, project_id, data):
        p = self.project(user, project_id)
        if p['user_id'] != user['id'] or p['status'] != 'completed':
            fail('Finish your project before listing the creation.', 409)
        if self.row('items', source_project_id=p['id'], source_id=None):
            fail('This creation already has a listing.', 409)
        value = self.validate_item({**data, 'quantity': 1, 'category': 'Made by you',
            'title': data.get('title') or p['name'], 'description': data.get('description') or p['completed_note'],
            'image': data.get('image') or p['completed_image']}, user)
        if value['mode'] == 'credits' and not value['credits']:
            fail('Set a positive credit value.')
        return self.insert_item(value, user['id'], source_project_id=p['id'])

    def exchange(self, user, exchange_id):
        e = self.required('exchanges', exchange_id, 'Exchange')
        if e['buyer_id'] != user['id'] and e['seller_id'] != user['id'] and not (e['seller_id'] is None and user['role'] == 'organiser'):
            fail('You are not a participant in this exchange.', 403)
        return {**e, 'item': self.item(e['item_id']), 'offered': self.item(e['offered_id']) if e['offered_id'] else None,
                'buyer_name': self.user(e['buyer_id'])['name'],
                'seller_name': self.user(e['seller_id'])['name'] if e['seller_id'] else 'Community shelf'}

    def request_exchange(self, user, data):
        item = self.item(data.get('item_id'))
        quantity = number(data.get('quantity', 1), 1000, 1)
        if item['owner_id'] == user['id']:
            fail('You already own this item.')
        if item['community'] != user['community']:
            fail('This item belongs to another community.', 403)
        if item['available_by'] > self.today():
            fail('This item is not available for collection yet.', 409)
        exchange_id, total = str(uuid.uuid4()), quantity * item['credits']
        offered_id, offered_quantity = None, 0
        if item['mode'] == 'barter':
            offered = self.item(data.get('offered_id'))
            if offered['owner_id'] != user['id']:
                fail('You can only offer an item you own.', 403)
            if offered['available_by'] > self.today():
                fail('Your offered item is not available yet.', 409)
            offered_id = offered['id']
            offered_quantity = number(data.get('offered_quantity', 1), 1000, 1)
            self.reserve(offered_id, offered_quantity)
        self.reserve(item['id'], quantity)
        if item['mode'] == 'credits':
            self.hold(user['id'], exchange_id, total)
        self.add('exchanges', id=exchange_id, buyer_id=user['id'], seller_id=item['owner_id'],
                 item_id=item['id'], quantity=quantity, offered_id=offered_id, offered_quantity=offered_quantity,
                 mode=item['mode'], total=total, status='pending', buyer_confirmed=0, seller_confirmed=0,
                 expires_at=self.now()+48*3600000, completed_at=None)
        if item['owner_id']:
            self.notify(item['owner_id'], f"{user['name']} requested “{item['title']}”.")
        else:
            self.notify_organisers(f"{user['name']} requested an item from the exchange shelf.")
        return self.exchange(user, exchange_id)

    def accept_exchange(self, user, exchange_id):
        e = self.exchange(user, exchange_id)
        if e['seller_id'] != user['id'] and not (e['seller_id'] is None and user['role'] == 'organiser'):
            fail('Only the owner or shelf organiser can accept.', 403)
        # A member who is also an organiser cannot approve their own shelf redemption.
        if e['buyer_id'] == user['id']:
            fail('Another organiser must approve your shelf request.', 403)
        if e['status'] != 'pending':
            fail('This request is no longer pending.', 409)
        self.required('exchanges', exchange_id, 'Exchange')['status'] = 'accepted'
        self.notify(e['buyer_id'], f"Your request for “{e['item']['title']}” was accepted. Arrange a handover.")
        return {'ok': True}

    def cancel_exchange_internal(self, e, status='cancelled'):
        if e['status'] not in ACTIVE_EXCHANGES:
            fail('This exchange has already ended.', 409)
        if e['buyer_confirmed'] or e['seller_confirmed']:
            fail('Handover has started. Both participants need to finish confirmation.', 409)
        self.release(e['item_id'], e['quantity'])
        if e['offered_id']:
            self.release(e['offered_id'], e['offered_quantity'])
        self.required('exchanges', e['id'], 'Exchange')['status'] = status
        self.end_hold(e['id'], 'released')
        for uid in (e['buyer_id'], e['seller_id']):
            self.notify(uid, 'An exchange was cancelled. Its reserved credits and items are available again.')

    def cancel_exchange(self, user, exchange_id):
        self.cancel_exchange_internal(self.exchange(user, exchange_id))
        return {'ok': True}

    def confirm_exchange(self, user, exchange_id):
        e = self.exchange(user, exchange_id)
        if e['status'] == 'completed':
            return {'ok': True}
        if e['status'] != 'accepted':
            fail('The owner must accept this request first.', 409)
        row = self.required('exchanges', exchange_id, 'Exchange')
        row['buyer_confirmed' if user['id'] == e['buyer_id'] else 'seller_confirmed'] = 1
        if row['buyer_confirmed'] and row['seller_confirmed']:
            self.consume(e['item_id'], e['quantity'])
            if e['offered_id']:
                self.consume(e['offered_id'], e['offered_quantity'])
            if e['mode'] == 'credits':
                h = self.row('holds', reference=e['id'], status='active')
                if not h or h['amount'] != e['total']:
                    fail('This exchange needs an organiser’s review.', 409)
                self.entry(e['buyer_id'], -e['total'], 'exchange' if e['seller_id'] else 'redemption',
                           e['id'], e['item']['title'], 'exchange-debit:' + e['id'])
                if e['seller_id']:
                    self.entry(e['seller_id'], e['total'], 'exchange', e['id'], e['item']['title'], 'exchange-credit:' + e['id'])
                self.end_hold(e['id'], 'captured')
            row.update(status='completed', completed_at=self.now())
            for uid in (e['buyer_id'], e['seller_id']):
                self.notify(uid, 'Exchange complete: ' + e['item']['title'] + '.', 'wallet')
        return {'ok': True}

    def thread(self, user, kind, ref):
        if kind == 'kit':
            self.kit(user, ref)
        elif kind == 'exchange':
            self.exchange(user, ref)
        else:
            fail('Unknown conversation.')

    def get_messages(self, user, kind, ref):
        self.thread(user, kind, ref)
        return [{**m, 'name': self.user(m['user_id'])['name']} for m in self.rows('messages', kind=kind, reference=ref)]

    def send_message(self, user, kind, ref, data):
        self.thread(user, kind, ref)
        self.serial('messages', kind=kind, reference=ref, user_id=user['id'], body=text(data.get('body'), 1500, 1))
        return {'ok': True}

    def publish_want(self, user, project_id):
        p = self.project(user, project_id)
        if p['user_id'] != user['id']:
            fail('Only the maker can publish a wanted request.', 403)
        missing = [r for r in self.match(user, project_id)['requirements'] if r['missing']]
        if not missing:
            fail('Every requirement has a match. You can request your kit.')
        description = '\n'.join(f"{r['missing']} {r['unit']}: {r['title']}" +
            (f" (at least {r['width']} × {r['height']} cm)" if r['width'] or r['height'] else '') for r in missing)
        existing = self.row('wants', project_id=project_id, status='open')
        if existing:
            existing['description'] = description
        else:
            self.add('wants', user_id=user['id'], project_id=project_id, title=p['name'], description=description, status='open')
        return {'ok': True}

    def expire(self):
        now = self.now()
        for k in self.data['kits']:
            if k['status'] in ACTIVE_KITS and k['expires_at'] < now:
                self.cancel_kit_internal(k, 'expired')
        for e in self.data['exchanges']:
            if e['status'] in ACTIVE_EXCHANGES and not e['buyer_confirmed'] and not e['seller_confirmed'] and e['expires_at'] < now:
                self.cancel_exchange_internal(e, 'expired')
        self.data['sessions'][:] = [s for s in self.data['sessions'] if s['expires_at'] > now]

    def state(self, user):
        self.expire()
        recent = lambda rows, key='created_at': sorted(rows, key=lambda r: r[key], reverse=True)
        items = [self.item(i['id']) for i in self.rows('items', community=self.community) if not i['archived'] and i['quantity'] > 0]
        public_items = [{k: v for k, v in i.items() if k != 'contributor_id'} for i in recent(items)]
        showcases = [{**{f: p[f] for f in ('id', 'name', 'completed_note', 'completed_image', 'completed_at')},
                      'maker_name': self.user(p['user_id'])['name']} for p in recent(self.rows('projects', shared=1, status='completed'), 'completed_at')[:12]]
        impact = dict(exchanges=len(self.rows('exchanges', status='completed')), kits=len(self.rows('kits', status='collected')),
                      projects=len(self.rows('projects', status='completed')), members=len(self.data['users']),
                      shelf_units=sum(i['quantity'] for i in self.rows('items', owner_id=None)),
                      supplier_credits=sum(e['amount'] for e in self.rows('ledger', kind='supply')),
                      redeemed_credits=-sum(e['amount'] for e in self.rows('ledger', kind='redemption')))
        result = dict(user=user, community=self.community, collection=self.collection, items=public_items,
                      impact=impact, showcases=showcases, **{k: v for k, v in CATALOG.items() if k != 'ranks'},
                      wants=[{**w, 'maker_name': self.user(w['user_id'])['name']} for w in recent(self.rows('wants', status='open'))[:40]])
        if not user:
            return result
        uid, admin = user['id'], user['role'] == 'organiser'
        kits = [k for k in self.data['kits'] if admin or k['user_id'] == uid or self.row('kit_lines', kit_id=k['id'], supplier_id=uid)]
        exchanges = [e for e in self.data['exchanges'] if e['buyer_id'] == uid or e['seller_id'] == uid or (admin and e['seller_id'] is None)]
        contributions = [c for c in self.data['contributions'] if c['user_id'] == uid or admin]
        result.update(wallet=self.wallet(uid), ledger=recent(self.rows('ledger', user_id=uid), 'id')[:100],
            holds=self.rows('holds', user_id=uid, status='active'), projects=recent(self.rows('projects', user_id=uid)),
            kits=[self.kit(user, k['id']) for k in recent(kits)[:100]],
            exchanges=[self.exchange(user, e['id']) for e in recent(exchanges)[:100]],
            contributions=[{**c, 'contributor_name': self.user(c['user_id'])['name']} for c in recent(contributions)[:100]],
            notifications=recent(self.rows('notifications', user_id=uid), 'id')[:30])
        if admin:
            result['reconciliation'] = self.reconcile()
        return copy.deepcopy(result)
