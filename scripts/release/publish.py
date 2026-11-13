#!/usr/bin/env python3
"""Publish ticketmailer release assets to GitHub. Python 3.11+.

Strictly release-gated: default is dry-run mode (offline verification).
Actual publishing to GitHub requires the explicit --publish flag.
"""
import argparse
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("release", ROOT / "scripts/release/release.py")
if spec is None or spec.loader is None:
    raise ImportError("Could not load release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


def extract_notes(version: str, changelog_path: Path = ROOT / "CHANGELOG.md") -> str:
    """Extract release notes for version from CHANGELOG.md."""
    if not changelog_path.is_file():
        return f"Release {version}"
    content = changelog_path.read_text(encoding="utf-8")
    escaped = re.escape(version)
    pattern = rf"(?m)^## \[?{escaped}\]?(?:[^\n]*)?\n(.*?)(?=\n^## |\Z)"
    match = re.search(pattern, content, re.DOTALL)
    if match:
        notes = match.group(1).strip()
        if notes:
            return notes
    return f"Release {version}"


def check_git_status(root: Path = ROOT) -> tuple[bool, str]:
    """Check if repository working tree is clean."""
    diff = subprocess.run(
        ["git", "diff", "--quiet", "HEAD", "--"],
        cwd=root,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    untracked = subprocess.run(
        ["git", "ls-files", "--others", "--exclude-standard"],
        cwd=root,
        text=True,
        capture_output=True,
    )
    if diff.returncode != 0 or untracked.stdout.strip():
        details = []
        if diff.returncode != 0:
            details.append("uncommitted tracked changes")
        if untracked.stdout.strip():
            details.append(f"untracked files: {len(untracked.stdout.strip().splitlines())}")
        return False, "; ".join(details)
    if untracked.returncode != 0:
        return False, "unable to enumerate untracked files"
    return True, "clean"


def git_head(root: Path) -> str:
    return subprocess.check_output(
        ["git", "rev-parse", "--verify", "HEAD"], cwd=root, text=True
    ).strip()


def verify_release_candidate(root: Path, expected_head: str) -> None:
    clean, detail = check_git_status(root)
    if not clean or git_head(root) != expected_head:
        raise ValueError(f"Release candidate changed during verification ({detail}); refusing publication.")


def check_gh_auth() -> tuple[bool, str]:
    """Check if GitHub CLI is installed and authenticated."""
    if not shutil_which("gh"):
        return False, "gh CLI not found on PATH"
    proc = subprocess.run(
        ["gh", "auth", "status"],
        text=True,
        capture_output=True,
    )
    if proc.returncode != 0:
        return False, proc.stderr.strip() or "gh not authenticated"
    return True, "authenticated"


def shutil_which(cmd: str) -> bool:
    import shutil
    return shutil.which(cmd) is not None


def run_local_ci(mode: str = "all", root: Path = ROOT) -> None:
    """Run local CI gate before release."""
    ci_script = root / "scripts/ci"
    if not ci_script.is_file():
        raise RuntimeError(f"CI script not found at {ci_script}")
    print(f"Running local CI gate ({mode})...")
    proc = subprocess.run(["bash", str(ci_script), mode], cwd=root)
    if proc.returncode != 0:
        raise RuntimeError(f"Local CI gate failed with exit code {proc.returncode}")


def plan_or_publish(
    root: Path = ROOT,
    out: Path = ROOT / "dist",
    publish: bool = False,
    skip_ci: bool = False,
    ci_mode: str = "all",
    allow_dirty: bool = False,
    tag: str | None = None,
) -> dict:
    if publish and ci_mode != "all":
        raise ValueError("Publication requires --ci-mode all; quick omits packaged integration.")
    meta = release.check(root, tag)
    version = meta["version"]
    release_tag = tag or f"v{version}"

    # 1. Check Git status. A real publication must always come from a
    # clean, committed checkout; --allow-dirty is only for dry-run tests.
    candidate_head = git_head(root) if publish else None
    is_clean, git_msg = check_git_status(root)
    if not is_clean and (publish or not allow_dirty):
        raise ValueError(f"Git working tree is not clean ({git_msg}). Refusing release.")
    if publish and skip_ci:
        raise ValueError("--skip-ci is only allowed for dry-run testing; refusing publication.")

    # 2. Check GitHub CLI prerequisites
    gh_ok, gh_msg = check_gh_auth()
    if not gh_ok and publish:
        raise RuntimeError(f"GitHub CLI required for publishing: {gh_msg}")

    # 3. Local CI verification
    if not skip_ci:
        run_local_ci(ci_mode, root)
    if publish:
        verify_release_candidate(root, candidate_head)

    # 4. Build and verify release assets
    epoch_val = os.environ.get("SOURCE_DATE_EPOCH")
    if epoch_val is None:
        epoch_val = subprocess.check_output(
            ["git", "show", "-s", "--format=%ct", "HEAD"], cwd=root, text=True
        ).strip()
    archive = release.build(root, out, int(epoch_val))
    release.verify(root, out)

    expected_assets = [
        out / f"ticketmailer-{version}.tar.gz",
        out / "ticketmailer.xml",
        out / "SHA256SUMS",
    ]
    for asset in expected_assets:
        if not asset.is_file():
            raise FileNotFoundError(f"Missing expected asset: {asset}")

    notes = extract_notes(version, root / "CHANGELOG.md")
    checksums = (out / "SHA256SUMS").read_text()

    result = {
        "version": version,
        "tag": release_tag,
        "assets": [p.name for p in expected_assets],
        "checksums": checksums,
        "notes": notes,
        "git_status": git_msg,
        "gh_auth": gh_msg,
        "published": False,
    }

    if not publish:
        print("=== RELEASE DRY-RUN (OFFLINE / SAFE) ===")
        print(f"Version:      {version}")
        print(f"Tag:          {release_tag}")
        print(f"Git Status:   {git_msg}")
        print(f"GitHub Auth:  {gh_msg}")
        print(f"Assets in {out}:")
        for asset in expected_assets:
            print(f"  - {asset.name} ({asset.stat().st_size} bytes)")
        print("\nChecksums:")
        print(checksums.strip())
        print("\nRelease Notes Preview:")
        print("----------------------------------------")
        print(notes[:400] + ("..." if len(notes) > 400 else ""))
        print("----------------------------------------")
        print(f"\nCommand that would be executed to publish:")
        print(f"  gh release create \"{release_tag}\" \\\n"
              f"    \"{out / f'ticketmailer-{version}.tar.gz'}\" \\\n"
              f"    \"{out / 'ticketmailer.xml'}\" \\\n"
              f"    \"{out / 'SHA256SUMS'}\" \\\n"
              f"    --title \"{release_tag}\" \\\n"
              f"    --notes <CHANGELOG_NOTES>")
        print("\nDRY-RUN COMPLETE: No changes were made to GitHub.")
        print("To publish, re-run with explicit --publish flag.")
        return result

    # Publishing mode
    print(f"Publishing release {release_tag} to GitHub...")
    notes_file = out / f"RELEASE_NOTES_{version}.tmp"
    notes_file.write_text(notes, encoding="utf-8")
    try:
        cmd = [
            "gh",
            "release",
            "create",
            release_tag,
            str(out / f"ticketmailer-{version}.tar.gz"),
            str(out / "ticketmailer.xml"),
            str(out / "SHA256SUMS"),
            "--title",
            release_tag,
            "--notes-file",
            str(notes_file),
        ]
        verify_release_candidate(root, candidate_head)
        subprocess.run(cmd, cwd=root, check=True)
        result["published"] = True
        print(f"Successfully published release {release_tag} to GitHub!")
    finally:
        if notes_file.exists():
            notes_file.unlink()

    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--publish",
        action="store_true",
        help="Perform actual release publication to GitHub (default is dry-run)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        default=True,
        help="Simulate release preparation, assets build and verification without publishing",
    )
    parser.add_argument(
        "--skip-ci",
        action="store_true",
        help="Skip running local CI gates prior to release build",
    )
    parser.add_argument(
        "--ci-mode",
        choices=["quick", "all"],
        default="all",
        help="Local CI mode (default: all; quick is permitted only for dry-run)",
    )
    parser.add_argument(
        "--allow-dirty",
        action="store_true",
        help="Allow uncommitted changes (only for local testing/dry-run)",
    )
    parser.add_argument("--tag", help="Explicit tag (defaults to v<VERSION>)")
    parser.add_argument("--out", type=Path, default=ROOT / "dist")
    args = parser.parse_args()

    # If --publish is given, disable dry-run
    publish_mode = args.publish

    try:
        res = plan_or_publish(
            root=ROOT,
            out=args.out,
            publish=publish_mode,
            skip_ci=args.skip_ci,
            ci_mode=args.ci_mode,
            allow_dirty=args.allow_dirty,
            tag=args.tag,
        )
        if not publish_mode:
            sys.exit(0)
    except Exception as error:
        print(f"Error: {error}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
