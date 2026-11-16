#!/usr/bin/env python3
"""Run isolated GLPI targets/shards; tests inside each instance remain serial."""
from contextlib import ExitStack
import json
import os
from pathlib import Path
import signal
import socket
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]

def validate_config(jobs, shards):
    if jobs not in (1, 2, 3, 4):
        raise ValueError('TICKETMAILER_INTEGRATION_JOBS must be between 1 and 4')
    if shards not in (1, 2):
        raise ValueError('TICKETMAILER_INTEGRATION_SHARDS must be 1 or 2')

def test_ids(report):
    if report.get('errors'):
        raise ValueError('Playwright discovery reported errors')
    result = set()
    def visit(suite):
        for spec in suite.get('specs', []):
            for test in spec['tests']:
                key = spec['id'] + ':' + test['projectName']
                if key in result:
                    raise ValueError('Duplicate test ID in Playwright discovery')
                result.add(key)
        for child in suite.get('suites', []):
            visit(child)
    visit(report)
    if not result:
        raise ValueError('Empty Playwright discovery')
    return result

def validate_partitions(full, partitions):
    seen = set()
    for part in partitions:
        if not part or seen & part:
            raise ValueError('Empty or overlapping E2E shard')
        seen.update(part)
    if seen != full:
        raise ValueError('E2E shards omit or add tests')

def verify_inventory(shards, environment):
    command = ['npx', 'playwright', 'test', '--list', '--reporter=json']
    if shards > 1:
        command += ['--fully-parallel', '--workers=1']
    def discover(extra):
        return test_ids(json.loads(subprocess.check_output(command + extra, cwd=ROOT, env=environment, text=True)))
    full = discover([])
    parts = [discover(['--shard=' + str(i) + '/' + str(shards)]) for i in range(1, shards + 1)] if shards > 1 else [full]
    validate_partitions(full, parts)
    destination = environment.get('TICKETMAILER_EVIDENCE_DIR')
    if destination:
        (Path(destination) / 'e2e-inventory.json').write_text(json.dumps({'all':sorted(full), 'shards':[sorted(p) for p in parts]}, indent=2) + '\n')
    print('E2E inventory: ' + str(len(full)) + ' tests; exhaustive disjoint shards ' + str([len(p) for p in parts]), flush=True)

def run_matrix(jobs, targets, environment, command=None, shards=1):
    validate_config(jobs, shards)
    command = command or ['bash', 'scripts/release/integration.sh']
    tasks = [(version, digest, shard) for shard in range(1, shards + 1) for version, digest in targets]
    running = []
    results = []
    previous = {}
    def interrupted(signum, frame):
        raise KeyboardInterrupt
    try:
        for sig in (signal.SIGINT, signal.SIGTERM):
            previous[sig] = signal.signal(sig, interrupted)
        with ExitStack() as stack:
            sockets = [stack.enter_context(socket.socket()) for _ in range(len(tasks) * 2)]
            for sock in sockets:
                sock.bind(('127.0.0.1', 0))
            ports = [sock.getsockname()[1] for sock in sockets]
            for index, (version, digest, shard) in enumerate(tasks):
                if len(running) == jobs:
                    for process, stream in running:
                        results.append(process.wait())
                        if stream:
                            stream.close()
                    running.clear()
                    if any(results):
                        break
                env = dict(environment, RELEASE_GLPI_VERSION=version, RELEASE_GLPI_SHA256=digest,
                           RELEASE_E2E_SHARD=str(shard), RELEASE_E2E_SHARDS=str(shards),
                           RELEASE_HTTP_PORT=str(ports[index * 2]), RELEASE_MAIL_PORT=str(ports[index * 2 + 1]))
                sockets[index * 2].close()
                sockets[index * 2 + 1].close()
                suffix = '-shard-' + str(shard) if shards > 1 else ''
                stream = None
                if env.get('TICKETMAILER_EVIDENCE_DIR'):
                    stream = (Path(env['TICKETMAILER_EVIDENCE_DIR']) / ('integration-' + version + suffix + '.log')).open('w')
                print('Starting GLPI ' + version + suffix, flush=True)
                try:
                    process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=stream,
                                               stderr=subprocess.STDOUT, start_new_session=True)
                except BaseException:
                    if stream:
                        stream.close()
                    raise
                running.append((process, stream))
        for process, stream in running:
            results.append(process.wait())
        return 1 if any(results) else 0
    finally:
        for process, stream in running:
            if process.poll() is None:
                try:
                    os.killpg(process.pid, signal.SIGTERM)
                except ProcessLookupError:
                    pass
        for process, stream in running:
            process.wait()
            if stream:
                stream.close()
        for sig, handler in previous.items():
            signal.signal(sig, handler)

def main():
    env = dict(os.environ)
    jobs = int(env.get('TICKETMAILER_INTEGRATION_JOBS', '4'))
    shards = int(env.get('TICKETMAILER_INTEGRATION_SHARDS', '2'))
    validate_config(jobs, shards)
    verify_inventory(shards, env)
    targets = [(env['GLPI_' + name], env['GLPI_' + name + '_SHA256']) for name in ('PRIMARY', 'SUPPORTED')]
    return run_matrix(jobs, targets, env, shards=shards)

if __name__ == '__main__':
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        sys.exit(130)
    except ValueError as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
