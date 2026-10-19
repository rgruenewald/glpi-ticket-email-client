"""Offline contract tests for the release tooling (stdlib only)."""
import importlib.util
import io
import json
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("release", ROOT / "scripts/release/release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ReleaseTest(unittest.TestCase):
    def test_release_please_updates_canonical_version_file(self):
        config = json.loads((ROOT / "release-please-config.json").read_text())
        package = config["packages"]["."]
        self.assertEqual(package["release-type"], "simple")
        self.assertEqual(package.get("version-file", "version.txt"), "VERSION")
        self.assertTrue((ROOT / package["version-file"]).is_file())

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name) / "source"
        self.root.mkdir()
        for name in release.PACKAGE_PATHS + ("VERSION", ".release-please-manifest.json", "release-please-config.json"):
            src = ROOT / name
            if src.is_dir():
                shutil.copytree(src, self.root / name)
            else:
                shutil.copyfile(src, self.root / name)
        self.out = Path(self.tmp.name) / "out"

    def tearDown(self):
        self.tmp.cleanup()

    def test_checked_in_versions_agree(self):
        meta = release.check(self.root)
        self.assertEqual(meta["version"], (ROOT / "VERSION").read_text().strip())
        self.assertEqual(meta["min_glpi"], "11.0.0")

    def test_rejects_version_drift_and_wrong_tag(self):
        (self.root / "VERSION").write_text("9.9.9\n")
        with self.assertRaises(ValueError):
            release.check(self.root)
        shutil.copyfile(ROOT / "VERSION", self.root / "VERSION")
        with self.assertRaises(ValueError):
            release.check(self.root, "v0.0.1")

    def test_build_is_byte_reproducible_and_validates(self):
        first = release.build(self.root, self.out, 1700000000)
        before = {p.name: p.read_bytes() for p in self.out.iterdir()}
        second = release.build(self.root, self.out, 1700000000)
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.out.iterdir()})
        self.assertEqual(first, second)
        release.verify(self.root, self.out)
        with tarfile.open(first) as archive:
            names = archive.getnames()
            self.assertIn("ticketmailer/setup.php", names)
            self.assertFalse(any("tests/" in n or "vendor/" in n or "/.git" in n for n in names))
            self.assertTrue(all(m.uid == m.gid == 0 and m.mode == 0o644 for m in archive.getmembers()))

    def test_rejects_symlink_and_missing_runtime_file(self):
        target = self.root / "inc/unsafe.php"
        target.symlink_to("/etc/passwd")
        with self.assertRaises(ValueError):
            release.build(self.root, self.out, 1700000000)
        target.unlink()
        (self.root / "hook.php").unlink()
        with self.assertRaises(ValueError):
            release.build(self.root, self.out, 1700000000)

    def test_rejects_changed_archive(self):
        archive = release.build(self.root, self.out, 1700000000)
        with archive.open("ab") as stream:
            stream.write(b"changed")
        with self.assertRaises(ValueError):
            release.verify(self.root, self.out)

    def test_rejects_unexpected_archive_member_even_with_new_checksum(self):
        archive = release.build(self.root, self.out, 1700000000)
        with tarfile.open(archive, "w:gz") as stream:
            member = tarfile.TarInfo("../unsafe")
            member.size = 4
            stream.addfile(member, io.BytesIO(b"test"))
        release.write_checksums(self.out)
        with self.assertRaises(ValueError):
            release.verify(self.root, self.out)

    def test_catalog_contains_exact_version_and_download(self):
        release.build(self.root, self.out, 1700000000)
        import xml.etree.ElementTree as ET
        catalog = ET.parse(self.out / "ticketmailer.xml").getroot()
        version = catalog.find("versions/version")
        self.assertEqual(version.findtext("num"), release.check(ROOT)["version"])
        self.assertTrue(version.findtext("download_url").endswith("/v" + release.check(ROOT)["version"] + "/ticketmailer-" + release.check(ROOT)["version"] + ".tar.gz"))
        self.assertEqual(version.findtext("compatibility/min"), "11.0.0")
        self.assertEqual(version.findtext("compatibility/max"), "11.99.99")

    def test_workflows_pin_actions_and_do_not_publish_on_pr(self):
        import re
        for path in (ROOT / ".github/workflows").glob("*.yml"):
            if path.name not in {"ci.yml", "release.yml"}:
                continue
            for value in re.findall(r"uses: ([^\s]+)", path.read_text()):
                if not value.startswith("./"):
                    self.assertRegex(value, r"@[0-9a-f]{40}$")
        workflow = (ROOT / ".github/workflows/release.yml").read_text()
        self.assertNotIn("pull_request_target", workflow)
        self.assertIn("release_created", workflow)
        self.assertIn("--draft=false", workflow)


if __name__ == "__main__":
    unittest.main()
