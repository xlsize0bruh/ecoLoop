"""WSGI application used by Python servers and the optional PHP gateway."""
import base64
import hashlib
import hmac
import json
import logging
import os
from http import HTTPStatus
from http.cookies import SimpleCookie, CookieError
from pathlib import Path
import re
import secrets
from urllib.parse import parse_qs, unquote, urlsplit

from .config import ROOT, data_path, upload_path, load_env
from .domain import EcoLoop, AppError, fail
from .store import JsonStore

MAX_BODY = 3_000_000
SESSION_AGE = 7 * 24 * 3600 * 1000
STATIC = {'/': ('index.html', 'text/html; charset=utf-8'),
          '/index.html': ('index.html', 'text/html; charset=utf-8'),
          '/styles.css': ('styles.css', 'text/css; charset=utf-8'),
          '/print.css': ('print.css', 'text/css; charset=utf-8'),
          '/app.js': ('app.js', 'text/javascript; charset=utf-8'),
          '/art.js': ('art.js', 'text/javascript; charset=utf-8'),
          '/favicon.svg': ('favicon.svg', 'image/svg+xml')}
SECURITY_HEADERS = [
    ('X-Content-Type-Options', 'nosniff'), ('Referrer-Policy', 'same-origin'),
    ('X-Frame-Options', 'DENY'), ('Permissions-Policy', 'camera=(), microphone=(), geolocation=()'),
    ('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'")]


def json_bytes(value):
    return json.dumps(value, ensure_ascii=False, allow_nan=False).encode('utf-8')


class Application:
    def __init__(self, store=None, uploads=None, demo=None, production=None, site_url=None):
        self.store = store or JsonStore(data_path())
        self.uploads = Path(uploads or upload_path()).resolve()
        self.demo = os.getenv('DEMO_MODE') == '1' if demo is None else demo
        self.production = os.getenv('APP_ENV') == 'production' if production is None else production
        self.site_url = (site_url if site_url is not None else os.getenv('SITE_URL', '')).rstrip('/')
        if self.production and self.demo:
            raise ValueError('Demo mode must be disabled in production.')
        if self.production and not self.site_url.startswith('https://'):
            raise ValueError('Set SITE_URL to your public HTTPS origin in production.')
        if self.site_url:
            parsed = urlsplit(self.site_url)
            if parsed.scheme not in ('http', 'https') or not parsed.netloc or parsed.path or parsed.query or parsed.fragment or parsed.username:
                raise ValueError('SITE_URL must be an origin, such as https://ecoloop.example.')
        public = (ROOT / 'public').resolve()
        if self.store.path.is_relative_to(public) or self.uploads.is_relative_to(public):
            raise ValueError('Private storage must be outside public/.')

    def __call__(self, environ, start_response):
        extra = []
        try:
            status, headers, body = self.dispatch(environ)
        except AppError as error:
            status, headers, body = error.status, [], json_bytes({'error': str(error)})
        except FileNotFoundError:
            status, headers, body = 404, [], json_bytes({'error': 'Not found.'})
        except (OSError, ValueError, TimeoutError):
            logging.exception('EcoLoop storage unavailable')
            status, headers, body = 503, [], json_bytes({'error': 'Community storage is unavailable. Check the server storage configuration.'})
        except Exception:
            logging.exception('EcoLoop request failed')
            status, headers, body = 500, [], json_bytes({'error': 'Something went wrong. Please try again.'})
        if not any(k.lower() == 'content-type' for k, _ in headers):
            extra.extend([('Content-Type', 'application/json; charset=utf-8'), ('Cache-Control', 'no-store')])
        if self.production:
            extra.append(('Strict-Transport-Security', 'max-age=31536000'))
        start_response(f'{status} {HTTPStatus(status).phrase}',
                       SECURITY_HEADERS + headers + extra + [('Content-Length', str(len(body)))])
        return [b'' if environ.get('REQUEST_METHOD') == 'HEAD' else body]

    def current_session(self, app, env):
        try:
            cookie = SimpleCookie(env.get('HTTP_COOKIE', ''))
            token = cookie['ecoloop_session'].value if 'ecoloop_session' in cookie else ''
        except CookieError:
            return None
        if not re.fullmatch(r'[a-f0-9]{64}', token):
            return None
        session = app.row('sessions', token=hashlib.sha256(token.encode()).hexdigest())
        return session if session and session['expires_at'] > app.now() else None

    def new_session(self, app, user, session):
        if session:
            app.data['sessions'].remove(session)
        token, csrf = secrets.token_hex(32), secrets.token_hex(32)
        app.data['sessions'].append(dict(token=hashlib.sha256(token.encode()).hexdigest(),
                                         user_id=user['id'], csrf=csrf, expires_at=app.now()+SESSION_AGE))
        secure = '; Secure' if self.production or os.getenv('SECURE_COOKIES') == '1' else ''
        return csrf, ('Set-Cookie', f'ecoloop_session={token}; Path=/; HttpOnly; SameSite=Lax; Max-Age={SESSION_AGE//1000}{secure}')

    def check_origin(self, env):
        expected = self.site_url or env.get('wsgi.url_scheme', 'http') + '://' + env.get('HTTP_HOST', 'localhost')
        if env.get('HTTP_ORIGIN') and env['HTTP_ORIGIN'] != expected:
            fail('This request came from a different site.', 403)
        if env.get('HTTP_SEC_FETCH_SITE') == 'cross-site':
            fail('Cross-site requests are not allowed.', 403)

    def throttle(self, env, path):
        # Commit rate counters separately so rejected authentication attempts count.
        denied = False
        with self.store.transaction() as data:
            app = EcoLoop(data)
            now = app.now()
            data['rate_limits'] = {k: v for k, v in data['rate_limits'].items() if now-v['start'] < 60000}
            limits = [('write', 120)]
            if path in ('/api/login', '/api/register', '/api/demo-login'):
                limits.append(('auth', 12))
            if path == '/api/uploads':
                limits.append(('upload', 12))
            for kind, maximum in limits:
                # Ignore client-supplied X-Forwarded-For unless a trusted server sets REMOTE_ADDR.
                key = hashlib.sha256((env.get('REMOTE_ADDR', '') + ':' + kind).encode()).hexdigest()
                bucket = data['rate_limits'].setdefault(key, {'start': now, 'count': 0})
                bucket['count'] += 1
                denied = denied or bucket['count'] > maximum
        if denied:
            fail('Too many requests. Please wait a minute.', 429)

    def body(self, env):
        if env.get('CONTENT_TYPE', '').split(';')[0].strip() != 'application/json':
            fail('Requests must use JSON.', 415)
        try:
            size = int(env.get('CONTENT_LENGTH') or '0')
        except ValueError:
            fail('Invalid request size.')
        if size < 0 or size > MAX_BODY:
            fail('This upload is too large. Use a photo smaller than 2 MB.', 413)
        try:
            raw = env['wsgi.input'].read(size)
            if len(raw) != size:
                fail('Incomplete request body.')
            value = json.loads(raw or b'{}', parse_constant=lambda _: fail('Invalid number.'))
        except (ValueError, UnicodeError):
            fail('The request could not be read.')
        if not isinstance(value, dict):
            fail('Please send a JSON object.')
        return value

    def upload(self, app, user, data):
        value = data.get('data')
        if not isinstance(value, str) or len(value) > 2_800_000:
            fail('Use a JPEG, PNG, or WebP photo under 2 MB.')
        match = re.fullmatch(r'data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)', value)
        if not match:
            fail('Use a JPEG, PNG, or WebP photo.')
        try:
            raw = base64.b64decode(match[2], validate=True)
        except ValueError:
            fail('The photo could not be read.')
        if not 16 <= len(raw) <= 2*1024*1024:
            fail('Use a valid photo under 2 MB.')
        extension = 'jpg' if match[1] == 'jpeg' else match[1]
        valid = (raw.startswith(b'\x89PNG\r\n\x1a\n') if extension == 'png' else
                 raw.startswith(b'\xff\xd8\xff') if extension == 'jpg' else
                 raw[:4] == b'RIFF' and raw[8:12] == b'WEBP')
        if not valid:
            fail('The photo content does not match its file type.')
        self.uploads.mkdir(parents=True, exist_ok=True, mode=0o700)
        filename = secrets.token_hex(24) + '.' + extension
        path = self.uploads / filename
        with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), 'wb') as handle:
            handle.write(raw)
            handle.flush()
            os.fsync(handle.fileno())
        url = '/uploads/' + filename
        app.data['uploads'].append(dict(path=url, user_id=user['id'], created_at=app.now()))
        return {'url': url}

    def dispatch(self, env):
        path, method = env.get('PATH_INFO', '/'), env.get('REQUEST_METHOD', 'GET')
        if path == '/health' and method in ('GET', 'HEAD'):
            with self.store.transaction():
                pass
            return 200, [], json_bytes({'status': 'ok', 'storage': 'json'})
        if not path.startswith('/api/'):
            if method not in ('GET', 'HEAD'):
                fail('Method not allowed.', 405)
            if path in STATIC:
                filename, mime = STATIC[path]
                return 200, [('Content-Type', mime), ('Cache-Control', 'no-cache' if filename.endswith('.html') else 'public, max-age=300')], (ROOT / 'public' / filename).read_bytes()
            if re.fullmatch(r'/uploads/[a-f0-9]{48}\.(png|jpg|webp)', path):
                with self.store.transaction() as data:
                    if not any(u['path'] == path for u in data['uploads']):
                        fail('Not found.', 404)
                mime = {'.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp'}[Path(path).suffix]
                return 200, [('Content-Type', mime), ('Cache-Control', 'public, max-age=300')], (self.uploads / Path(path).name).read_bytes()
            fail('Not found.', 404)
        if method == 'POST':
            self.check_origin(env)
            self.throttle(env, path)
            data_in = self.body(env)
        elif method == 'GET':
            data_in = {}
        else:
            fail('This action could not be found.', 404)
        with self.store.transaction() as data:
            app = EcoLoop(data)
            app.expire()
            session = self.current_session(app, env)
            user = app.user(session['user_id']) if session else None
            if method == 'GET':
                if path == '/api/state':
                    result = {**app.state(user), 'csrf': session['csrf'] if session else None, 'demo': self.demo,
                              'demo_users': [{k: u[k] for k in ('id', 'name', 'email', 'role')} for u in data['users'][:8]] if self.demo else []}
                else:
                    if not user:
                        fail('Sign in to continue.', 401)
                    if re.fullmatch(r'/api/matches/[^/]+', path):
                        result = app.match(user, path.rsplit('/', 1)[1])
                    elif path == '/api/messages':
                        query = parse_qs(env.get('QUERY_STRING', ''))
                        result = {'messages': app.get_messages(user, query.get('kind', [''])[0], query.get('reference', [''])[0])}
                    else:
                        fail('This action could not be found.', 404)
                return 200, [], json_bytes(result)
            if path in ('/api/login', '/api/register', '/api/demo-login'):
                if path == '/api/login':
                    account = app.login(data_in.get('email', ''), data_in.get('password', ''))
                elif path == '/api/register':
                    account = app.create_user(data_in)
                else:
                    if not self.demo:
                        fail('Demo accounts are unavailable.', 404)
                    account = app.user(data_in.get('user_id'))
                csrf, cookie = self.new_session(app, account, session)
                return 200, [cookie], json_bytes({'user': account, 'csrf': csrf})
            if not user:
                fail('Sign in to continue.', 401)
            if not hmac.compare_digest(env.get('HTTP_X_CSRF_TOKEN', ''), session['csrf']):
                fail('Your session changed. Refresh the page and try again.', 403)
            headers = []
            if path == '/api/logout':
                data['sessions'].remove(session)
                secure = '; Secure' if self.production or os.getenv('SECURE_COOKIES') == '1' else ''
                headers.append(('Set-Cookie', 'ecoloop_session=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0' + secure))
                result = {'ok': True}
            elif path == '/api/uploads':
                result = self.upload(app, user, data_in)
            elif path == '/api/messages':
                result = app.send_message(user, data_in.get('kind'), data_in.get('reference'), data_in)
            elif path == '/api/notifications':
                for n in app.rows('notifications', user_id=user['id'], read_at=None):
                    n['read_at'] = app.now()
                result = {'ok': True}
            elif path == '/api/issuance':
                result = app.set_paused(user, data_in.get('paused') is True)
            else:
                parts = path[5:].split('/')
                creates = {'items': app.create_item, 'contributions': app.contribute,
                           'projects': app.create_project, 'kits': app.request_kit, 'exchanges': app.request_exchange}
                simple = {'items/archive': app.archive_item, 'contributions/reverse': app.reverse_contribution,
                          'projects/wanted': app.publish_want, 'kits/approve': app.approve_kit,
                          'kits/cancel': app.cancel_kit, 'kits/collect': app.collect_kit,
                          'exchanges/accept': app.accept_exchange, 'exchanges/cancel': app.cancel_exchange,
                          'exchanges/confirm': app.confirm_exchange}
                inputs = {'items/edit': app.edit_item, 'contributions/review': app.review_contribution,
                          'projects/edit': app.edit_project, 'projects/complete': app.finish_project,
                          'projects/relist': app.relist_creation}
                if len(parts) == 1 and parts[0] in creates:
                    result = creates[parts[0]](user, data_in)
                elif len(parts) == 3:
                    key = parts[0] + '/' + parts[2]
                    if key in simple:
                        result = simple[key](user, parts[1])
                    elif key in inputs:
                        result = inputs[key](user, parts[1], data_in)
                    elif key in ('kits/receive', 'kits/return'):
                        operation = app.receive_kit_line if key == 'kits/receive' else app.return_kit_line
                        result = operation(user, parts[1], data_in.get('line_id'))
                    else:
                        fail('This action could not be found.', 404)
                else:
                    fail('This action could not be found.', 404)
            return 200, headers, json_bytes(result)


def create_application():
    load_env()
    return Application()
