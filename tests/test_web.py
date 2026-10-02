import base64
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from ecoloop.domain import EcoLoop
from ecoloop.store import JsonStore
from ecoloop.web import Application


class WebTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.store = JsonStore(Path(self.tmp.name) / 'data.json')
        self.app = Application(self.store, Path(self.tmp.name) / 'uploads', demo=False, production=False, site_url='http://localhost')
        self.cookie = ''
        self.csrf = ''

    def request(self, path, payload=None, method=None, **extra):
        raw = json.dumps(payload).encode() if payload is not None else b''
        env = {'PATH_INFO': path.split('?')[0], 'QUERY_STRING': path.partition('?')[2],
               'REQUEST_METHOD': method or ('POST' if payload is not None else 'GET'),
               'HTTP_HOST': 'localhost', 'HTTP_ORIGIN': 'http://localhost', 'REMOTE_ADDR': '127.0.0.1',
               'CONTENT_TYPE': 'application/json', 'CONTENT_LENGTH': str(len(raw)),
               'HTTP_COOKIE': self.cookie, 'HTTP_X_CSRF_TOKEN': self.csrf, 'wsgi.input': io.BytesIO(raw),
               'wsgi.url_scheme': 'http', **extra}
        response = {}
        def start(status, headers):
            response['status'] = int(status.split()[0])
            response['headers'] = dict(headers)
        content = b''.join(self.app(env, start))
        response['body'] = json.loads(content) if content and response['headers'].get('Content-Type', '').startswith('application/json') else content
        return response

    def register(self):
        response = self.request('/api/register', {'name': 'Test Member', 'email': 'test@example.org', 'password': 'correct-password-123'})
        self.assertEqual(response['status'], 200, response)
        self.cookie = response['headers']['Set-Cookie'].split(';')[0]
        self.csrf = response['body']['csrf']
        return response

    def test_register_persistence_csrf_logout_and_privacy(self):
        response = self.register()
        self.assertIn('HttpOnly', response['headers']['Set-Cookie'])
        self.assertNotIn('password', response['body']['user'])
        self.assertEqual(self.request('/api/state')['body']['user']['name'], 'Test Member')
        item = {'title': 'Reusable cardboard', 'description': 'A clean cardboard sheet.', 'material': 'cardboard_sheet', 'credits': 5}
        self.assertEqual(self.request('/api/items', item, HTTP_X_CSRF_TOKEN='wrong')['status'], 403)
        self.assertEqual(self.request('/api/items', item, HTTP_ORIGIN='https://attacker.example')['status'], 403)
        self.assertEqual(self.request('/api/items', item)['status'], 200)
        with self.store.transaction() as data:
            self.assertEqual(len(data['items']), 1)
            self.assertNotEqual(data['sessions'][0]['token'], self.cookie.split('=')[1])
            self.assertNotIn('correct-password-123', json.dumps(data))
        self.assertEqual(self.request('/api/logout', {})['status'], 200)
        self.assertIsNone(self.request('/api/state')['body']['user'])
        self.assertEqual(self.request('/api/demo-login', {'user_id': response['body']['user']['id']})['status'], 404)

    def test_login_errors_rate_limited_across_new_app_instances(self):
        self.register()
        for _ in range(11):
            response = self.request('/api/login', {'email': 'test@example.org', 'password': 'wrong'})
            self.assertEqual(response['status'], 401)
            self.app = Application(self.store, Path(self.tmp.name) / 'uploads', demo=False, production=False, site_url='http://localhost')
        self.assertEqual(self.request('/api/login', {'email': 'test@example.org', 'password': 'wrong'})['status'], 429)

    def test_files_and_malformed_input(self):
        self.assertEqual(self.request('/')['status'], 200)
        self.assertEqual(self.request('/app.js')['status'], 200)
        for path in ('/storage/data.json', '/.env', '/../ecoloop/domain.py', '/%2e%2e/.env', '/index.php', '/ecoloop/catalog.json'):
            self.assertEqual(self.request(path)['status'], 404, path)
        self.assertEqual(self.request('/api/register', [])['status'], 400)
        self.assertEqual(self.request('/api/register', {}, CONTENT_TYPE='text/plain')['status'], 415)
        self.assertEqual(self.request('/api/register', {}, CONTENT_LENGTH='3000001')['status'], 413)
        self.assertEqual(self.request('/api/register', {'name': {}, 'email': [], 'password': True})['status'], 400)
        head = self.request('/', method='HEAD')
        self.assertEqual(head['body'], b'')
        self.assertGreater(int(head['headers']['Content-Length']), 0)

    def test_health_reports_unavailable_storage(self):
        with self.assertLogs(level='ERROR'):
            with patch.object(self.store, 'transaction', side_effect=OSError('read only')):
                response = self.request('/health')
        self.assertEqual(response['status'], 503)
        self.assertIn('storage is unavailable', response['body']['error'])

    def test_photo_upload_and_owner_validation(self):
        self.register()
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6r9sAAAAASUVORK5CYII=')
        result = self.request('/api/uploads', {'data': 'data:image/png;base64,'+base64.b64encode(png).decode()})
        self.assertEqual(result['status'], 200)
        url = result['body']['url']
        self.assertEqual(self.request(url)['body'], png)
        item = {'title': 'Photo item', 'description': 'An item with a photograph.', 'credits': 3, 'image': url}
        self.assertEqual(self.request('/api/items', item)['status'], 200)
        self.assertEqual(self.request('/api/uploads', {'data': 'data:image/png;base64,'+base64.b64encode(b'<script>alert(1)</script>').decode()})['status'], 400)
        self.request('/api/logout', {})
        other = self.request('/api/register', {'name': 'Other Member', 'email': 'other@example.org', 'password': 'correct-password-123'})
        self.cookie = other['headers']['Set-Cookie'].split(';')[0]
        self.csrf = other['body']['csrf']
        self.assertEqual(self.request('/api/items', item)['status'], 400)

    def test_production_guards(self):
        with self.assertRaises(ValueError):
            Application(self.store, demo=True, production=True, site_url='https://example.org')
        with self.assertRaises(ValueError):
            Application(self.store, demo=False, production=True, site_url='')
        self.app = Application(self.store, demo=False, production=True, site_url='https://example.org')
        result = self.request('/api/register', {'name': 'Prod Member', 'email': 'prod@example.org', 'password': 'correct-password-123'}, HTTP_ORIGIN='https://example.org')
        self.assertIn('; Secure', result['headers']['Set-Cookie'])
        self.assertIn('Strict-Transport-Security', result['headers'])


if __name__ == '__main__':
    unittest.main()
