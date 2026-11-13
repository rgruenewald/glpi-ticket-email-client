"""Fail-closed policy tests; real CodeQL fixtures have a separate explicit entrypoint."""
import importlib.util
import json
import inspect
import io
import os
from pathlib import Path
import subprocess
import tempfile
import tarfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('security_scan', ROOT / 'scripts/security/scan.py')
scan = importlib.util.module_from_spec(spec)
spec.loader.exec_module(scan)

class SecurityGateTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)

    def report(self, score='7.0'):
        rule = {'id': 'example/security', 'properties': {'security-severity': score, 'tags': ['security']}}
        return {'version': '2.1.0', 'runs': [{'tool': {'driver': {'name': 'CodeQL', 'rules': [rule]}},
                'invocations': [{'executionSuccessful': True, 'toolExecutionNotifications': [
                    self.coverage_note('js/baseline/expected-extracted-files'),
                    self.coverage_note('js/diagnostics/successfully-extracted-files')]}],
                'results': [{'ruleId': rule['id']}]}]}

    def coverage_note(self, identifier, path='app.js', level='none'):
        return {'descriptor': {'id': identifier}, 'level': level,
                'locations': [{'physicalLocation': {'artifactLocation':
                    {'uri': path, 'uriBaseId': '%SRCROOT%'}}}]}

    def evaluate(self, report):
        path = self.root / 'report.sarif'
        path.write_text(json.dumps(report))
        return scan.evaluate_sarif(path, 'javascript', {'app.js'})

    def test_high_and_critical_fail_threshold_but_moderate_is_report_only(self):
        for score in ('7.0', '9.8', '10.0'):
            self.assertEqual(len(self.evaluate(self.report(score))), 1)
        self.assertEqual(self.evaluate(self.report('6.9')), [])

    def test_empty_results_are_valid_only_in_complete_report(self):
        report = self.report()
        report['runs'][0]['results'] = []
        self.assertEqual(self.evaluate(report), [])
        for invalid in ({}, {'version': '2.1.0', 'runs': []}):
            with self.assertRaises(ValueError): self.evaluate(invalid)

    def test_unknown_or_invalid_security_metadata_fails(self):
        for score in (None, 'NaN', 'bad', '-1', '11'):
            with self.assertRaises(ValueError): self.evaluate(self.report(score))
        report = self.report()
        report['runs'][0]['results'][0]['ruleId'] = 'unknown'
        with self.assertRaises(KeyError): self.evaluate(report)

    def test_failed_sarif_invocation_fails(self):
        report = self.report()
        report['runs'][0]['invocations'][0]['executionSuccessful'] = False
        with self.assertRaises(ValueError): self.evaluate(report)

    def test_incomplete_sarif_fixtures_fail_closed(self):
        fixtures = ROOT / 'tests/security/sarif'
        self.assertEqual(scan.evaluate_sarif(fixtures / 'complete-clean.json',
                                             'javascript', {'app.js'}), [])
        for name in ('missing-invocation.json', 'missing-extraction.json', 'partial-extraction.json'):
            with self.subTest(fixture=name):
                with self.assertRaises(ValueError):
                    scan.evaluate_sarif(fixtures / name, 'javascript', {'app.js'})

    def test_invocation_success_must_be_explicit(self):
        for invocation in ({}, {'executionSuccessful': None}, {'executionSuccessful': 1}):
            report = self.report()
            report['runs'][0]['invocations'] = [invocation]
            with self.assertRaises(ValueError): self.evaluate(report)

    def test_extraction_and_coverage_diagnostics_block(self):
        for identifier, level in (('js/diagnostics/extraction-errors', 'none'),
                                  ('js/diagnostics/extraction-warnings', 'none'),
                                  ('cli/coverage', 'warning'), ('cli/error', 'error')):
            report = self.report()
            report['runs'][0]['invocations'][0]['toolExecutionNotifications'].append(
                self.coverage_note(identifier, level=level))
            with self.assertRaises(ValueError): self.evaluate(report)

    def test_coverage_needs_success_for_baseline_and_independent_source_list(self):
        report = self.report()
        notes = report['runs'][0]['invocations'][0]['toolExecutionNotifications']
        notes.append(self.coverage_note('js/baseline/expected-extracted-files', 'other.js'))
        with self.assertRaises(ValueError): self.evaluate(report)
        report = self.report()
        path = self.root / 'report.json'
        path.write_text(json.dumps(report))
        with self.assertRaises(ValueError):
            scan.evaluate_sarif(path, 'javascript', {'app.js', 'ignored.ts'})
        details = {}
        self.assertEqual(len(scan.evaluate_sarif(path, 'javascript', {'app.js'}, details)), 1)
        self.assertEqual(details['expected_source_files'], ['app.js'])

    def test_expected_sources_has_no_test_or_generated_exclusions(self):
        for name in ('tests/check.js', 'generated/app.ts', 'app.py', 'bad.js.fixture'):
            p = self.root / name
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text('')
        self.assertEqual(scan.expected_sources(self.root, 'javascript'),
                         {'tests/check.js', 'generated/app.ts'})
        self.assertEqual(scan.expected_sources(self.root, 'python'), {'app.py'})

    def test_actions_dependabot_ecosystem_removed(self):
        self.assertNotIn('github-actions', (ROOT / '.github/dependabot.yml').read_text())

    def test_suppression_does_not_bypass_high_finding(self):
        report = self.report()
        report['runs'][0]['results'][0]['suppressions'] = [{'kind': 'inSource'}]
        self.assertEqual(len(self.evaluate(report)), 1)

    def test_missing_cli_fails_without_installing_or_network(self):
        with patch.dict(os.environ, {'XDG_CACHE_HOME': str(self.root)}):
            with self.assertRaises(RuntimeError): scan.verified_cli()

    def test_missing_cli_process_exits_nonzero(self):
        result = subprocess.run(['python3', str(ROOT / 'scripts/security/scan.py')],
                                cwd=ROOT, text=True, capture_output=True,
                                env={**os.environ, 'XDG_CACHE_HOME': str(self.root)})
        self.assertEqual(result.returncode, 1)
        self.assertIn('Pinned CodeQL missing', result.stderr)
        self.assertFalse((self.root / 'ticketmailer').exists())

    def test_installer_rejects_bad_digest_before_extracting_or_executing(self):
        spec = importlib.util.spec_from_file_location('codeql_installer', ROOT / 'scripts/security/install-codeql.py')
        installer = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(installer)
        response = io.BytesIO(b'bad archive')
        response.url = 'https://example.invalid/bundle'
        with patch.object(installer, 'CACHE', self.root / 'cache'):
            with patch.object(installer, 'LOCK', {**installer.LOCK, 'size': 11}):
                with patch.object(installer.urllib.request, 'urlopen', return_value=response):
                    with patch.object(installer.subprocess, 'check_output') as execute:
                        with patch.object(installer.tarfile, 'open') as extract:
                            with self.assertRaisesRegex(RuntimeError, 'SHA-256 mismatch'):
                                installer.install()
                            execute.assert_not_called()
                            extract.assert_not_called()

    def test_safe_extraction_uses_only_regular_files_on_python_311(self):
        spec = importlib.util.spec_from_file_location('installer', ROOT / 'scripts/security/install-codeql.py')
        installer = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(installer)
        archive = self.root / 'safe.tar'
        with tarfile.open(archive, 'w') as bundle:
            for name, mode in (('codeql/codeql', 0o4755), ('codeql/readme', 0o666)):
                member = tarfile.TarInfo(name)
                member.mode = mode
                member.size = 4
                bundle.addfile(member, io.BytesIO(b'test'))
        out = self.root / 'out'
        out.mkdir()
        with patch.object(tarfile.TarFile, 'extractall', side_effect=AssertionError('unsafe extraction')):
            with tarfile.open(archive) as bundle:
                installer.extract_bundle(bundle, out)
        self.assertEqual((out / 'codeql/codeql').read_bytes(), b'test')
        self.assertEqual((out / 'codeql/codeql').stat().st_mode & 0o7777, 0o755)
        self.assertEqual((out / 'codeql/readme').stat().st_mode & 0o7777, 0o644)

    def test_safe_extraction_rejects_paths_links_and_devices_before_writes(self):
        spec = importlib.util.spec_from_file_location('installer', ROOT / 'scripts/security/install-codeql.py')
        installer = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(installer)
        for name, kind in (('../escape', tarfile.REGTYPE),
                           ('codeql/../escape', tarfile.REGTYPE),
                           ('/codeql/absolute', tarfile.REGTYPE),
                           ('other/root', tarfile.REGTYPE),
                           ('codeql/link', tarfile.SYMTYPE),
                           ('codeql/hardlink', tarfile.LNKTYPE),
                           ('codeql/device', tarfile.CHRTYPE),
                           ('codeql/pipe', tarfile.FIFOTYPE)):
            with self.subTest(name=name):
                archive = self.root / 'bad.tar'
                with tarfile.open(archive, 'w') as bundle:
                    safe = tarfile.TarInfo('codeql/safe')
                    bundle.addfile(safe)
                    bad = tarfile.TarInfo(name)
                    bad.type = kind
                    bad.linkname = '../../escape'
                    bundle.addfile(bad)
                out = self.root / 'rejected'
                out.mkdir(exist_ok=True)
                with tarfile.open(archive) as bundle:
                    with self.assertRaises(RuntimeError): installer.extract_bundle(bundle, out)
                self.assertEqual(list(out.iterdir()), [])

    def test_wrong_version_fails(self):
        location = self.root / 'ticketmailer/codeql' / scan.LOCK['version']
        cli = location / 'codeql/codeql'
        cli.parent.mkdir(parents=True)
        cli.write_text('unused')
        cli.chmod(0o755)
        (location / 'bundle-sha256').write_text(scan.LOCK['sha256'])
        with patch.dict(os.environ, {'XDG_CACHE_HOME': str(self.root)}):
            with patch.object(scan.subprocess, 'check_output', return_value='{"version":"0"}'):
                with self.assertRaises(RuntimeError): scan.verified_cli()

    def test_analysis_nonzero_exit_fails(self):
        cli = self.root / 'codeql/codeql'
        suite = (cli.parent / 'qlpacks/codeql/javascript-queries' / scan.LOCK['query_packs']['javascript'] /
                 'codeql-suites/javascript-code-scanning.qls')
        suite.parent.mkdir(parents=True)
        suite.write_text('unused')
        output = self.root / 'out'
        output.mkdir()
        failure = subprocess.CalledProcessError(32, ['codeql', 'database', 'analyze'])
        with patch.object(scan.subprocess, 'run', side_effect=[None, failure]):
            with self.assertRaises(subprocess.CalledProcessError): scan.scan_tree(self.root, output, cli)

    def test_artifacts_are_isolated_from_playwright_cleanup(self):
        self.assertEqual(scan.ARTIFACT_ROOT.relative_to(ROOT), Path('.cache/security'))
        self.assertFalse(scan.ARTIFACT_ROOT.is_relative_to(ROOT / 'test-results'))
        ignored = subprocess.run(['git', 'check-ignore', str(scan.ARTIFACT_ROOT / 'run/summary.json')],
                                 cwd=ROOT, capture_output=True)
        self.assertEqual(ignored.returncode, 0)

    def test_all_and_quick_include_security_before_other_gates(self):
        ci = (ROOT / 'scripts/ci').read_text()
        for mode in ('all', 'quick'):
            section = ci.split('  ' + mode + ')', 1)[1].split(';;', 1)[0]
            self.assertIn('gate_security', section)
        self.assertIn('scripts/security/scan.py', ci)

    def test_real_publication_requires_full_local_ci(self):
        spec = importlib.util.spec_from_file_location('local_publisher_security', ROOT / 'scripts/release/publish.py')
        publisher = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(publisher)
        self.assertEqual(inspect.signature(publisher.plan_or_publish).parameters['ci_mode'].default, 'all')
        with self.assertRaisesRegex(ValueError, 'requires --ci-mode all'):
            publisher.plan_or_publish(root=self.root, publish=True, ci_mode='quick')
