"""Toolchain drift, compatibility and evidence contracts (offline)."""
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import tomllib
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('ci_evidence', ROOT / 'scripts/ci-evidence.py')
evidence = importlib.util.module_from_spec(spec)
spec.loader.exec_module(evidence)


class ToolchainTest(unittest.TestCase):
    def test_testcontainer_uses_one_vendor_path_and_current_contract(self):
        image = (ROOT / 'docker/test/Dockerfile').read_text()
        compose = (ROOT / 'docker-compose.yml').read_text()
        self.assertIn('COMPOSER_VENDOR_DIR=/app/vendor', image)
        self.assertIn('WORKDIR /app', image)
        self.assertIn('vendor/bin/phpunit', image)
        self.assertIn('scripts/verify/score.sh', image)
        self.assertNotIn('.agent/contracts', image)
        self.assertIn('test_vendor:/app/vendor', compose)

    def test_runtime_matrix_and_pins_agree(self):
        pins = dict(line.split('=', 1) for line in (ROOT / 'scripts/toolchain.env').read_text().splitlines() if line and not line.startswith('#'))
        self.assertEqual(pins['GLPI_PRIMARY'], '12.0.2')
        self.assertEqual(pins['GLPI_SUPPORTED'], '11.0.8')
        image = (ROOT / 'docker/glpi/Dockerfile').read_text()
        compose = (ROOT / 'scripts/release/compose.yml').read_text()
        for name in ('GLPI_PRIMARY', 'GLPI_PRIMARY_SHA256'):
            self.assertIn(pins[name], image)
            self.assertIn(pins[name], compose)
        self.assertIn('sha256sum -c -', image)
        for name, value in pins.items():
            if 'IMAGE' in name:
                self.assertRegex(value, r'@sha256:[0-9a-f]{64}$')
        ci = (ROOT / 'scripts/ci').read_text()
        self.assertIn('for target in PRIMARY SUPPORTED', ci)
        self.assertIn('RELEASE_HTTP_PORT="$http_port" RELEASE_MAIL_PORT="$mail_port"', ci)
        self.assertIn('mise which python3', ci)
        self.assertIn('Python 3.14.6', ci)
        self.assertNotIn('HOST_PHP', ci)
        integration = (ROOT / 'scripts/release/integration.sh').read_text()
        self.assertIn('exec -T --user www-data glpi php bin/console --version', integration)
        self.assertIn('files/_log/php-errors.log', integration)
        self.assertIn('grep -Fxq "GLPI CLI ${RELEASE_GLPI_VERSION:-12.0.2}"', integration)

    def test_dev_and_release_service_images_match(self):
        images = []
        for name in ('docker-compose.yml', 'scripts/release/compose.yml'):
            images.append(re.findall(r'image: ((?:mariadb|axllent/mailpit):[^\s]+)', (ROOT / name).read_text()))
        self.assertEqual(images[0], images[1])
        self.assertTrue(all('@sha256:' in item for item in images[0]))

    def test_host_versions_are_pinned_and_checked(self):
        tools = tomllib.loads((ROOT / ".mise.toml").read_text())["tools"]
        package = json.loads((ROOT / "package.json").read_text())
        ci = (ROOT / "scripts/ci").read_text()
        self.assertIn("Python " + tools["python"], ci)
        self.assertIn("v" + tools["node"], ci)
        self.assertIn(package["packageManager"].split("@")[1], ci)

    def test_update_coverage_and_reports(self):
        dependabot = (ROOT / '.github/dependabot.yml').read_text()
        for item in ('package-ecosystem: npm', '/docker/test', '/scripts/release'):
            self.assertIn(item, dependabot)
        playwright = (ROOT / 'playwright.config.js').read_text()
        for item in ("['junit'", "['html'", 'retain-on-failure', 'TICKETMAILER_E2E_REPORT_DIR'):
            self.assertIn(item, playwright)

    def test_phpstan_baseline_only_covers_external_glpi_symbols(self):
        baseline = (ROOT / 'phpstan-baseline.neon').read_text()
        identifiers = set(re.findall(r'identifier: ([^\s]+)', baseline))
        self.assertEqual(identifiers, {'class.notFound', 'function.notFound', 'constant.notFound'})
        config = (ROOT / 'phpstan.neon').read_text()
        self.assertIn('reportUnmatchedIgnoredErrors: true', config)
        self.assertIn('phpVersion: 80200', config)
        self.assertIn('phpstan analyse', (ROOT / 'scripts/ci').read_text())

    def test_evidence_rejects_changed_source_and_failed_gate(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / 'scripts').mkdir()
            (root / 'scripts/toolchain.env').write_text('test pin')
            output = root / '.cache'
            output.mkdir()
            (root / '.gitignore').write_text('.cache/\n')
            subprocess.run(['git', 'init', '-q', str(root)], check=True)
            def command(*args):
                return '' if args == ('git', 'status', '--porcelain') else 'fixture-version'
            for code, mutate, passed in ((0, False, True), (1, False, False), (0, True, False)):
                with patch.object(evidence, 'ROOT', root), patch.object(evidence, 'command', side_effect=command):
                    with patch.object(sys, 'argv', ['evidence', 'start', str(output), 'quick']):
                        evidence.main()
                    if mutate:
                        (root / 'changed.php').write_text('<?php')
                    with patch.object(sys, 'argv', ['evidence', 'finish', str(output), str(code)]):
                        if passed:
                            evidence.main()
                        else:
                            with self.assertRaises(SystemExit):
                                evidence.main()
                report = json.loads((output / 'summary.json').read_text())
                self.assertEqual(report['status'], 'passed' if passed else 'failed')
                self.assertFalse(report['independently_attested'])
                self.assertIn('release_assets', report)
            with patch.object(evidence, 'ROOT', root):
                before = evidence.fingerprint()
                (root / '.gitignore').chmod(0o755)
                self.assertNotEqual(before, evidence.fingerprint())
