"""Independent gate scheduling, failure propagation and process-group cleanup."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('ci_parallel', ROOT / 'scripts/ci-parallel.py')
parallel = importlib.util.module_from_spec(spec)
spec.loader.exec_module(parallel)

WORKER = '''import json, os, pathlib, signal, subprocess, sys, time
root = pathlib.Path(os.environ['RESULTS'])
name = sys.argv[-1]
def stop(sig, frame):
    (root / (name + '.stopped')).touch()
    raise SystemExit(143)
signal.signal(signal.SIGTERM, stop)
start = time.monotonic()
(root / (name + '.ready')).touch()
if os.environ.get('NESTED'):
    subprocess.Popen([sys.executable, __file__, name + '-child'], env={k:v for k,v in os.environ.items() if k != 'NESTED'})
time.sleep(float(os.environ.get('DELAY', '0.2')))
(root / (name + '.json')).write_text(json.dumps({'start':start, 'end':time.monotonic()}))
sys.exit(23 if os.environ.get('FAIL') == name else 0)
'''

class GateSchedulingTest(unittest.TestCase):
    def exercise(self, jobs, fail=''):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            worker = folder / 'worker.py'
            worker.write_text(WORKER)
            env = dict(os.environ, RESULTS=tmp, TICKETMAILER_EVIDENCE_DIR=tmp, FAIL=fail)
            result = parallel.run_gates(jobs, env, [sys.executable, str(worker)])
            reports = {p.stem: json.loads(p.read_text()) for p in folder.glob('*.json')}
            return result, reports

    def test_three_gates_overlap(self):
        result, reports = self.exercise(3)
        self.assertEqual(result, 0)
        self.assertEqual(set(reports), set(parallel.GATES))
        self.assertLess(max(r['start'] for r in reports.values()), min(r['end'] for r in reports.values()))

    def test_serial_control_retains_every_gate(self):
        result, reports = self.exercise(1)
        self.assertEqual(result, 0)
        for a, b in zip(parallel.GATES, parallel.GATES[1:]):
            self.assertGreaterEqual(reports[b]['start'], reports[a]['end'])

    def test_failure_of_any_gate_blocks_success_without_skipping_peers(self):
        for gate in parallel.GATES:
            with self.subTest(gate=gate):
                result, reports = self.exercise(3, fail=gate)
                self.assertEqual(result, 1)
                self.assertEqual(set(reports), set(parallel.GATES))

    def test_invalid_concurrency_is_rejected(self):
        for jobs in (0, 2, 4):
            with self.assertRaises(ValueError):
                parallel.run_gates(jobs, {})

    def test_sigterm_cleans_all_worker_and_nested_processes(self):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            worker = folder / 'worker.py'
            worker.write_text(WORKER)
            code = ('import runpy,os,sys; m=runpy.run_path(sys.argv[1]); '
                    'sys.exit(m["run_gates"](3,dict(os.environ),[sys.executable,sys.argv[2]]))')
            process = subprocess.Popen([sys.executable, '-c', code, str(ROOT / 'scripts/ci-parallel.py'), str(worker)],
                env=dict(os.environ, RESULTS=tmp, TICKETMAILER_EVIDENCE_DIR=tmp, DELAY='30', NESTED='1'),
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                deadline = time.monotonic() + 10
                while len(list(folder.glob('*.ready'))) < 6 and time.monotonic() < deadline:
                    time.sleep(0.01)
                self.assertEqual(len(list(folder.glob('*.ready'))), 6)
                process.terminate()
                self.assertEqual(process.wait(timeout=10), 143)
                deadline = time.monotonic() + 3
                while len(list(folder.glob('*.stopped'))) < 6 and time.monotonic() < deadline:
                    time.sleep(0.01)
                self.assertEqual(len(list(folder.glob('*.stopped'))), 6)
            finally:
                if process.poll() is None:
                    process.terminate()
                    process.wait(timeout=10)

    def test_spawn_failure_cleans_already_started_worker(self):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            worker = folder / 'worker.py'
            worker.write_text(WORKER)
            env = dict(os.environ, RESULTS=tmp, TICKETMAILER_EVIDENCE_DIR=tmp, DELAY='30')
            real_popen = subprocess.Popen
            def spawn(*args, **kwargs):
                if (folder / 'security.ready').exists():
                    raise FileNotFoundError('synthetic spawn failure')
                process = real_popen(*args, **kwargs)
                deadline = time.monotonic() + 10
                while not (folder / 'security.ready').exists() and time.monotonic() < deadline:
                    time.sleep(0.01)
                return process
            with patch.object(parallel.subprocess, 'Popen', side_effect=spawn):
                with self.assertRaises(FileNotFoundError):
                    parallel.run_gates(3, env, [sys.executable, str(worker)])
            self.assertTrue((folder / 'security.stopped').exists())

    def test_full_pipeline_keeps_performance_outside_parallel_phase(self):
        ci = (ROOT / 'scripts/ci').read_text()
        section = ci.split('  all)', 1)[1].split(';;', 1)[0]
        self.assertLess(section.index('gate_package_and_contracts'), section.index('scripts/ci-parallel.py'))
        for gate in parallel.GATES:
            self.assertIn(gate, ci.split('  _worker)', 1)[1])
        self.assertIn('gate_security', ci.split('  quick)', 1)[1].split(';;', 1)[0])
