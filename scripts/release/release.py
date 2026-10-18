#!/usr/bin/env python3
"""Offline, deterministic GLPI package and catalog builder. Python 3.11+."""
import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import re
import subprocess
import tarfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
REPOSITORY = "https://github.com/rgruenewald/glpi-ticket-email-client"
PACKAGE_PATHS = ("setup.php", "hook.php", "inc", "front", "ajax", "templates", "locales", "public", "sql", "LICENSE", "README.md", "SECURITY.md", "composer.json")
SEMVER = r"[0-9]+\.[0-9]+\.[0-9]+"


def check(root=ROOT, tag=None):
    version = (root / "VERSION").read_text().strip()
    if not re.fullmatch(SEMVER, version):
        raise ValueError("Only stable X.Y.Z releases are supported")
    setup = (root / "setup.php").read_text()
    meta = {"version": version}
    for key, constant in (("version", "VERSION"), ("min_glpi", "MIN_GLPI"), ("max_glpi", "MAX_GLPI")):
        match = re.search(r"define\('PLUGIN_TICKETMAILER_" + constant + r"', '([^']+)'\)", setup)
        if not match or not re.fullmatch(SEMVER, match[1]):
            raise ValueError("Missing or invalid plugin constant: " + constant)
        if key == "version" and match[1] != version:
            raise ValueError("VERSION and setup.php disagree")
        meta[key] = match[1]
    if tuple(map(int, meta["min_glpi"].split("."))) > tuple(map(int, meta["max_glpi"].split("."))):
        raise ValueError("Invalid GLPI compatibility range")
    manifest = json.loads((root / ".release-please-manifest.json").read_text())
    if manifest != {".": version}:
        raise ValueError("Release Please manifest and VERSION disagree")
    if tag is not None and tag != "v" + version:
        raise ValueError("Tag must match v" + version)
    composer = root / "composer.json"
    if composer.exists() and any(not (name == "php" or name.startswith("ext-")) for name in json.loads(composer.read_text()).get("require", {})):
        raise ValueError("Runtime Composer dependencies require an explicit packaging design")
    files(root)  # Reject symlinks, missing paths and non-regular runtime content.
    return meta


def files(root):
    result = []
    for name in PACKAGE_PATHS:
        path = root / name
        if not path.exists() or path.is_symlink():
            raise ValueError("Missing or symlinked package path: " + name)
        candidates = [path] if path.is_file() else sorted(path.rglob("*"))
        for item in candidates:
            if item.is_symlink() or not (item.is_dir() or item.is_file()):
                raise ValueError("Unsafe runtime path: " + str(item))
            if item.is_file():
                result.append(item.relative_to(root).as_posix())
    return sorted(result)


def catalog(meta):
    root = ET.Element("root")
    def add(parent, key, value, **attrs):
        element = ET.SubElement(parent, key, attrs)
        element.text = value
        return element
    add(root, "name", "GLPI Ticket Email Client")
    add(root, "key", "ticketmailer")
    add(root, "state", "stable")
    desc = ET.SubElement(root, "description")
    add(desc, "short", "Compose and send ticket emails through GLPI SMTP.", lang="en")
    add(desc, "long", "Ticket email composer with recipients, attachments, an audit trail and timeline integration. Uses the existing GLPI SMTP configuration.", lang="en")
    langs = ET.SubElement(root, "langs")
    for lang in ("en_GB", "de_DE"):
        add(langs, "lang", lang)
    for key, url in (("homepage", REPOSITORY), ("download", REPOSITORY + "/releases"), ("issues", REPOSITORY + "/issues"), ("readme", "https://raw.githubusercontent.com/rgruenewald/glpi-ticket-email-client/main/README.md")):
        add(root, key, url)
    add(ET.SubElement(root, "authors"), "author", "Ronny Gruenewald")
    version = ET.SubElement(ET.SubElement(root, "versions"), "version")
    add(version, "num", meta["version"])
    compatibility = ET.SubElement(version, "compatibility")
    add(compatibility, "min", meta["min_glpi"])
    add(compatibility, "max", meta["max_glpi"])
    add(version, "download_url", REPOSITORY + "/releases/download/v" + meta["version"] + "/ticketmailer-" + meta["version"] + ".tar.gz")
    add(version, "license", "GPL v3+")
    ET.indent(root)
    return ET.tostring(root, encoding="utf-8", xml_declaration=True) + b"\n"


