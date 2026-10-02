"""One JSON snapshot per transaction, protected across threads and processes.

The separate lock inode is never replaced. All readers and writers take that
lock, reload the latest snapshot, and commit through fsync + atomic replace.
A failed transaction does not modify the previous snapshot. Local disks only.
"""
import copy
import json
import os
from pathlib import Path
import tempfile
import threading
import time
from contextlib import contextmanager

TABLES = ('users', 'sessions', 'items', 'contributions', 'contribution_items',
          'projects', 'kits', 'kit_lines', 'exchanges', 'ledger', 'holds',
          'messages', 'notifications', 'wants', 'uploads')
_MUTEX = threading.RLock()


def empty_state():
    return {'schema_version': 1, **{name: [] for name in TABLES},
            'settings': {}, 'rate_limits': {}}


def validate(data, previous=None):
    if not isinstance(data, dict) or data.get('schema_version') != 1:
        raise ValueError('Unsupported JSON store version; restore or migrate the data.')
    for table in TABLES:
        if not isinstance(data.get(table), list):
            raise ValueError('Invalid JSON table: ' + table)
    if not isinstance(data.get('settings'), dict) or not isinstance(data.get('rate_limits'), dict):
        raise ValueError('Invalid JSON settings.')
    users = {u['id'] for u in data['users']}
    for table in TABLES:
        keys = [r['id'] for r in data[table] if 'id' in r]
        if len(keys) != len(set(keys)):
            raise ValueError('Duplicate IDs: ' + table)
    for field, table in [('email', 'users'), ('token', 'sessions'),
                         ('event_key', 'ledger'), ('reference', 'holds'), ('path', 'uploads')]:
        values = [r[field] for r in data[table]]
        if len(values) != len(set(values)):
            raise ValueError('Duplicate ' + field)
    balances = {uid: 0 for uid in users}
    held = {uid: 0 for uid in users}
    for row in data['ledger']:
        if row['user_id'] not in users or type(row['amount']) is not int:
            raise ValueError('Invalid credit ledger entry.')
        balances[row['user_id']] += row['amount']
    for row in data['holds']:
        if row['user_id'] not in users or type(row['amount']) is not int or row['amount'] < 0:
            raise ValueError('Invalid credit hold.')
        if row['status'] == 'active':
            held[row['user_id']] += row['amount']
    if any(balances[u] < held[u] for u in users):
        raise ValueError('Credit balances cannot be overdrawn.')
    for row in data['items']:
        if not (type(row['quantity']) is int and type(row['reserved']) is int
                and 0 <= row['reserved'] <= row['quantity'] and row['credits'] >= 0):
            raise ValueError('Invalid stock quantity.')
        if row['owner_id'] is not None and row['owner_id'] not in users:
            raise ValueError('Unknown item owner.')
    live = [r['project_id'] for r in data['kits']
            if r['status'] in ('pending', 'approved', 'ready', 'collected')]
    if len(live) != len(set(live)):
        raise ValueError('Only one active kit is allowed per project.')
    if previous is not None:
        ledger = previous['ledger']
        if data['ledger'][:len(ledger)] != ledger:
            raise ValueError('Existing credit ledger entries are immutable.')


class JsonStore:
    def __init__(self, path):
        self.path = Path(path).resolve()
        self.lock_path = self.path.with_suffix(self.path.suffix + '.lock')

    @contextmanager
    def transaction(self):
        self.path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
        with _MUTEX:
            fd = os.open(self.lock_path, os.O_RDWR | os.O_CREAT, 0o600)
            with os.fdopen(fd, 'r+b') as lock:
                if os.name == 'nt':
                    import msvcrt
                    if not lock.read(1):
                        lock.write(b'0')
                        lock.flush()
                    deadline = time.monotonic() + 30
                    while True:
                        try:
                            lock.seek(0)
                            msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
                            break
                        except OSError:
                            if time.monotonic() > deadline:
                                raise TimeoutError('JSON storage is busy.')
                            time.sleep(0.05)
                else:
                    import fcntl
                    fcntl.flock(lock.fileno(), fcntl.LOCK_EX)
                try:
                    # Never silently replace a corrupt file with an empty community.
                    original = json.loads(self.path.read_text('utf-8')) if self.path.exists() else empty_state()
                    validate(original)
                    state = copy.deepcopy(original)
                    yield state
                    validate(state, original)
                    if state != original or not self.path.exists():
                        self._write(state)
                finally:
                    if os.name == 'nt':
                        lock.seek(0)
                        msvcrt.locking(lock.fileno(), msvcrt.LK_UNLCK, 1)
                    else:
                        fcntl.flock(lock.fileno(), fcntl.LOCK_UN)

    def _write(self, state):
        fd, name = tempfile.mkstemp(prefix=self.path.name + '.', suffix='.tmp', dir=self.path.parent)
        try:
            with os.fdopen(fd, 'w', encoding='utf-8') as handle:
                json.dump(state, handle, ensure_ascii=False, indent=2, allow_nan=False)
                handle.write('\n')
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(name, self.path)
            if os.name != 'nt':
                directory = os.open(self.path.parent, os.O_RDONLY)
                try:
                    os.fsync(directory)
                finally:
                    os.close(directory)
        finally:
            if os.path.exists(name):
                os.unlink(name)
