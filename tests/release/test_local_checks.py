"""Exercise the real pre-push hook in a disposable Git repository."""
from pathlib import Path
import shutil
import os
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class PushHookTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        local_vars = subprocess.run(['git', 'rev-parse', '--local-env-vars'],
                                    text=True, capture_output=True, check=True).stdout.split()
        self.env = {key: value for key, value in os.environ.items() if key not in local_vars}
        self.git('init', '-q')
        self.git('config', 'user.name', 'Hook Test')
        self.git('config', 'user.email', 'hook@example.test')
        (self.root / 'scripts').mkdir()
        (self.root / 'scripts/check-local').write_text('echo checked >> checks.log\nexit 0\n')
        (self.root / '.gitignore').write_text('checks.log\n')
        self.git('add', '.')
        self.git('commit', '-qm', 'fixture')
        self.sha = self.git('rev-parse', 'HEAD').stdout.strip()

    def git(self, *args):
        return subprocess.run(['git', *args], cwd=self.root, env=self.env, text=True, capture_output=True, check=True)

    def hook(self, sha=None):
        line = f'refs/heads/test {sha or self.sha} refs/heads/test {"0" * 40}\n'
        return subprocess.run(['bash', str(ROOT / '.githooks/pre-push')], cwd=self.root, env=self.env, input=line, text=True, capture_output=True)

    def test_success_runs_checks(self):
        result = self.hook()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.root / 'checks.log').read_text(), 'checked\n')

    def test_hook_clears_git_repository_environment(self):
        (self.root / 'scripts/check-local').write_text('test -z "${GIT_DIR:-}"\n')
        self.git('add', '.')
        self.git('commit', '-qm', 'environment fixture')
        sha = self.git('rev-parse', 'HEAD').stdout.strip()
        line = f'refs/heads/test {sha} refs/heads/test {"0" * 40}\n'
        result = subprocess.run(['bash', str(ROOT / '.githooks/pre-push')],
                                cwd=self.root, input=line, text=True, capture_output=True,
                                env={**self.env, 'GIT_DIR': str(self.root / '.git')})
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_failure_blocks_push(self):
        (self.root / 'scripts/check-local').write_text('exit 23\n')
        self.git('add', '.')
        self.git('commit', '-qm', 'fail fixture')
        self.sha = self.git('rev-parse', 'HEAD').stdout.strip()
        self.assertEqual(self.hook().returncode, 23)

    def test_dirty_tracked_files_block_push(self):
        (self.root / 'scripts/check-local').write_text('exit 0\n')
        self.assertNotEqual(self.hook().returncode, 0)
        self.assertFalse((self.root / 'checks.log').exists())

    def test_untracked_source_blocks_push(self):
        (self.root / 'new-source.js').write_text('uncommitted')
        self.assertNotEqual(self.hook().returncode, 0)

    def test_changes_during_checks_block_push(self):
        (self.root / 'scripts/check-local').write_text('echo changed >> .gitignore\n')
        self.git('add', '.')
        self.git('commit', '-qm', 'mutating fixture')
        self.sha = self.git('rev-parse', 'HEAD').stdout.strip()
        self.assertNotEqual(self.hook().returncode, 0)

    def test_local_ci_preserves_all_gates_without_github_actions(self):
        ci_path = ROOT / 'scripts/ci'
        self.assertTrue(ci_path.is_file())
        self.assertTrue(os.access(ci_path, os.X_OK))
        ci = ci_path.read_text()
        for ver in ('"8.2"', '"8.3"', '"8.4"', '"8.5"'):
            self.assertIn(ver, ci)
        self.assertIn('composer_cmd audit', ci)
        self.assertIn('npm audit --audit-level=moderate', ci)
        self.assertIn('npm run test:performance', ci)
        self.assertIn('scripts/verify/score.sh', ci)
        self.assertIn('scripts/release/integration.sh', ci)
        self.assertFalse((ROOT / '.github/workflows').exists())

    def test_non_head_commit_blocks_push(self):
        self.git('commit', '--allow-empty', '-qm', 'second')
        self.assertNotEqual(self.hook().returncode, 0)

    def test_deletion_does_not_run_checks(self):
        self.assertEqual(self.hook('0' * 40).returncode, 0)
        self.assertFalse((self.root / 'checks.log').exists())

    def test_installer_is_idempotent_and_preserves_existing_hooks(self):
        shutil.copy(ROOT / 'scripts/install-hooks', self.root / 'scripts/install-hooks')
        for _ in range(2):
            subprocess.run(['bash', 'scripts/install-hooks'], cwd=self.root, env=self.env, check=True, capture_output=True)
        self.assertEqual(self.git('config', '--get', 'core.hooksPath').stdout.strip(), '.githooks')
        self.git('config', 'core.hooksPath', 'custom-hooks')
        result = subprocess.run(['bash', 'scripts/install-hooks'], cwd=self.root, env=self.env, capture_output=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.git('config', '--get', 'core.hooksPath').stdout.strip(), 'custom-hooks')
