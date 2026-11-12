#!/usr/bin/env python3
"""Check project-owned Markdown links before publishing docs/wiki (no network)."""
from pathlib import Path
import re
import sys
from urllib.parse import unquote, urlsplit

REPO = "/rgruenewald/glpi-ticket-email-client"
LINK = re.compile(r"!?\[[^\]\n]*\]\(([^\s)]+)\)")


def anchors(path):
    """GitHub heading IDs for the simple ATX headings used in these docs."""
    result, counts = set(), {}
    for heading in re.findall(r"^#{1,6}\s+(.+?)\s*#*\s*$", path.read_text(), re.M):
        slug = re.sub(r"[^\w\- ]", "", heading.lower()).replace(" ", "-")
        number = counts.get(slug, 0)
        counts[slug] = number + 1
        result.add(slug if number == 0 else f"{slug}-{number}")
    return result


def check(root):
    wiki = root / "docs/wiki"
    errors, count = [], 0
    for page in sorted(wiki.glob("*.md")):
        content = page.read_text()
        for match in LINK.finditer(content):
            count += 1
            url = match.group(1)
            parsed = urlsplit(url)
            path = unquote(parsed.path)
            target = None
            problem = None
            if parsed.netloc == "github.com" and path.startswith(REPO + "/wiki/"):
                slug = path.removeprefix(REPO + "/wiki/").rstrip("/")
                if slug.endswith(".md") or "/" in slug or not slug:
                    problem = "wiki pages need an extensionless page slug"
                else:
                    target = wiki / (slug + ".md")
            elif parsed.netloc == "raw.githubusercontent.com" and path.startswith("/wiki" + REPO + "/"):
                target = wiki / path.removeprefix("/wiki" + REPO + "/")
            elif parsed.netloc == "github.com" and path.startswith(REPO + "/blob/main/"):
                target = root / path.removeprefix(REPO + "/blob/main/")
            elif not parsed.scheme and not parsed.netloc:
                if not path:
                    target = page
                else:
                    problem = "use an absolute wiki URL, rendered repository URL or raw asset URL"
            if target is not None:
                if not target.is_file():
                    problem = f"missing target: {target.relative_to(root)}"
                elif parsed.fragment and target.suffix == ".md" and unquote(parsed.fragment) not in anchors(target):
                    problem = f"missing heading: {parsed.fragment}"
            if problem:
                line = content[:match.start()].count("\n") + 1
                errors.append(f"{page.relative_to(root)}:{line}: {problem}: {url}")
    if not list(wiki.glob("*.md")):
        errors.append("docs/wiki contains no Markdown pages")
    return count, errors


if __name__ == "__main__":
    count, errors = check(Path(__file__).resolve().parents[2])
    for error in errors:
        print(error, file=sys.stderr)
    print(f"Wiki links: {count} checked, {len(errors)} error(s)")
    sys.exit(bool(errors))
