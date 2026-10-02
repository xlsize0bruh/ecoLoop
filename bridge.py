"""Private CLI bridge for public/index.php; never expose this directory."""
import base64
import io
import json
import sys
from urllib.parse import unquote, urlsplit
from ecoloop.web import create_application, MAX_BODY


def main():
    request = json.loads(sys.stdin.buffer.read(4_100_000))
    body = base64.b64decode(request.get('body', ''), validate=True)
    if len(body) > MAX_BODY:
        raise ValueError('Request too large.')
    uri = urlsplit(request['uri'])
    env = {'REQUEST_METHOD': request['method'], 'PATH_INFO': unquote(uri.path), 'QUERY_STRING': uri.query,
           'CONTENT_LENGTH': str(len(body)), 'CONTENT_TYPE': request.get('content_type', ''),
           'REMOTE_ADDR': request.get('remote_addr', ''), 'SERVER_NAME': 'localhost', 'SERVER_PORT': '80',
           'SERVER_PROTOCOL': 'HTTP/1.1', 'wsgi.version': (1, 0), 'wsgi.url_scheme': request.get('scheme', 'http'),
           'wsgi.input': io.BytesIO(body), 'wsgi.errors': sys.stderr, 'wsgi.multithread': False,
           'wsgi.multiprocess': True, 'wsgi.run_once': True}
    # PHP supplies an explicit, fixed list. Never translate arbitrary keys to environment variables.
    for name in ('cookie', 'origin', 'host', 'x-csrf-token', 'sec-fetch-site'):
        env['HTTP_' + name.upper().replace('-', '_')] = request.get('headers', {}).get(name, '')
    response = {}
    def start_response(status, headers):
        response.update(status=int(status.split()[0]), headers=headers)
    chunks = create_application()(env, start_response)
    response['body'] = base64.b64encode(b''.join(chunks)).decode('ascii')
    sys.stdout.write(json.dumps(response))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        print('EcoLoop PHP bridge failed; check Python, .env and storage permissions.', file=sys.stderr)
        sys.exit(1)
