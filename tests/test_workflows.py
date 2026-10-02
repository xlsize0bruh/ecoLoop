import copy
import json
import multiprocessing
from pathlib import Path
import tempfile
import unittest

from ecoloop.domain import EcoLoop, AppError
from ecoloop.seed import seed_demo
from ecoloop.store import JsonStore


def race_for_item(path, user_id, item_id, queue):
    try:
        with JsonStore(path).transaction() as data:
            app = EcoLoop(data)
            app.request_exchange(app.user(user_id), {'item_id': item_id})
        queue.put('ok')
    except AppError as error:
        queue.put(error.status)


class Workflows(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.path = Path(self.tmp.name) / 'community.json'
        self.store = JsonStore(self.path)
        with self.store.transaction() as data:
            app = seed_demo(data)
            self.users = {u['email'].split('@')[0]: app.user(u['id']) for u in data['users']}
            self.project = data['projects'][0]['id']

    def call(self, method, member, *args):
        with self.store.transaction() as data:
            app = EcoLoop(data)
            app.expire()
            return copy.deepcopy(getattr(app, method)(self.users[member], *args))

    def credits(self, amount=60):
        c = self.call('contribute', 'maker', {'items': [dict(title='Useful calculator', description='A working calculator with a good display.',
                    material='stationery', category='Stationery', quantity=1, credits=amount)]})
        self.call('review_contribution', 'organiser', c['id'], {'decision': 'accept', 'physically_received': True})
        return c

    def kit(self):
        self.credits()
        match = self.call('match', 'maker', self.project)
        self.assertEqual(match['total'], 60)
        self.assertTrue(match['complete'])
        return self.call('request_kit', 'maker', {'project_id': self.project, 'fingerprint': match['fingerprint']})

    def ready(self):
        kit = self.kit()
        for owner in ('asha', 'kabir', 'mira'):
            self.call('approve_kit', owner, kit['id'])
        for line in kit['lines']:
            self.call('receive_kit_line', 'organiser', kit['id'], line['id'])
        return kit

    def test_kit_supplier_payouts_once_and_shelf_redemption(self):
        kit = self.ready()
        self.call('collect_kit', 'maker', kit['id'])
        self.call('collect_kit', 'maker', kit['id'])
        with self.store.transaction() as data:
            app = EcoLoop(data)
            self.assertEqual([app.wallet(self.users[k]['id'])['total'] for k in ('maker', 'asha', 'kabir', 'mira')], [0, 20, 15, 55])
            self.assertEqual(len(app.rows('ledger', reference=kit['id'], kind='supply')), 3)
            self.assertTrue(app.reconcile()['balanced'])
            shelf = next(i for i in data['items'] if i['owner_id'] is None and i['credits'] == 30)
        e = self.call('request_exchange', 'mira', {'item_id': shelf['id']})
        self.call('accept_exchange', 'organiser', e['id'])
        self.call('confirm_exchange', 'mira', e['id'])
        self.call('confirm_exchange', 'organiser', e['id'])
        self.call('confirm_exchange', 'organiser', e['id'])
        with self.store.transaction() as data:
            app = EcoLoop(data)
            self.assertEqual(app.wallet(self.users['mira']['id'])['total'], 25)
            self.assertTrue(app.reconcile()['balanced'])

    def test_cancel_after_delivery_retains_stock_until_return(self):
        kit = self.kit()
        line = next(l for l in kit['lines'] if l['supplier_id'] == self.users['asha']['id'])
        self.call('approve_kit', 'asha', kit['id'])
        self.call('receive_kit_line', 'organiser', kit['id'], line['id'])
        self.call('cancel_kit', 'maker', kit['id'])
        with self.store.transaction() as data:
            app = EcoLoop(data)
            self.assertEqual(app.wallet(self.users['maker']['id'])['held'], 0)
            self.assertEqual(app.item(line['item_id'])['reserved'], 1)
        self.call('return_kit_line', 'organiser', kit['id'], line['id'])
        with self.store.transaction() as data:
            self.assertTrue(all(i['reserved'] == 0 for i in data['items']))

    def test_expired_kit_releases_holds(self):
        kit = self.kit()
        with self.store.transaction() as data:
            EcoLoop(data, now=lambda: kit['expires_at']+1).expire()
        with self.store.transaction() as data:
            self.assertEqual(data['kits'][0]['status'], 'expired')
            self.assertTrue(all(i['reserved'] == 0 for i in data['items']))
            self.assertEqual(EcoLoop(data).wallet(self.users['maker']['id'])['available'], 60)

    def test_failed_exchange_rolls_back_stock_reservation(self):
        with self.store.transaction() as data:
            item = next(i for i in data['items'] if i['owner_id'] == self.users['asha']['id'] and i['mode'] == 'credits')
        with self.assertRaises(AppError):
            self.call('request_exchange', 'maker', {'item_id': item['id']})
        with self.store.transaction() as data:
            self.assertEqual(EcoLoop(data).item(item['id'])['reserved'], 0)
            self.assertEqual(data['exchanges'], [])

    def test_simultaneous_processes_cannot_reserve_last_unit_twice(self):
        with self.store.transaction() as data:
            item = next(i for i in data['items'] if i['mode'] == 'gift')
        context = multiprocessing.get_context('spawn')
        queue = context.Queue()
        processes = [context.Process(target=race_for_item, args=(str(self.path), self.users[k]['id'], item['id'], queue)) for k in ('maker', 'asha')]
        for process in processes:
            process.start()
        for process in processes:
            process.join(20)
            self.assertEqual(process.exitcode, 0)
        self.assertCountEqual([queue.get(timeout=2), queue.get(timeout=2)], ['ok', 409])
        with self.store.transaction() as data:
            self.assertEqual(EcoLoop(data).item(item['id'])['reserved'], 1)
            self.assertEqual(len(data['exchanges']), 1)

    def test_barter_reserves_and_consumes_both_sides(self):
        with self.store.transaction() as data:
            target = next(i for i in data['items'] if i['mode'] == 'barter')
            offered = next(i for i in data['items'] if i['owner_id'] == self.users['maker']['id'])
        e = self.call('request_exchange', 'maker', {'item_id': target['id'], 'offered_id': offered['id']})
        self.call('accept_exchange', 'asha', e['id'])
        self.call('confirm_exchange', 'maker', e['id'])
        with self.assertRaises(AppError):
            self.call('cancel_exchange', 'asha', e['id'])
        self.call('confirm_exchange', 'asha', e['id'])
        with self.store.transaction() as data:
            app = EcoLoop(data)
            self.assertEqual(app.item(target['id'])['quantity'], 0)
            self.assertEqual(app.item(offered['id'])['quantity'], 0)
            self.assertTrue(app.reconcile()['balanced'])

    def test_access_control_messages_and_organiser_reviews(self):
        with self.store.transaction() as data:
            gift = next(i for i in data['items'] if i['mode'] == 'gift')
        e = self.call('request_exchange', 'maker', {'item_id': gift['id']})
        self.call('send_message', 'maker', 'exchange', e['id'], {'body': 'When can we meet?'})
        self.assertEqual(len(self.call('get_messages', 'mira', 'exchange', e['id'])), 1)
        for method, args in [('get_messages', ('exchange', e['id'])), ('accept_exchange', (e['id'],)), ('project', (self.project,))]:
            with self.assertRaises(AppError) as error:
                self.call(method, 'kabir', *args)
            self.assertEqual(error.exception.status, 403)
        c = self.call('contribute', 'maker', {'items': [dict(title='Spare book', description='All pages are intact.', credits=5)]})
        with self.assertRaises(AppError):
            self.call('review_contribution', 'maker', c['id'], {'decision': 'accept', 'physically_received': True})
        with self.assertRaises(AppError):
            self.call('review_contribution', 'organiser', c['id'], {'decision': 'accept'})

    def test_finished_project_relists_only_unused_quantity(self):
        kit = self.ready()
        self.call('collect_kit', 'maker', kit['id'])
        usage = {l['id']: l['quantity'] for l in kit['lines']}
        usage[kit['lines'][0]['id']] = 0
        self.call('finish_project', 'maker', self.project, {'note': 'Built my desktop organiser!', 'used': usage, 'shared': True})
        with self.store.transaction() as data:
            app = EcoLoop(data)
            leftovers = [i for i in data['items'] if i['source_id']]
            self.assertEqual(len(leftovers), 1)
            self.assertEqual(leftovers[0]['quantity'], 1)
            self.assertEqual(len(app.state(None)['showcases']), 1)
        with self.assertRaises(AppError):
            self.call('finish_project', 'maker', self.project, {'note': 'Try completing twice.'})
        creation = self.call('relist_creation', 'maker', self.project, {'mode': 'gift', 'material': 'other'})
        self.assertEqual(creation['category'], 'Made by you')
        with self.assertRaises(AppError):
            self.call('relist_creation', 'maker', self.project, {'mode': 'gift'})

    def test_reverse_contribution_and_immutable_ledger(self):
        c = self.credits()
        self.call('reverse_contribution', 'organiser', c['id'])
        with self.store.transaction() as data:
            self.assertTrue(EcoLoop(data).reconcile()['balanced'])
            self.assertEqual(EcoLoop(data).wallet(self.users['maker']['id'])['total'], 0)
        before = self.path.read_bytes()
        with self.assertRaises(ValueError):
            with self.store.transaction() as data:
                data['ledger'][0]['amount'] += 100
        self.assertEqual(self.path.read_bytes(), before)

    def test_corrupt_data_is_never_silently_reset(self):
        self.path.write_text('{invalid json', encoding='utf-8')
        with self.assertRaises(json.JSONDecodeError):
            with self.store.transaction():
                pass
        self.assertEqual(self.path.read_text(), '{invalid json')

    def test_matching_does_not_double_allocate_shared_inventory(self):
        p = self.call('create_project', 'maker', {'name': 'Large organiser', 'description': 'Build with two cardboard sections.',
            'requirements': [dict(title='Cardboard', material='cardboard_sheet', quantity=2, unit='bundle')]*2})
        match = self.call('match', 'maker', p['id'])
        self.assertFalse(match['complete'])
        self.assertEqual(sum(r['matched'] for r in match['requirements']), 2)
        self.call('publish_want', 'maker', p['id'])

    def test_stale_match_and_negative_quantities_rejected(self):
        self.credits()
        m = self.call('match', 'maker', self.project)
        with self.store.transaction() as data:
            EcoLoop(data).required('items', m['requirements'][0]['lines'][0]['item_id'], 'Item')['credits'] += 1
        with self.assertRaises(AppError):
            self.call('request_kit', 'maker', {'project_id': self.project, 'fingerprint': m['fingerprint']})
        with self.assertRaises(AppError):
            self.call('request_exchange', 'maker', {'item_id': m['requirements'][0]['lines'][0]['item_id'], 'quantity': -1})


if __name__ == '__main__':
    unittest.main()
