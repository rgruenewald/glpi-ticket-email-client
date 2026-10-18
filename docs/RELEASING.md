# Release management

## Contract

Short-lived branches and pull requests target main. Squash PRs using Conventional
Commit titles: fix for patches, feat for minor versions, and a breaking-change
footer or ! for major versions. Review the release PR's changelog, compatibility,
migration notes and required checks before merging. No automatic merge.

Release Please maintains VERSION, setup.php, CHANGELOG.md and its manifest together.
The initial development baseline is 2.3.1 at commit
3c940f2db6c710dd556a6871eb08439366634187. There were no remote tags or releases
when this process was introduced. The baseline is not a historical published
release. The next version follows new Conventional Commits. Never invent old tags.
The bootstrap SHA is ignored after the first managed release.

Runtime metadata currently advertises GLPI 11.0.0 through 11.99.99. Integration
tests cover GLPI 11.0.8 / PHP 8.5; unit CI covers PHP 8.2–8.5. This does not prove
all future GLPI 11 releases or GLPI 10 compatible. Review the advertised range
before marketplace submission; changing compatibility is a separate product decision.

## One-time GitHub setup

1. Install a dedicated GitHub App on this repository only, with Contents,
   Pull requests and Issues read/write. No administration, workflows or branch
   bypass permission. Set repository variable RELEASE_APP_ID and Actions secret
   RELEASE_APP_PRIVATE_KEY. Enter the key in GitHub's secret UI or secure stdin,
   never chat, command-line arguments, source files or logs.
2. The App token makes release PRs trigger normal CI. PRs created with the standard
   GITHUB_TOKEN do not normally trigger other workflows. Do not silently substitute
   a PAT or bypass checks. Missing App configuration fails the release job closed.
3. Protect main with a branch ruleset requiring PRs and the exact status check
   Required checks, blocking force pushes and deletion. Use zero required human
   approvals for a solo maintainer. Require an up-to-date branch. Configure squash
   merging with PR title as the commit title; disable merge and rebase merges only
   after checking existing branch workflows. Do not remove unrelated protection.
4. Protect version tags against update and deletion, allowing the release App to
   create tags. Do not prohibit the App's initial tag creation.
5. Existing PR review protection is already enabled. Make new checks required only
   after the workflow has run successfully, to avoid an unmergeable bootstrap PR.

Repository administration and App creation are not performed by local scripts.
No release or marketplace submission is authorized merely by running local checks.

## Daily process

- Open a feature/fix PR, review it and wait for Required checks plus other existing
  gates. CI runs PHP tests, Node tests, v2 contracts, packaging tests and packaged
  GLPI installation/migration/SMTP integration.
- Merge with a Conventional Commit title. The Release workflow maintains one
  release PR with generated version changes and changelog.
- Review the release PR. Add administrator-facing upgrade/backup instructions and
  known limitations. Migration and delivery behavior need particular attention.
- Explicitly merge that PR when ready. Release Please creates a version tag and a
  draft release. The exact tagged commit is packaged and integration-tested again.
- Only after every gate succeeds are assets uploaded and the draft published.
  Do not publish drafts manually to work around a failed gate.

Assets: ticketmailer-X.Y.Z.tar.gz, ticketmailer.xml, SHA256SUMS. The archive has a
single ticketmailer/ runtime tree, no vendor/dev/test/agent files, and includes
license and security documentation. No runtime Composer packages are currently
needed; adding one deliberately blocks packaging until its inclusion is designed.

## Release-PR review checklist

Record the following in a review comment or linked verification record. Preserve
Release Please's generated PR body and machine-readable markers. Do not apply the
normal change-PR template by overwriting a generated release PR.

- Administrator impact: practical changes, fixes and known limitations.
- Version consistency: VERSION, setup.php, manifest, documentation and changelog.
- Compatibility: exact GLPI/PHP versions actually tested, separately from the
  advertised range. Never widen compatibility based on unit tests alone.
- Upgrade: backup, migration, configuration changes and any manual steps; explicitly
  state when no special action is needed and why.
- Evidence: release commit/candidate, required CI results, packaged installation,
  migration and delivery checks. Re-run affected checks after candidate changes.
- Security/privacy: permissions, duplicate-send/failure semantics and safe artifacts.
- Package/catalog: expected assets, checksums and XML download/version consistency.

Only mark completed checks as passed. Distinguish unrun checks, existing failures
and uncertainties. Human-readable release notes must remain useful without video.
Optional media follows [Release media](RELEASE-MEDIA.md); it is not a release gate.

## Deterministic offline checks

From the repository root, with Python 3.11+:

    python3 -m unittest discover -s tests/release -v
    python3 scripts/release/release.py check
    python3 scripts/release/release.py build
    python3 scripts/release/release.py verify

Build time defaults to the commit timestamp. Set SOURCE_DATE_EPOCH explicitly to
reproduce a historical build. Sorted paths, fixed ownership/mode and timestamp,
USTAR and a timestamped gzip stream make repeat builds byte-identical on the same
Python/zlib toolchain and inputs. Cross-version compression identity is not promised.
CI pins Python's minor version, not the full operating-system/compression stack.
Checksums provide integrity, not a cryptographic identity signature.

    RELEASE_DOCKER_CONTEXT=rootless bash scripts/release/integration.sh

On GitHub runners the Docker context defaults to default. This creates its own
randomly named disposable Compose project, database and loopback ports 18080 and
18025. It never reuses scripts/dev volumes. Ports can be changed with
RELEASE_HTTP_PORT / RELEASE_MAIL_PORT. It removes only its own containers and data
on exit. It downloads dependencies and browser binaries and needs Docker, Node/npm
and Playwright host libraries. Install browser system dependencies explicitly when
needed: npx playwright install --with-deps chromium.

The migration fixture removes legacy columns from a fresh disposable schema,
runs the real migrations twice, and compares columns to the clean installation.
It is a real migration/idempotency test, not a previous-release upgrade test.
Before the second public release, add an upgrade check installing the actual
previous published package and preserving representative data. Do not claim this
fixture proves preservation of every historical database layout or index.

## Failures and recovery

No gate may be bypassed. A failure after draft creation leaves a draft and tag,
not a published catalog entry. Do not move the tag, replace published assets, or
assume rerunning Release Please will re-emit release_created for an existing draft.
Diagnose using the workflow log and reproduce the exact tag locally. If the release
commit is defective, prepare a corrected patch release. If only CI infrastructure
failed, a separately authorized recovery must re-check the exact tag, run all gates,
upload only the three expected assets without --clobber and publish the existing
draft. Recovery automation is intentionally not included in the normal workflow.

## GLPI directory / Marketplace

The package builder generates XML from the same setup.php compatibility constants
and VERSION as the archive. Every XML points to that version's immutable GitHub
asset URL. It follows the official example structure, but is not claimed to have
passed catalog moderation or a server-side schema validator.

After the first successful published release, submit the plugin through
https://plugins.glpi-project.org/ and establish the directory's metadata URL/update
procedure. A candidate is the version-specific GitHub release asset ticketmailer.xml.
Do not use latest/download until the directory confirms that redirect/update model.
The generated XML currently describes one release, not a historical compatibility
catalog. Preserve older release entries if required by the directory before upload.
No catalog registration, acceptance or automatic marketplace update is implied.

References:
- https://github.com/googleapis/release-please-action
- https://github.com/googleapis/release-please/blob/main/docs/manifest-releaser.md
- https://glpi-developer-documentation.readthedocs.io/en/latest/plugins/packaging.html
- https://github.com/pluginsGLPI/example/blob/main/example.xml
