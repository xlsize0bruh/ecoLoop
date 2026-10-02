"""Local administration; never exposed as a web endpoint."""
import argparse
from getpass import getpass
import os
from pathlib import Path
import shutil
from datetime import datetime, timezone
import zipfile

from ecoloop.config import ROOT, load_env, data_path, upload_path
from ecoloop.domain import EcoLoop, AppError
from ecoloop.seed import seed_demo
from ecoloop.store import JsonStore


def main():
    parser = argparse.ArgumentParser(description='EcoLoop community administration')
    parser.add_argument('command', choices=['setup', 'demo', 'backup', 'check'])
    parser.add_argument('--output', help='Backup path; defaults to a timestamp under storage/backups')
    args = parser.parse_args()
    load_env()
    if args.command == 'setup':
        # Collect input before taking the storage lock.
        name = input('Organiser name: ').strip()
        email = input('Organiser email: ').strip()
        password = getpass('Password (10+ characters): ')
        if password != getpass('Repeat password: '):
            parser.error('The passwords did not match.')
        with JsonStore(data_path()).transaction() as data:
            user = EcoLoop(data).create_user(dict(name=name, email=email, password=password), 'organiser')
        print('Organiser created: ' + user['email'])
    elif args.command == 'demo':
        if os.getenv('APP_ENV') == 'production':
            parser.error('Do not seed demo accounts in production.')
        with JsonStore(ROOT / 'storage/demo.json').transaction() as data:
            seed_demo(data)
        print('Demo ready. Python: python app.py --demo')
        print('PHP: set DEMO_MODE=1, DATA_PATH=storage/demo.json, UPLOAD_DIR=storage/demo-uploads in .env')
    elif args.command == 'backup':
        stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
        output = Path(args.output or ROOT / ('storage/backups/ecoloop-' + stamp + '.zip')).resolve()
        if output.is_relative_to(ROOT / 'public'):
            parser.error('Save backups outside public/.')
        output.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
        # Take the same lock as API requests so metadata and uploads agree.
        import json
        with JsonStore(data_path()).transaction() as data:
            fd = os.open(output, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
            with os.fdopen(fd, 'wb') as handle, zipfile.ZipFile(handle, 'w', zipfile.ZIP_DEFLATED) as archive:
                archive.writestr('ecoloop.json', json.dumps(data, ensure_ascii=False, indent=2)+'\n')
                for upload in data['uploads']:
                    filename = Path(upload['path']).name
                    archive.write(upload_path() / filename, 'uploads/' + filename)
        print('Backup saved: ' + str(output))
    else:
        with JsonStore(data_path()).transaction() as data:
            app = EcoLoop(data)
            result = app.reconcile()
            print(f"JSON valid. Members: {len(data['users'])}; shelf value: {result['stock']}; outstanding credits: {result['outstanding']}.")
            if not result['balanced']:
                raise SystemExit('Stock reconciliation needs organiser review.')


if __name__ == '__main__':
    try:
        main()
    except (AppError, ValueError, OSError) as error:
        raise SystemExit(str(error))
