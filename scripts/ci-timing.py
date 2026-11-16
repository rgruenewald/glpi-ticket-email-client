#!/usr/bin/env python3
"""Record one completed CI step using a monotonic clock, without command arguments."""
import json
import os
from pathlib import Path
import sys
import tempfile
import time

def main():
    name, started, code = sys.argv[1:]
    elapsed = (time.monotonic_ns() - int(started)) / 1_000_000_000
    print(f'TIMING {name}: {elapsed:.3f}s (exit {code})', file=sys.stderr, flush=True)
    destination = os.environ.get('TICKETMAILER_EVIDENCE_DIR')
    if destination:
        directory = Path(destination) / 'timings'
        directory.mkdir(exist_ok=True)
        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', dir=directory, delete=False) as stream:
            json.dump({'step': name, 'duration_seconds': elapsed, 'exit_code': int(code)}, stream)

if __name__ == '__main__':
    main()
