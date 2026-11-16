# Release management

## Contract

Short-lived branches and pull requests target main. Squash PRs using Conventional
Commit titles: fix for patches, feat for minor versions, and a breaking-change
footer or ! for major versions. Review the release PR's changelog, compatibility,
migration notes and required checks before merging. No automatic merge.

Keep VERSION, setup.php, CHANGELOG.md and the version manifest aligned. The
retained Release Please configuration is metadata/history, not an Actions
dependency of the local release publisher.
The initial development baseline is 2.3.1 at commit
3c940f2db6c710dd556a6871eb08439366634187. There were no remote tags or releases
when this process was introduced. The baseline is not a historical published
release. The next version follows new Conventional Commits. Never invent old tags.
The bootstrap SHA is ignored after the first managed release.

Runtime metadata advertises GLPI 11.0.0 through 12.0.99. GLPI 12.0.x is primary;
GLPI 11.0.x remains supported. GLPI 10 is not supported (ADR 0002). The packaged
integration matrix targets GLPI 12.0.2 and 11.0.8 / PHP 8.5 in separate disposable
instances; unit CI covers PHP 8.2–8.5. A configured target is not proof of a
completed test. Consult the private run evidence for actual results. PHP 8.2 is
the plugin language minimum; GLPI host requirements also apply.

## Local CI and repository protection

Dependency audits, CodeQL source-security analysis, builds and tests run locally,
not in GitHub Actions. See [local security gates](verification/local-security.md)
for the pinned installation, default query suites, high/critical threshold,
coverage limitations and failure tests. No release-only Actions workflow is
introduced: actual release approval is the explicit local publish command.

Preserve PR requirements and unrelated protections. Changing former Actions-backed
Dependency Review/CodeQL requirements is a separate, explicitly authorized
repository-administration operation after the replacement gates and exact commit
have passed. The local scripts never change rulesets or upload analysis artifacts.
Local hooks are bypassable; removing hosted checks means GitHub can no longer
enforce their successful local equivalents.

Repository administration is not performed by release scripts. No release or
marketplace submission is authorized merely by running local checks.

## Daily process

- For development, run `make ci` or `./scripts/ci all` (PHP matrix 8.2–8.5,
  dependency audits, local CodeQL, score.sh, UI performance, and disposable GLPI
  integration). Before a release dry-run, do not additionally run this identical
  full pipeline on the same candidate: the publisher already invokes it. The
  pre-push hook and the mandatory full publication check remain unchanged.
- `scripts/check-local` is wired into `.githooks/pre-push` to prevent pushing unverified commits.
- Prepare version bump in `VERSION`, `setup.php`, `CHANGELOG.md`, and `.release-please-manifest.json`.
- Review practical changes, upgrade notes, migration behavior, and known limitations.
- Build and verify release assets, including one full CI pass, with dry-run protection:
  `python3 scripts/release/publish.py --dry-run`
- When verified and authorized, publish to GitHub:
  `python3 scripts/release/publish.py --publish --ci-mode all`
- Publisher dry-run and publication default to all local gates. Explicit
  `--dry-run --ci-mode quick` omits packaged integration, not security analysis.
  Publication refuses both `--ci-mode quick` and `--skip-ci`; neither a
  dry-run nor a successful PR grants release authorization.
- Only after every gate succeeds are assets uploaded and the release published.
  Do not publish manually around failed gates.

Assets: ticketmailer-X.Y.Z.tar.gz, ticketmailer.xml, ticketmailer.spdx.json, SHA256SUMS. The archive has a
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
Local CI uses the installed Python version, not a pinned operating-system/compression stack.
Checksums provide integrity, not a cryptographic identity signature. The deterministic
SPDX 2.3 SBOM inventories the runtime package files and their SHA-256 hashes; it is
not an inventory of external GLPI, container OS packages or development dependencies.
The verifier rejects a modified SBOM even if its checksum is recomputed. Local CI
evidence is not signed, independently attested provenance or a SLSA-level claim.
Release signing requires a separately selected identity and key-management policy.

    RELEASE_DOCKER_CONTEXT=rootless bash scripts/release/integration.sh

### Timing and parallel integration

Local CI records monotonic durations and exit codes per step in the private
`.cache/ci/run-*/timings/` directory and collects them in `summary.json` alongside
wall-clock elapsed duration (`duration_seconds`, not the sum of overlapping steps).
Command arguments are not recorded in timing files. PHPStan JSON stays separate
from timing diagnostics. Each run builds into its own `package/` directory;
standalone release builds and the publisher still use `dist/`.

In `all` mode, dependencies, package/contracts and the UI performance benchmark
finish before the independent security, PHP-matrix and integration gates run
concurrently. The UI benchmark therefore never overlaps these heavy workloads.
Every gate must succeed; a failed gate does not hide peer results. Gate logs live
in `gate-security.log`, `gate-php-matrix.log` and `gate-integration.log`. Workers
share the parent evidence directory but never initialize or finalize its summary.
Interruption terminates their process groups and allows cleanup before escalating
to SIGKILL after 60 seconds per worker. `quick` keeps its sequential execution.
Set `TICKETMAILER_GATE_JOBS=1` to run the three gates sequentially (default: `3`).
For a fully serial comparison also set `TICKETMAILER_INTEGRATION_SHARDS=1`
and `TICKETMAILER_INTEGRATION_JOBS=1`.
Security can finish after integration starts, but never after successful CI or
publication: all three exit statuses remain mandatory.

