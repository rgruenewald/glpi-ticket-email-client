"""Offline contract tests for the release tooling (stdlib only)."""
import importlib.util
import io
import json
import os
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


class LocalReleasePublisherTest(unittest.TestCase):
    def test_publish_script_exists_and_defaults_to_dry_run(self):
        pub_path = ROOT / "scripts/release/publish.py"
        self.assertTrue(pub_path.is_file())
        self.assertTrue(os.access(pub_path, os.X_OK))

        spec_pub = importlib.util.spec_from_file_location("publish", pub_path)
        if spec_pub is None or spec_pub.loader is None:
            self.fail("Could not load publish.py")
        publish_mod = importlib.util.module_from_spec(spec_pub)
        spec_pub.loader.exec_module(publish_mod)

        notes = publish_mod.extract_notes("2.6.2", ROOT / "CHANGELOG.md")
        self.assertIn("Bug Fixes", notes)

        # Dry run with skip-ci and allow-dirty returns verified assets without publishing
        res = publish_mod.plan_or_publish(
            root=ROOT,
            publish=False,
            skip_ci=True,
            allow_dirty=True,
        )
        self.assertFalse(res["published"])
        version = (ROOT / "VERSION").read_text().strip()
        self.assertEqual(res["version"], version)
        self.assertIn(f"ticketmailer-{version}.tar.gz", res["assets"])
        self.assertIn("ticketmailer.xml", res["assets"])
        self.assertIn("SHA256SUMS", res["assets"])

    def test_no_github_actions_workflows_exist(self):
        self.assertFalse((ROOT / ".github/workflows").exists())


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
        self.assertEqual(meta["max_glpi"], "12.0.99")

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
        for field in ("short", "long"):
            node = catalog.find("description/" + field)
            self.assertTrue(node.findtext("en"))
            self.assertNotIn("lang", node.attrib)
        self.assertEqual(catalog.findtext("license"), "GPL v3+")
        self.assertTrue(catalog.findall("tags/en/tag"))
        version = catalog.find("versions/version")
        self.assertEqual([child.tag for child in version],
                         ["num", "compatibility", "download_url"])
        self.assertEqual(version.findtext("num"), release.check(ROOT)["version"])
        self.assertTrue(version.findtext("download_url").endswith("/v" + release.check(ROOT)["version"] + "/ticketmailer-" + release.check(ROOT)["version"] + ".tar.gz"))
        self.assertEqual(version.findtext("compatibility/min"), "11.0.0")
        self.assertEqual(version.findtext("compatibility/max"), "12.0.99")

    def test_publish_rejects_dirty_working_tree_and_skip_ci(self):
        spec_pub = importlib.util.spec_from_file_location("publish", ROOT / "scripts/release/publish.py")
        if spec_pub is None or spec_pub.loader is None:
            self.fail("Could not load publish.py")
        publish_mod = importlib.util.module_from_spec(spec_pub)
        spec_pub.loader.exec_module(publish_mod)

        subprocess.run(["git", "init", "-q"], cwd=self.root, check=True)
        subprocess.run(["git", "config", "user.name", "Test"], cwd=self.root, check=True)
        subprocess.run(["git", "config", "user.email", "test@example.com"], cwd=self.root, check=True)
        subprocess.run(["git", "add", "."], cwd=self.root, check=True)
        subprocess.run(["git", "commit", "-qm", "initial"], cwd=self.root, check=True)

        is_clean, _ = publish_mod.check_git_status(self.root)
        self.assertTrue(is_clean)
        (self.root / "untracked.txt").write_text("untracked")
        is_clean_dirty, _ = publish_mod.check_git_status(self.root)
        self.assertFalse(is_clean_dirty)
        with self.assertRaises(ValueError):
            publish_mod.plan_or_publish(self.root, publish=True, skip_ci=True, allow_dirty=True)

        (self.root / "untracked.txt").unlink()
        with self.assertRaises(ValueError):
            publish_mod.plan_or_publish(self.root, publish=True, skip_ci=True)


if __name__ == "__main__":
    unittest.main()
