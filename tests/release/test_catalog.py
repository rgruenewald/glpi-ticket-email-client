"""Offline coverage of the published-release-only directory catalog."""
import copy
import hashlib
import importlib.util
from pathlib import Path
import unittest
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("catalog", ROOT / "scripts/release/catalog.py")
catalog = importlib.util.module_from_spec(spec)
spec.loader.exec_module(catalog)


class CatalogTest(unittest.TestCase):
    def setUp(self):
        self.releases = [dict(tag_name="v2.5.0", draft=False, prerelease=False),
                         dict(tag_name="v2.4.0", draft=False, prerelease=False),
                         dict(tag_name="v2.3.2", draft=False, prerelease=False)]
        self.bodies = {}
        for release in self.releases:
            tag = release["tag_name"]
            version = tag[1:]
            root = ET.parse(ROOT / "ticketmailer.xml").getroot()
            versions = root.find("versions")
            entry = copy.deepcopy(versions[0])
            entry.find("num").text = version
            # Published compatibility is fixture data, not the current catalog head.
            entry.find("compatibility/min").text = "11.0.0"
            entry.find("compatibility/max").text = "11.0.99" if version == "2.5.0" else "11.99.99"
            entry.find("download_url").text = catalog.BASE + "/releases/download/" + tag + "/ticketmailer-" + version + ".tar.gz"
            versions.clear()
            versions.append(entry)
            self.bodies[tag] = {"ticketmailer.xml": ET.tostring(root),
                                "ticketmailer-" + version + ".tar.gz": b"package fixture"}
            self.rehash(tag)

    def rehash(self, tag):
        bodies = self.bodies[tag]
        bodies["SHA256SUMS"] = "".join(hashlib.sha256(data).hexdigest() + "  " + name + "\n"
                                      for name, data in bodies.items() if name != "SHA256SUMS").encode()

    def build(self):
        return catalog.assemble(self.releases, self.bodies.__getitem__)

    def test_directory_descriptions_accept_legacy_and_language_children(self):
        for legacy in (True, False):
            with self.subTest(legacy=legacy):
                for tag, bodies in self.bodies.items():
                    root = ET.fromstring(bodies["ticketmailer.xml"])
                    for field in ("short", "long"):
                        node = root.find("description/" + field)
                        node.clear()
                        if legacy:
                            node.set("lang", "en")
                            node.text = field + " description"
                        else:
                            ET.SubElement(node, "en").text = field + " description"
                            ET.SubElement(node, "de").text = "Deutsche Beschreibung"
                    bodies["ticketmailer.xml"] = ET.tostring(root)
                    self.rehash(tag)
                original = copy.deepcopy(self.bodies)
                result = ET.fromstring(self.build())
                for field in ("short", "long"):
                    node = result.find("description/" + field)
                    self.assertEqual(node.findtext("en"), field + " description")
                    self.assertNotIn("lang", node.attrib)
                    if not legacy:
                        self.assertEqual(node.findtext("de"), "Deutsche Beschreibung")
                self.assertEqual(self.bodies, original)

    def test_directory_license_and_required_tags(self):
        for legacy in (True, False):
            with self.subTest(legacy=legacy):
                for tag, bodies in self.bodies.items():
                    root = ET.fromstring(bodies["ticketmailer.xml"])
                    for node in root.findall("license"):
                        root.remove(node)
                    entry = root.find("versions/version")
                    for node in entry.findall("license"):
                        entry.remove(node)
                    ET.SubElement(entry if legacy else root, "license").text = "GPL v3+"
                    bodies["ticketmailer.xml"] = ET.tostring(root)
                    self.rehash(tag)
                result = ET.fromstring(self.build())
                self.assertEqual(result.findtext("license"), "GPL v3+")
                self.assertTrue(result.findall("tags/en/tag"))
                for entry in result.findall("versions/version"):
                    self.assertEqual([node.tag for node in entry],
                                     ["num", "compatibility", "download_url"])

    def test_history_is_sorted_and_preserves_published_compatibility(self):
        self.releases.reverse()
        versions = ET.fromstring(self.build()).findall("versions/version")
        self.assertEqual([v.findtext("num") for v in versions], ["2.5.0", "2.4.0", "2.3.2"])
        self.assertEqual([v.findtext("compatibility/max") for v in versions],
                         ["11.0.99", "11.99.99", "11.99.99"])

    def test_drafts_prereleases_and_nonstable_tags_are_not_downloaded(self):
        self.releases += [dict(tag_name="v9.0.0", draft=True, prerelease=False),
                          dict(tag_name="v8.0.0", draft=False, prerelease=True),
                          dict(tag_name="v7.0.0-rc1", draft=False, prerelease=False)]
        self.assertEqual(len(ET.fromstring(self.build()).findall("versions/version")), 3)

    def test_tampered_xml_and_archive_are_rejected(self):
        for name in ("ticketmailer.xml", "ticketmailer-2.4.0.tar.gz"):
            with self.subTest(name=name):
                original = self.bodies["v2.4.0"][name]
                self.bodies["v2.4.0"][name] += b"tampered"
                with self.assertRaises(ValueError):
                    self.build()
                self.bodies["v2.4.0"][name] = original

    def test_wrong_version_download_and_invalid_bounds_are_rejected(self):
        original = self.bodies["v2.4.0"]["ticketmailer.xml"]
        for path, value in (("num", "9.0.0"), ("download_url", "https://example.com/evil"),
                            ("compatibility/max", "10.0.0")):
            with self.subTest(path=path):
                root = ET.fromstring(original)
                root.find("versions/version/" + path).text = value
                self.bodies["v2.4.0"]["ticketmailer.xml"] = ET.tostring(root)
                self.rehash("v2.4.0")
                with self.assertRaises(ValueError):
                    self.build()

    def test_missing_assets_duplicate_versions_and_empty_history_fail(self):
        original = self.bodies["v2.4.0"].pop("ticketmailer-2.4.0.tar.gz")
        with self.assertRaises(KeyError):
            self.build()
        with self.assertRaises(ValueError):
            catalog.assemble([], self.bodies.__getitem__)
        self.bodies["v2.4.0"]["ticketmailer-2.4.0.tar.gz"] = original
        self.releases = [self.releases[1], self.releases[1]]
        with self.assertRaises(ValueError):
            self.build()

    def test_checked_in_catalog_is_not_part_of_the_install_package(self):
        spec = importlib.util.spec_from_file_location("release", ROOT / "scripts/release/release.py")
        release = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(release)
        self.assertNotIn("ticketmailer.xml", release.files(ROOT))

    def test_workflows_gate_refresh_and_notification(self):
        refresh = (ROOT / ".github/workflows/catalog.yml").read_text()
        notify = (ROOT / ".github/workflows/catalog-notify.yml").read_text()
        workflow = (ROOT / ".github/workflows/release.yml").read_text()
        self.assertIn("if: needs.release.outputs.published == 'true'", workflow)
        self.assertIn("gh pr create", refresh)
        self.assertNotIn("--force", refresh)
        self.assertNotIn("pull_request:", refresh + notify)
        self.assertIn("paths: [ticketmailer.xml]", notify)
        self.assertIn("vars.GLPI_CATALOG_ENABLED == 'true'", notify)
        self.assertIn("github.ref == 'refs/heads/main'", notify)
        self.assertIn("--fail-with-body", notify)
        self.assertNotIn("--retry", notify)


if __name__ == "__main__":
    unittest.main()