Both GLPI versions run concurrently, each partitioned into two isolated shards
by default (four stacks, capped at four concurrent integration jobs). Each stack
has its own Compose project, database, Mailpit, ports, screenshots and Playwright
reports. Tests within each stack remain serial because they mutate shared state. Dependencies are prepared before
launching either target; integration does not concurrently reinstall node_modules.
Each stack writes `integration-VERSION-shard-N.log` in the evidence directory
(or `integration-VERSION.log` with one shard). A failing
target blocks the gate; the other target is allowed to finish and clean up.
On interruption, target process groups receive termination and execute cleanup.
`TICKETMAILER_INTEGRATION_SHARDS` accepts `1` or `2` (default: `2`), and
`TICKETMAILER_INTEGRATION_JOBS` caps concurrent stacks at `1` through `4`
(default: `4`). Each GLPI version uses a separate stack for each shard.
Playwright partitions individual tests with `--fully-parallel --workers=1`, so
tests still execute serially inside each stack. No session state is cached.
Before startup, JSON discovery checks that the shards are nonempty, disjoint
and exhaustively cover the unsharded suite; the private `e2e-inventory.json`
records this mapping. Reports and timings include the version and shard number.
See [Playwright sharding](https://playwright.dev/docs/test-sharding) for partition
semantics. More stacks require additional CPU and memory; no tests are omitted.

The four-stack default was measured on 12 CPUs / 31 GiB RAM. For constrained
machines or a controlled fully serial comparison, run
`TICKETMAILER_GATE_JOBS=1 TICKETMAILER_INTEGRATION_SHARDS=1 TICKETMAILER_INTEGRATION_JOBS=1 ./scripts/ci all`.
To retain parallel GLPI versions with fewer stacks, use
`TICKETMAILER_INTEGRATION_SHARDS=1 TICKETMAILER_INTEGRATION_JOBS=2 ./scripts/ci all`.
All settings retain every gate and both target versions. Compare the same source snapshot,
toolchain and warm cache without concurrent unrelated test workloads. No CI
evidence is reused to bypass publication gates.

The standalone integration command creates its own
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

No gate may be bypassed. The local publisher runs every gate and builds/verifies
assets before invoking `gh release create`. It does not create a preliminary
release or maintain a separate approval state. HEAD and checkout cleanliness are
checked again after local CI and immediately before the create command. Do not
edit the checkout concurrently: without a global lock, a small final race remains.

A failure before the create command makes no release changes on GitHub. Diagnose
the local command output and private CI artifacts, correct the cause, and rerun
all gates on the exact clean committed candidate before a separately authorized
`--publish` invocation.

A failure or timeout during `gh release create` has an uncertain remote outcome:
the tag, release or some assets may already exist. Inspect the exact remote tag,
release state and four expected assets read-only before doing anything else.
Do not blindly repeat create, move/delete tags, overwrite assets with `--clobber`
or assume the operation rolled back. The publisher has no automatic recovery or
resume mode. Any completion of a partial remote operation needs separate approval,
verification of the exact candidate/tag and checksums, and all local gates. If
public code or assets are defective, prepare a corrected patch release instead of
replacing published artifacts. Catalog refresh/notification is a separate local
operation and must not advertise an incomplete release.

## GLPI directory / Marketplace

The package builder generates XML from the same setup.php compatibility constants
and VERSION as the archive. Every XML points to that version's immutable GitHub
asset URL. It follows the official example structure, but is not claimed to have
passed catalog moderation or a server-side schema validator.

### Stable catalog and history

`dist/ticketmailer.xml` describes the individual release. Root `ticketmailer.xml`
is the historical directory catalog. Once merged into main, its stable source is:
https://raw.githubusercontent.com/rgruenewald/glpi-ticket-email-client/main/ticketmailer.xml

Regenerate with `python3 scripts/release/catalog.py` (authenticated GitHub CLI).
The script reads public stable releases only, downloads their XML and packages,
and checks both against published SHA256SUMS. Invalid or missing assets fail the
whole refresh without replacing the catalog. Historical compatibility is copied
from the published assets, not current development constants. Existing 2.3.2 and
2.4.0 entries therefore retain 11.0.0 through 11.99.99; current development targets GLPI 12.0.x primarily while retaining GLPI 11.0.x support.
Historical published entries are not rewritten. Review this historical range before submission.

After successful publication, refresh the historical catalog with `python3 scripts/release/catalog.py`.
Review the updated `ticketmailer.xml` metadata and commit.
To notify the registered GLPI directory after the release is public:
Run `scripts/release/notify-catalog.sh` (or `make catalog-notify`).

### Registration and refresh notification

Register through https://plugins.glpi-project.org/ and confirm that the directory
accepts the stable XML URL and historical entries. Registration and moderation
require a human account. The notification workflow follows:

1. Store the directory-issued API key in the environment as `GLPI_CATALOG_TOKEN`.
   Never commit the token or include it in plain logs.
2. Run `scripts/release/notify-catalog.sh` to request directory refresh.
   Use `scripts/release/notify-catalog.sh --dry-run` to test connectivity.

The POST to /api/plugin/update has a timeout and fails on HTTP errors. It is not
automatically retried, because a timed-out request may have reached the server.
Check directory status before manually rerunning. HTTP success does not prove
moderation approval or Marketplace availability. Local catalog generation does
not register a plugin, provision secrets, call that API or modify a listing.

References:
- https://github.com/googleapis/release-please-action
- https://github.com/googleapis/release-please/blob/main/docs/manifest-releaser.md
- https://glpi-developer-documentation.readthedocs.io/en/latest/plugins/packaging.html
- https://github.com/pluginsGLPI/example/blob/main/example.xml