def write_checksums(out):
    names = sorted(p.name for p in out.iterdir() if p.is_file() and p.name != "SHA256SUMS")
    (out / "SHA256SUMS").write_text("".join(hashlib.sha256((out / n).read_bytes()).hexdigest() + "  " + n + "\n" for n in names))


def build(root, out, epoch):
    meta = check(root)
    if epoch < 0:
        raise ValueError("SOURCE_DATE_EPOCH must be non-negative")
    out.mkdir(parents=True, exist_ok=True)
    expected = {"ticketmailer-" + meta["version"] + ".tar.gz", "ticketmailer.xml", "SHA256SUMS"}
    if any(p.name not in expected or p.is_symlink() or not p.is_file() for p in out.iterdir()):
        raise ValueError("Output directory must be empty or contain only this release")
    archive = out / ("ticketmailer-" + meta["version"] + ".tar.gz")
    # USTAR, sorted paths, fixed metadata, no gzip filename or wall-clock timestamp.
    with archive.open("wb") as raw, gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=epoch, compresslevel=9) as compressed:
        with tarfile.open(fileobj=compressed, mode="w", format=tarfile.USTAR_FORMAT) as tar:
            for name in files(root):
                data = (root / name).read_bytes()
                info = tarfile.TarInfo("ticketmailer/" + name)
                info.size = len(data)
                info.mtime = epoch
                info.mode = 0o644
                info.uid = info.gid = 0
                info.uname = info.gname = ""
                tar.addfile(info, io.BytesIO(data))
    (out / "ticketmailer.xml").write_bytes(catalog(meta))
    write_checksums(out)
    verify(root, out)
    return archive


def verify(root, out):
    meta = check(root)
    archive = out / ("ticketmailer-" + meta["version"] + ".tar.gz")
    expected_names = {archive.name, "ticketmailer.xml", "SHA256SUMS"}
    if {p.name for p in out.iterdir()} != expected_names:
        raise ValueError("Unexpected or missing release assets")
    checksums = "".join(hashlib.sha256((out / n).read_bytes()).hexdigest() + "  " + n + "\n" for n in sorted(expected_names - {"SHA256SUMS"}))
    if (out / "SHA256SUMS").read_text() != checksums:
        raise ValueError("Release checksum mismatch")
    if (out / "ticketmailer.xml").read_bytes() != catalog(meta):
        raise ValueError("Catalog differs from current release metadata")
    expected = {"ticketmailer/" + n: root / n for n in files(root)}
    with tarfile.open(archive, "r:gz") as tar:
        members = tar.getmembers()
        if [m.name for m in members] != sorted(expected):
            raise ValueError("Unexpected, duplicate, unsorted or missing archive members")
        for member in members:
            if not member.isfile() or member.mode != 0o644 or member.uid or member.gid:
                raise ValueError("Unsafe archive entry")
            if tar.extractfile(member).read() != expected[member.name].read_bytes():
                raise ValueError("Archive content differs: " + member.name)
    return meta


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=("check", "build", "verify"))
    parser.add_argument("--tag")
    parser.add_argument("--out", type=Path, default=ROOT / "dist")
    args = parser.parse_args()
    try:
        meta = check(ROOT, args.tag)
        if args.command == "build":
            epoch = os.environ.get("SOURCE_DATE_EPOCH")
            if epoch is None:
                epoch = subprocess.check_output(["git", "show", "-s", "--format=%ct", "HEAD"], cwd=ROOT, text=True).strip()
            build(ROOT, args.out, int(epoch))
        elif args.command == "verify":
            verify(ROOT, args.out)
        print(json.dumps(meta, sort_keys=True))
    except (ValueError, OSError, subprocess.CalledProcessError, tarfile.TarError) as error:
        parser.exit(1, str(error) + "\n")


if __name__ == "__main__":
    main()
