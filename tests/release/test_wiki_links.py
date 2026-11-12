"""Regression checks for the GitHub Wiki publication link gate."""
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("wiki_links", ROOT / "scripts/verify/wiki_links.py")
wiki_links = importlib.util.module_from_spec(spec)
spec.loader.exec_module(wiki_links)
WIKI = "https://github.com/rgruenewald/glpi-ticket-email-client/wiki/"
RAW = "https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/"
BLOB = "https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/"


class WikiLinksTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.wiki = self.root / "docs/wiki"
        self.wiki.mkdir(parents=True)

    def page(self, text):
        (self.wiki / "Home.md").write_text(text)
        return wiki_links.check(self.root)[1]

    def test_valid_page_asset_repository_and_unicode_anchors(self):
        (self.wiki / "Guide.md").write_text("# Einführung\n## Details\n## Details\n")
        (self.wiki / "images").mkdir()
        (self.wiki / "images/demo.png").write_bytes(b"image")
        (self.root / "SECURITY.md").write_text("# Security\n")
        self.assertEqual([], self.page(
            f"[Guide]({WIKI}Guide#einf%C3%BChrung)\n"
            f"[Duplicate]({WIKI}Guide#details-1)\n"
            f"![Demo]({RAW}images/demo.png)\n"
            f"[Policy]({BLOB}SECURITY.md#security)\n"
            "[Local heading](#home)\n# Home\n"))

    def test_relative_repository_escape_is_rejected(self):
        for url in ("../INSTALLATION.md", "../../SECURITY.md", "Guide.md", "images/demo.png"):
            with self.subTest(url=url):
                self.assertEqual(1, len(self.page(f"[Link]({url})")))

    def test_wiki_markdown_extension_and_missing_page_are_rejected(self):
        for slug in ("Home.md", "Missing", "nested/Page"):
            with self.subTest(slug=slug):
                self.assertEqual(1, len(self.page(f"[Link]({WIKI}{slug})")))

    def test_missing_asset_source_and_heading_are_rejected(self):
        for url in (RAW + "missing.png", BLOB + "missing.md", WIKI + "Home#missing"):
            with self.subTest(url=url):
                self.assertEqual(1, len(self.page(f"[Link]({url})")))

    def test_empty_wiki_fails(self):
        self.assertTrue(wiki_links.check(self.root)[1])

    def test_repository_wiki_passes(self):
        count, errors = wiki_links.check(ROOT)
        self.assertGreater(count, 100)
        self.assertEqual([], errors)


if __name__ == "__main__":
    unittest.main()
