#!/usr/bin/env python3
"""Build the directory catalog only from public, checksum-verified release assets."""
import argparse
import copy
import hashlib
import json
from pathlib import Path
import re
import subprocess
import tempfile
import xml.etree.ElementTree as ET

REPO = "rgruenewald/glpi-ticket-email-client"
BASE = "https://github.com/" + REPO
SEMVER = r"[0-9]+.[0-9]+.[0-9]+"
ROOT = Path(__file__).resolve().parents[2]


def assemble(releases, assets):
    """assets(tag) returns the three published asset bodies; never use working-tree metadata."""
    entries = {}
    catalog = None
    for release in releases:
        if release["draft"] or release["prerelease"]:
            continue
        tag = release["tag_name"]
        if not re.fullmatch("v" + SEMVER, tag):
            continue
        version = tag[1:]
        archive = "ticketmailer-" + version + ".tar.gz"
        bodies = assets(tag)
        checksums = {}
        for line in bodies["SHA256SUMS"].decode().splitlines():
            match = re.fullmatch(r"([a-f0-9]{64})  ([^/s]+)", line)
            if not match or match[2] in checksums:
                raise ValueError("Invalid release checksums: " + tag)
            checksums[match[2]] = match[1]
        for name in (archive, "ticketmailer.xml"):
            if hashlib.sha256(bodies[name]).hexdigest() != checksums.get(name):
                raise ValueError("Release checksum mismatch: " + tag)
        root = ET.fromstring(bodies["ticketmailer.xml"])
        versions = root.findall("versions/version")
        if root.tag != "root" or root.findtext("key") != "ticketmailer" or len(versions) != 1:
            raise ValueError("Unexpected release catalog: " + tag)
        entry = versions[0]
        if entry.findtext("num") != version or entry.findtext("download_url") != BASE + "/releases/download/" + tag + "/" + archive:
            raise ValueError("Release identity mismatch: " + tag)
        bounds = [entry.findtext("compatibility/" + key, "") for key in ("min", "max")]
        if not all(re.fullmatch(SEMVER, value) for value in bounds) or tuple(map(int, bounds[0].split("."))) > tuple(map(int, bounds[1].split("."))):
            raise ValueError("Invalid release compatibility: " + tag)
        license_text = root.findtext("license") or entry.findtext("license")
        if not license_text or version in entries:
            raise ValueError("Missing license or duplicate version: " + tag)
        entries[version] = copy.deepcopy(entry)
        for license_node in entries[version].findall("license"):
            entries[version].remove(license_node)
        # GitHub returns newest releases first. Preserve their published plugin metadata.
        if catalog is None:
            catalog = copy.deepcopy(root)
            if catalog.find("license") is None:
                ET.SubElement(catalog, "license").text = license_text
            if catalog.find("tags") is None:
                tags = ET.SubElement(ET.SubElement(catalog, "tags"), "en")
                for tag_text in ("email", "ticket", "SMTP"):
                    ET.SubElement(tags, "tag").text = tag_text
    if catalog is None:
        raise ValueError("No published stable releases")
    # Older immutable releases used a language attribute. The Directory expects
    # language child elements; normalize only the copied catalog presentation.
    for field in ("short", "long"):
        description = catalog.find("description/" + field)
        if description is None:
            raise ValueError("Missing catalog description: " + field)
        if not list(description) and description.get("lang") and description.text:
            language = description.attrib.pop("lang")
            content = description.text
            description.text = None
            ET.SubElement(description, language).text = content
        if not any(child.text and child.text.strip() for child in description):
            raise ValueError("Missing translated catalog description: " + field)
    versions = catalog.find("versions")
    versions.clear()
    for version in sorted(entries, key=lambda v: tuple(map(int, v.split("."))), reverse=True):
        versions.append(entries[version])
    ET.indent(catalog)
    return ET.tostring(catalog, encoding="utf-8", xml_declaration=True) + b"\n"


def refresh(output):
    pages = subprocess.check_output(["gh", "api", "--paginate", "--slurp", "repos/" + REPO + "/releases"], text=True)
    releases = [release for page in json.loads(pages) for release in page]

    def download(tag):
        with tempfile.TemporaryDirectory() as directory:
            names = ["SHA256SUMS", "ticketmailer.xml", "ticketmailer-" + tag[1:] + ".tar.gz"]
            command = ["gh", "release", "download", tag, "--repo", REPO, "--dir", directory]
            for name in names:
                command.extend(["--pattern", name])
            subprocess.run(command, check=True)
            return {name: (Path(directory) / name).read_bytes() for name in names}

    content = assemble(releases, download)
    # No partial catalog if any release is missing or invalid.
    temporary = output.with_suffix(output.suffix + ".tmp")
    temporary.write_bytes(content)
    temporary.replace(output)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=ROOT / "ticketmailer.xml")
    args = parser.parse_args()
    refresh(args.output)
