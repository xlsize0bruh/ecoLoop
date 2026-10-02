"""EcoLoop server. Start with: python app.py"""
import argparse
import os
from socketserver import ThreadingMixIn
from wsgiref.simple_server import WSGIServer, make_server
from ecoloop.config import load_env, ROOT, data_path, upload_path
from ecoloop.store import JsonStore
from ecoloop.web import create_application


class ThreadedServer(ThreadingMixIn, WSGIServer):
    daemon_threads = True


def main():
    parser = argparse.ArgumentParser(description='Run EcoLoop locally')
    parser.add_argument('--demo', action='store_true', help='Use isolated sample data and the demo account switcher')
    parser.add_argument('--host')
    parser.add_argument('--port', type=int)
    args = parser.parse_args()
    load_env()

    # Ensure storage directories exist (Render's filesystem is ephemeral).
    data_path().parent.mkdir(parents=True, exist_ok=True)
    upload_path().mkdir(parents=True, exist_ok=True)

    if args.demo:
        if os.getenv('APP_ENV') == 'production':
            parser.error('Demo mode is disabled in production.')
        os.environ['DEMO_MODE'] = '1'
        os.environ['DATA_PATH'] = str(ROOT / 'storage/demo.json')
        os.environ['UPLOAD_DIR'] = str(ROOT / 'storage/demo-uploads')
        from ecoloop.seed import seed_demo
        with JsonStore(os.environ['DATA_PATH']).transaction() as data:
            seed_demo(data)
    app = create_application()
    # Default to 0.0.0.0 so Render (and similar hosts) can reach the server.
    host = args.host or os.getenv('HOST', '0.0.0.0')
    port = args.port if args.port is not None else int(os.getenv('PORT', '3000'))
    with make_server(host, port, app, server_class=ThreadedServer) as server:
        print(f'EcoLoop is ready at http://{host}:{server.server_port}', flush=True)
        try:
            server.serve_forever()
        except KeyboardInterrupt:
            pass


if __name__ == '__main__':
    main()
