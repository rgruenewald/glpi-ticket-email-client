"""CI isolation, timing and real subprocess scheduling contracts, without Docker."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('integration_matrix', ROOT / 'scripts/release/integration-matrix.py')
matrix = importlib.util.module_from_spec(spec)
spec.loader.exec_module(matrix)

class MatrixTest(unittest.TestCase):
    def exercise(self, jobs, fail=False, shards=1):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            worker = folder / 'worker.py'
            worker.write_text(
                'import json,os,time,pathlib,sys\n'
                'start=time.monotonic()\n'
                'time.sleep(0.3)\n'
                'data={"start":start,"end":time.monotonic(),"ports":[os.environ["RELEASE_HTTP_PORT"],os.environ["RELEASE_MAIL_PORT"]]}\n'
                'pathlib.Path(os.environ["RESULTS"],os.environ["RELEASE_GLPI_VERSION"]+("-"+os.environ["RELEASE_E2E_SHARD"] if os.environ.get("KEY_SHARD") else "")+".json").write_text(json.dumps(data))\n'
                'sys.exit(23 if os.environ.get("FAIL")==os.environ["RELEASE_GLPI_VERSION"] else 0)\n')
            env = dict(os.environ, RESULTS=tmp, TICKETMAILER_EVIDENCE_DIR=tmp)
            if shards > 1:
                env['KEY_SHARD'] = '1'
            if fail:
                env['FAIL'] = 'primary'
            result = matrix.run_matrix(jobs, [('primary', 'a'), ('supported', 'b')], env, [sys.executable, str(worker)], shards=shards)
            reports = {p.stem: json.loads(p.read_text()) for p in folder.glob('*.json')}
            return result, reports

    def test_targets_overlap_with_distinct_ports(self):
        result, reports = self.exercise(2)
        self.assertEqual(result, 0)
        a, b = reports['primary'], reports['supported']
        self.assertLess(max(a['start'], b['start']), min(a['end'], b['end']))
        self.assertEqual(len(set(a['ports'] + b['ports'])), 4)

    def test_four_isolated_version_shards_overlap_with_eight_distinct_ports(self):
        result, reports = self.exercise(4, shards=2)
        self.assertEqual(result, 0)
        self.assertEqual(set(reports), {'primary-1','supported-1','primary-2','supported-2'})
        self.assertEqual(len({port for report in reports.values() for port in report['ports']}), 8)
        self.assertLess(max(r['start'] for r in reports.values()), min(r['end'] for r in reports.values()))

    def test_invalid_shard_count_is_rejected_before_launch(self):
        for shards in (0, 3):
            with self.assertRaises(ValueError):
                matrix.run_matrix(4, [], {}, shards=shards)

    def test_inventory_rejects_missing_added_overlapping_or_empty_partitions(self):
        full = {'a','b','c'}
        matrix.validate_partitions(full, [{'a','b'},{'c'}])
        for parts in ([{'a'},{'b'}],[{'a','b'},{'b','c'}],[{'a','b'},{'c','d'}],[full,set()]):
            with self.assertRaises(ValueError):
                matrix.validate_partitions(full, parts)

    def test_inventory_collects_nested_test_ids_and_rejects_errors_and_duplicates(self):
        item = {'id':'a','tests':[{'projectName':''}]}
        self.assertEqual(matrix.test_ids({'suites':[{'specs':[item]}]}), {'a:'})
        for report in ({}, {'errors':[{'message':'bad'}]}, {'specs':[item,item]}):
            with self.assertRaises(ValueError):
                matrix.test_ids(report)

    def test_serial_comparison_retains_both_targets(self):
        result, reports = self.exercise(1)
        self.assertEqual(result, 0)
        self.assertGreaterEqual(reports['supported']['start'], reports['primary']['end'])

    def test_parallel_failure_is_not_hidden_by_successful_peer(self):
        result, reports = self.exercise(2, fail=True)
        self.assertEqual(result, 1)
        self.assertEqual(set(reports), {'primary', 'supported'})

    def test_serial_failure_stops_before_next_target(self):
        result, reports = self.exercise(1, fail=True)
        self.assertEqual(result, 1)
        self.assertEqual(set(reports), {'primary'})

    def test_invalid_concurrency_is_rejected(self):
        for jobs in (0, 5):
            with self.assertRaises(ValueError):
                matrix.run_matrix(jobs, [], {})

    def test_termination_reaches_both_target_process_groups(self):
        self.exercise_termination(1)

    def test_termination_reaches_all_four_shard_process_groups(self):
        self.exercise_termination(2)

    def test_shards_keep_serial_workers_and_distinct_reports(self):
        script = (ROOT / "scripts/release/integration.sh").read_text()
        self.assertIn("--fully-parallel --workers=1 --shard=", script)
        self.assertIn('TICKETMAILER_E2E_REPORT_DIR="$TICKETMAILER_EVIDENCE_DIR/$prefix"', script)

    def exercise_termination(self, shards):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            worker = folder / 'worker.py'
            worker.write_text('import os,pathlib,signal,time\n'
                              'root=pathlib.Path(os.environ["RESULTS"])\n'
                              'name=os.environ["RELEASE_GLPI_VERSION"]+"-"+os.environ["RELEASE_E2E_SHARD"]\n'
                              'def stop(sig,frame):\n'
                              ' (root/(name+".stopped")).touch()\n'
                              ' raise SystemExit(143)\n'
                              'signal.signal(signal.SIGTERM,stop)\n'
                              '(root/(name+".ready")).touch()\n'
                              'time.sleep(30)\n')
            code = ('import runpy,os,sys; module=runpy.run_path(sys.argv[1]); '
                    'module["run_matrix"](int(os.environ["SHARDS"])*2, [("a","x"),("b","y")], dict(os.environ), [sys.executable,sys.argv[2]], shards=int(os.environ["SHARDS"]))')
            process = subprocess.Popen([sys.executable, '-c', code, str(ROOT / 'scripts/release/integration-matrix.py'), str(worker)],
                                       env=dict(os.environ, RESULTS=tmp, TICKETMAILER_EVIDENCE_DIR=tmp, SHARDS=str(shards)),
                                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                deadline = time.monotonic() + 10
                while len(list(folder.glob('*.ready'))) < 2 * shards and time.monotonic() < deadline:
                    time.sleep(0.01)
                self.assertEqual(len(list(folder.glob('*.ready'))), 2 * shards)
                process.terminate()
                self.assertNotEqual(process.wait(timeout=10), 0)
                self.assertEqual(len(list(folder.glob('*.stopped'))), 2 * shards)
            finally:
                if process.poll() is None:
                    process.terminate()
                    process.wait(timeout=10)

class TimingTest(unittest.TestCase):
    def test_timing_preserves_failure_and_does_not_corrupt_stdout(self):
        with tempfile.TemporaryDirectory() as tmp:
            env = dict(os.environ, TICKETMAILER_EVIDENCE_DIR=tmp)
            result = subprocess.run(['bash', '-c',
                'set -e; source scripts/ci-timing.sh; fail() { echo payload; false; echo unreachable; }; timed example fail; echo continued'],
                cwd=ROOT, env=env, text=True, capture_output=True)
            self.assertEqual(result.returncode, 1)
            self.assertEqual(result.stdout, 'payload\n')
            records = list((Path(tmp) / 'timings').glob('*.json'))
            self.assertEqual(len(records), 1)
            timing = json.loads(records[0].read_text())
            self.assertEqual(timing['exit_code'], 1)
            self.assertGreater(timing['duration_seconds'], 0)

    def test_success_keeps_stdout_and_specific_exit_code(self):
        for code in (0, 23):
            result = subprocess.run(['bash', '-c',
                f'set -e; source scripts/ci-timing.sh; timed example bash -c "echo payload; exit {code}"'],
                cwd=ROOT, env={k: v for k, v in os.environ.items() if k != 'TICKETMAILER_EVIDENCE_DIR'}, text=True, capture_output=True)
            self.assertEqual(result.returncode, code)
            self.assertEqual(result.stdout, 'payload\n')

    def test_ci_package_is_private_and_publisher_test_is_isolated(self):
        ci = (ROOT / 'scripts/ci').read_text()
        self.assertIn('RELEASE_ASSET_DIR="$TICKETMAILER_EVIDENCE_DIR/package"', ci)
        self.assertIn('--out "$RELEASE_ASSET_DIR"', ci)
        self.assertIn('mkdir -p .cache/phpstan', ci)
        self.assertIn('out=Path(tmp)', (ROOT / 'tests/release/test_release.py').read_text())
