#!/usr/bin/env python3
"""Explicit, checksum-pinned installation for this public GPL project. No uploads."""
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import platform
import subprocess
import tarfile
import tempfile
import urllib.request

LOCK = json.loads(Path(__file__).with_name('codeql-lock.json').read_text())
CACHE = Path(os.environ.get('XDG_CACHE_HOME', Path.home() / '.cache')) / 'ticketmailer/codeql'

def extract_bundle(bundle, destination):
    # Explicit file IO is safe on all Python 3.11 versions. Never call tar extract.
    members = bundle.getmembers()
    seen = set()
    for member in members:
        path = PurePosixPath(member.name)
        if (path.is_absolute() or '..' in path.parts or not path.parts
                or path.parts[0] != 'codeql' or path in seen
                or not (member.isfile() or member.isdir())):
            raise RuntimeError('Unsafe or unsupported bundle member')
        seen.add(path)
    for member in members:
        target = destination.joinpath(*PurePosixPath(member.name).parts)
        if member.isdir():
            target.mkdir(parents=True, exist_ok=True, mode=0o755)
        else:
            target.parent.mkdir(parents=True, exist_ok=True, mode=0o755)
            with bundle.extractfile(member) as source, target.open('xb') as output:
                shutil.copyfileobj(source, output)
            target.chmod(0o755 if member.mode & 0o111 else 0o644)

def install():
    if platform.system() != 'Linux' or platform.machine() != 'x86_64':
        raise RuntimeError('Pinned bundle supports glibc Linux x86_64 only')
    destination = CACHE / LOCK['version']
    if destination.exists():
        raise RuntimeError('Version already installed; inspect existing cache rather than overwriting it')
    CACHE.mkdir(parents=True, exist_ok=True, mode=0o700)
    with tempfile.TemporaryDirectory(prefix='.install-', dir=CACHE) as tmp:
        tmp = Path(tmp)
        archive = tmp / 'bundle.tar.gz'
        digest = hashlib.sha256()
        size = 0
        with urllib.request.urlopen(LOCK['url'], timeout=120) as response, archive.open('wb') as output:
            if not response.url.startswith('https://'):
                raise RuntimeError('Refusing non-HTTPS download redirect')
            while chunk := response.read(1024 * 1024):
                size += len(chunk)
                if size > LOCK['size']:
                    raise RuntimeError('CodeQL bundle exceeds pinned size')
                digest.update(chunk)
                output.write(chunk)
        if size != LOCK['size'] or digest.hexdigest() != LOCK['sha256']:
            raise RuntimeError('CodeQL bundle SHA-256 mismatch; nothing executed')
        unpacked = tmp / 'unpacked'
        unpacked.mkdir()
        with tarfile.open(archive, 'r:gz') as bundle:
            extract_bundle(bundle, unpacked)
        cli = unpacked / 'codeql/codeql'
        version = json.loads(subprocess.check_output([str(cli), 'version', '--format=json'], text=True))
        if version['version'] != LOCK['version']:
            raise RuntimeError('Unexpected CodeQL version')
        (unpacked / 'bundle-sha256').write_text(LOCK['sha256'] + '\n')
        unpacked.rename(destination)
    print('Installed verified CodeQL ' + LOCK['version'] + ' in ' + str(destination))

if __name__ == '__main__':
    install()
