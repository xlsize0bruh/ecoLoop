"""Real HTTP tests of both executable entry points; PHP test runs when installed."""
import base64
import http.cookiejar
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import unittest
from urllib.error import HTTPError, URLError
from urllib.request import build_opener, HTTPCookieProcessor, Request

ROOT = Path(__file__).resolve().parent.parent
PHP = os.getenv('TEST_PHP_BINARY') or shutil.which('php')


class GatewayTests(unittest.TestCase):
    def exercise(self, gateway):
        with tempfile.TemporaryDirectory() as tmp:
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            origin = f'http://127.0.0.1:{port}'
            env = {**os.environ, 'APP_ENV': 'development', 'DEMO_MODE': '0', 'SECURE_COOKIES': '0',
                   'SITE_URL': origin, 'DATA_PATH': str(Path(tmp)/'data.json'), 'UPLOAD_DIR': str(Path(tmp)/'uploads'),
                   'PYTHON_BINARY': sys.executable}
            command = [sys.executable, 'app.py', '--port', str(port)] if gateway == 'python' else [PHP, '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/index.php']
            with open(Path(tmp)/'server.log', 'w+') as log:
                process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=log, stderr=log)
                try:
                    client = build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
                    def request(path, data=None, csrf=''):
                        req = Request(origin+path, None if data is None else json.dumps(data).encode(),
                                      headers={'Content-Type': 'application/json', 'Origin': origin, 'X-CSRF-Token': csrf})
                        try:
                            response = client.open(req, timeout=10)
                        except HTTPError as error:
                            response = error
                        with response:
                            raw = response.read()
                            return response.status, response.headers, json.loads(raw) if response.headers.get_content_type() == 'application/json' else raw
                    for _ in range(100):
                        try:
                            if request('/health')[0] == 200:
                                break
                        except (URLError, ConnectionError):
                            pass
                        if process.poll() is not None:
                            log.seek(0)
                            self.fail(log.read())
                        time.sleep(0.05)
                    else:
                        self.fail('Server did not start')
                    status, headers, page = request('/')
                    self.assertEqual(status, 200)
                    self.assertIn(b'EcoLoop', page)
                    self.assertEqual(headers['X-Content-Type-Options'], 'nosniff')
                    self.assertEqual(request('/app.js')[0], 200)
                    self.assertEqual(request('/storage/data.json')[0], 404)
                    self.assertEqual(request('/.env')[0], 404)
                    status, _, account = request('/api/register', {'name': 'Gateway Tester', 'email': 'gateway@example.org', 'password': 'correct-password-123'})
                    self.assertEqual(status, 200, account)
                    csrf = account['csrf']
                    self.assertEqual(request('/api/state')[2]['user']['name'], 'Gateway Tester')
                    # Enough payload to exercise PHP's pipe loop beyond the OS pipe buffer.
                    png = b'\x89PNG\r\n\x1a\n' + b'0' * 100000
                    status, _, photo = request('/api/uploads', {'data': 'data:image/png;base64,'+base64.b64encode(png).decode()}, csrf)
                    self.assertEqual(status, 200, photo)
                    self.assertEqual(request(photo['url'])[2], png)
                    status, _, item = request('/api/items', {'title': 'A useful box', 'description': 'A clean box ready to reuse.', 'material': 'cardboard_sheet', 'credits': 5, 'image': photo['url']}, csrf)
                    self.assertEqual(status, 200, item)
                    self.assertEqual(request('/api/items/'+item['id']+'/archive', {}, csrf)[0], 200)
                    self.assertEqual(request('/api/logout', {}, csrf)[0], 200)
                    self.assertIsNone(request('/api/state')[2]['user'])
                    status, _, login = request('/api/login', {'email': 'gateway@example.org', 'password': 'correct-password-123'})
                    self.assertEqual(status, 200, login)
                    saved = json.loads((Path(tmp)/'data.json').read_text())
                    self.assertEqual(len(saved['users']), 1)
                    self.assertEqual(saved['items'][0]['archived'], 1)
                finally:
                    process.terminate()
                    try:
                        process.wait(timeout=10)
                    except subprocess.TimeoutExpired:
                        process.kill()
                        process.wait()

    def test_python_http_entry_point(self):
        self.exercise('python')

    @unittest.skipUnless(PHP, 'PHP is not installed; CI requires and tests PHP')
    def test_php_http_entry_point(self):
        self.exercise('php')


if __name__ == '__main__':
    unittest.main()
