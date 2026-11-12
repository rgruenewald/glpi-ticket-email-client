#!/usr/bin/env python3
"""Local development schema fingerprint, not a product version or migration ledger."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def fingerprint(root=ROOT):
    paths = [root / p for p in ('hook.php', 'inc/hook.class.php')]
    sql = sorted((root / 'sql').rglob('*.sql'))
    if not sql or not (root / 'sql/install.sql').is_file():
        raise ValueError('Missing schema SQL files')
    paths.extend(sql)
    digest = hashlib.sha256()
    for path in sorted(paths):
        if path.is_symlink() or not path.is_file():
            raise ValueError(f'Invalid schema input: {path}')
        digest.update(path.relative_to(root).as_posix().encode())
        digest.update(bytes([0]))
        digest.update(path.read_bytes())
        digest.update(bytes([0]))
    return digest.hexdigest()


def marker_path(instance):
    # Identity includes persistent DB volume creation and glpi_plugins creation.
    key = hashlib.sha256(instance.encode()).hexdigest()
    return Path.home() / '.local/state/ticketmailer-schema' / (key + '.json')


def migrations(root=ROOT):
    return {p.name: hashlib.sha256(p.read_bytes()).hexdigest()
            for p in sorted((root / "sql").glob("update-*.sql"))}


def record(path, value, migration_hashes=None):
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    os.chmod(path.parent, 0o700)
    fd, temp = tempfile.mkstemp(dir=path.parent)
    try:
        with os.fdopen(fd, 'w') as stream:
            json.dump({'fingerprint': value, 'migrations': migration_hashes or {}}, stream)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temp, path)
    finally:
        if os.path.exists(temp):
            os.unlink(temp)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=('fingerprint', 'read', 'record', 'validate'))
    parser.add_argument('--instance')
    parser.add_argument('--value')
    args = parser.parse_args()
    if args.command == 'fingerprint':
        print(fingerprint())
        return
    if not args.instance:
        parser.error('--instance is required')
    path = marker_path(args.instance)
    if args.command == 'validate':
        try:
            old = json.loads(path.read_text()).get('migrations', {})
        except (FileNotFoundError, ValueError):
            old = {}
        current = migrations()
        for name, value in old.items():
            if current.get(name) != value:
                raise SystemExit(f'Previously recorded migration changed or removed: {name}. Add a new guarded migration or restore a matching database; refusing to mark this schema applied.')
        return
    if args.command == 'read':
        try:
            value = json.loads(path.read_text())['fingerprint']
            if not isinstance(value, str) or len(value) != 64:
                raise ValueError('Invalid schema fingerprint')
            print(value)
        except (FileNotFoundError, ValueError, KeyError, TypeError):
            print('')  # Missing/invalid state must trigger backup and reapplication.
    else:
        if not args.value or len(args.value) != 64:
            parser.error('--value must be a SHA-256 fingerprint')
        record(path, args.value, migrations())


if __name__ == '__main__':
    main()
