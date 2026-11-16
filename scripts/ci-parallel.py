#!/usr/bin/env python3
"""Run independent CI gates after the isolated UI benchmark, failing closed."""
import os
from pathlib import Path
import signal
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
GATES = ('security', 'php-matrix', 'integration')

def run_gates(jobs, environment, command=None):
    if jobs not in (1, 3):
        raise ValueError('TICKETMAILER_GATE_JOBS must be 1 or 3')
    command = command or ['bash', 'scripts/ci', '_worker']
    running = []
    results = []
    previous = {}
    def interrupted(signum, frame):
        raise SystemExit(128 + signum)
    try:
        for sig in (signal.SIGINT, signal.SIGTERM):
            previous[sig] = signal.signal(sig, interrupted)
        for offset in range(0, len(GATES), jobs):
            for gate in GATES[offset:offset + jobs]:
                stream = None
                if environment.get('TICKETMAILER_EVIDENCE_DIR'):
                    stream = (Path(environment['TICKETMAILER_EVIDENCE_DIR']) / ('gate-' + gate + '.log')).open('w')
                print('Starting CI gate ' + gate, flush=True)
                try:
                    process = subprocess.Popen([*command, gate], cwd=ROOT, env=environment,
                                               stdout=stream, stderr=subprocess.STDOUT, start_new_session=True)
                except BaseException:
                    if stream:
                        stream.close()
                    raise
                running.append((gate, process, stream))
            for gate, process, stream in running:
                result = process.wait()
                results.append(result)
                print('CI gate ' + gate + ': exit ' + str(result), flush=True)
                if stream:
                    stream.close()
            running.clear()
        return 1 if any(results) else 0
    finally:
        for gate, process, stream in running:
            if process.poll() is None:
                try:
                    os.killpg(process.pid, signal.SIGTERM)
                except ProcessLookupError:
                    pass
        for gate, process, stream in running:
            try:
                process.wait(timeout=60)
            except subprocess.TimeoutExpired:
                try:
                    os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
                process.wait()
            if stream:
                stream.close()
        for sig, handler in previous.items():
            signal.signal(sig, handler)

def main():
    return run_gates(int(os.environ.get('TICKETMAILER_GATE_JOBS', '3')), dict(os.environ))

if __name__ == '__main__':
    try:
        sys.exit(main())
    except ValueError as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
