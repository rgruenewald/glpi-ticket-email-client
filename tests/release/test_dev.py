"""Interactive lifecycle checks using a fake Docker, never the shared instance."""
import os
from pathlib import Path
import signal
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[2]

class DevTest(unittest.TestCase):
    def test_termination_stops_all_services_without_removing_volumes(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            docker = root / "docker"

            # Test the lifecycle separately from host-specific ACL preparation.
            source = (ROOT / "scripts/dev").read_text()
            source = source.replace("    compose build glpi test", "    :")
            source = source.replace('    "$0" prepare', "    :")
            source = source.replace("    source scripts/dev-plugin.sh", "    :")
            source = source.replace("    plugin_preflight", "    :")
            source = source.replace("    plugin_apply", "    :")
            script = root / "dev"
            script.write_text(source)
            docker.write_text("#!/bin/sh\necho \"$*\" >> \"$CALLS\"\ncase \"$*\" in\n  *--follow*) exec sleep 30;;\nesac\n")
            docker.chmod(0o755)
            script.chmod(0o755)
            # The launcher lock lives under HOME, even with a fake Docker.
            # Never contend with a real interactive session or touch host state.
            env = dict(os.environ, HOME=tmp,
                       PATH=tmp + os.pathsep + os.environ["PATH"], CALLS=str(root / "calls"))
            proc = subprocess.Popen([str(script), "interactive"], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
            try:
                for _ in range(100):
                    if (root / "calls").exists() and "--follow" in (root / "calls").read_text():
                        break
                    if proc.poll() is not None:
                        _, err = proc.communicate()
                        self.fail("Launcher exited before log follower started: " + err.decode())
                    time.sleep(0.02)
                else:
                    proc.kill()
                    _, err = proc.communicate()
                    self.fail("Log follower did not start: " + err.decode())
                proc.send_signal(signal.SIGTERM)
                _, err = proc.communicate(timeout=10)
                self.assertEqual(proc.returncode, 143, err.decode())
                calls = (root / "calls").read_text()
                self.assertIn("stop glpi db mailpit", calls)
                self.assertNotIn("down", calls)
                self.assertIn("compose.interactive.yml", calls)
                self.assertTrue((root / ".local/state/ticketmailer-schema/interactive.lock").is_file())
            finally:
                if proc.poll() is None:
                    proc.kill()
                proc.communicate()
