"""Development schema fingerprints and local state safety."""
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('dev_schema', ROOT / 'scripts/dev-schema.py')
schema = importlib.util.module_from_spec(spec)
spec.loader.exec_module(schema)


class DevSchemaTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        for name in ('hook.php', 'inc/hook.class.php', 'sql/install.sql'):
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / name, target)

    def test_fingerprint_detects_changed_added_and_removed_migrations(self):
        before = schema.fingerprint(self.root)
        migration = self.root / 'sql/update-test.sql'
        migration.write_text('-- synthetic migration')
        added = schema.fingerprint(self.root)
        self.assertNotEqual(before, added)
        migration.write_text('-- edited synthetic migration')
        self.assertNotEqual(added, schema.fingerprint(self.root))
        migration.unlink()
        self.assertEqual(before, schema.fingerprint(self.root))
        (self.root / 'inc/hook.class.php').write_text('// new migration dispatch')
        self.assertNotEqual(before, schema.fingerprint(self.root))

    def test_non_schema_source_does_not_trigger_migration(self):
        before = schema.fingerprint(self.root)
        (self.root / 'setup.php').write_text('// unchanged product version')
        (self.root / 'feature.php').write_text('// unrelated change')
        self.assertEqual(before, schema.fingerprint(self.root))

    def test_state_is_private_atomic_and_shared_across_worktrees(self):
        with patch.object(Path, 'home', return_value=self.root):
            path = schema.marker_path('db-volume:created:table-created')
            self.assertEqual(path, schema.marker_path('db-volume:created:table-created'))
            self.assertNotEqual(path, schema.marker_path('db-volume:recreated:table-created'))
            value = schema.fingerprint(self.root)
            schema.record(path, value)
            self.assertEqual(json.loads(path.read_text()), {'fingerprint': value, 'migrations': {}})
            self.assertEqual(path.stat().st_mode & 0o777, 0o600)
            self.assertEqual(path.parent.stat().st_mode & 0o777, 0o700)
            self.assertEqual(list(path.parent.iterdir()), [path])

    def test_missing_or_invalid_state_requires_reapplication(self):
        env = dict(os.environ, HOME=str(self.root))
        cmd = ['python3', str(ROOT / 'scripts/dev-schema.py'), 'read', '--instance', 'test']
        self.assertEqual(subprocess.check_output(cmd, env=env, text=True).strip(), '')
        with patch.object(Path, 'home', return_value=self.root):
            path = schema.marker_path('test')
        path.parent.mkdir(parents=True)
        path.write_text('not json')
        self.assertEqual(subprocess.check_output(cmd, env=env, text=True).strip(), '')

    def test_missing_or_symlinked_schema_inputs_are_rejected(self):
        install = self.root / 'sql/install.sql'
        install.unlink()
        with self.assertRaises(ValueError):
            schema.fingerprint(self.root)
        install.symlink_to(ROOT / 'sql/install.sql')
        with self.assertRaises(ValueError):
            schema.fingerprint(self.root)
