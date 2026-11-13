#!/usr/bin/env python3
"""Private local evidence, bound to the actual source snapshot, not just HEAD."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]

def command(*args):
    return subprocess.check_output(args, cwd=ROOT, text=True).strip()

def fingerprint():
    names = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT).split(b'\0')
    digest = hashlib.sha256()
    for raw in sorted(set(names) - {b''}):
        path = ROOT / raw.decode()
        digest.update(raw + b'\0')
        digest.update(str(path.lstat().st_mode & 0o777).encode() if path.exists() else b'missing')
        digest.update(b'\0')
        if path.is_symlink():
            raise ValueError('Symlink in source inventory')
        digest.update(hashlib.sha256(path.read_bytes()).digest() if path.is_file() else b'deleted')
    return digest.hexdigest()

def main():
    action, destination, value = sys.argv[1:]
    path = Path(destination) / 'summary.json'
    if action == 'start':
        report = {
            'status': 'running', 'mode': value,
            'revision': command('git', 'rev-parse', 'HEAD'),
            'source_sha256': fingerprint(),
            'dirty': bool(command('git', 'status', '--porcelain')),
            'tools': {'node': command('node', '--version'), 'npm': command('npm', '--version'),
                      'python': sys.version.split()[0]},
            'toolchain_sha256': hashlib.sha256((ROOT / 'scripts/toolchain.env').read_bytes()).hexdigest(),
            'independently_attested': False,
        }
    elif action == 'finish':
        report = json.loads(path.read_text())
        report['exit_code'] = int(value)
        report['release_assets'] = {item.name: hashlib.sha256(item.read_bytes()).hexdigest()
                                    for item in sorted((ROOT / 'dist').glob('*')) if item.is_file()}
        report['source_unchanged'] = fingerprint() == report['source_sha256'] and command('git', 'rev-parse', 'HEAD') == report['revision']
        report['status'] = 'passed' if int(value) == 0 and report['source_unchanged'] else 'failed'
    else:
        raise ValueError('Unknown evidence action')
    path.write_text(json.dumps(report, indent=2) + '\n')
    if action == 'finish' and report['status'] != 'passed':
        sys.exit(1)

if __name__ == '__main__':
    main()
