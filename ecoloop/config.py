"""Small .env reader. Existing host environment variables take precedence."""
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def load_env():
    path = ROOT / '.env'
    if not path.exists():
        return
    for line in path.read_text('utf-8').splitlines():
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        key, separator, value = line.partition('=')
        if not separator or not key.strip().replace('_', '').isalnum():
            raise ValueError('Invalid line in .env')
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in ('"', "'"):
            value = value[1:-1]
        os.environ.setdefault(key.strip(), value)


def data_path():
    return (ROOT / os.getenv('DATA_PATH', 'storage/ecoloop.json')).resolve()


def upload_path():
    return (ROOT / os.getenv('UPLOAD_DIR', 'storage/uploads')).resolve()
