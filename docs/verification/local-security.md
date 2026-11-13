# Local security gates

## Run and install

This repository uses local dependency, source-security, build and test gates.
It does not require GitHub Actions. No workflow or release-approval automation
is introduced. Install the pinned CodeQL bundle explicitly once:

```sh
python3 scripts/security/install-codeql.py
./scripts/ci security
./scripts/ci all
```

The installer currently supports glibc Linux x86_64, downloads only the official
CodeQL 2.27.4 bundle over HTTPS and checks its committed SHA-256 before extraction
or execution. Extraction copies only regular files/directories under `codeql/`,
rejecting traversal, links and special files before writing, without the newer
`tarfile` filter API. This preserves the Python 3.11+ contract, including 3.11.0.
CLI and bundled queries are pinned together in
`scripts/security/codeql-lock.json`. Installation is outside Git under
`${XDG_CACHE_HOME:-$HOME/.cache}/ticketmailer/codeql/2.27.4`. An existing version
is never overwritten. Installation is not automatic during a check: missing,
wrong-version or unverified tools block the gate. Cache contents are trusted
local user-owned tooling; the receipt is not protection against a malicious
owner modifying an installation after verification.

## License and privacy

The repository is public on GitHub under GPL-3.0. GitHub's CodeQL terms permit
analysis of OSI-licensed code and automated analysis of such code hosted and
maintained on GitHub.com. The CLI itself is not redistributed in this repository.
Do not assume these terms permit scanning proprietary/private code; review the
license before reuse elsewhere. Synthetic negative fixtures inherit the repository
license and are analyzed as text, never executed.

All extraction, queries and SARIF processing run locally. There is no upload
command, SARIF API call or external scanner service. Source snapshots contain
Git-tracked and non-ignored untracked files, including current uncommitted source,
not ignored credentials, dependencies, runtime data or generated test artifacts.
Symlink source paths are rejected. Private databases, logs and SARIF stay under
ignored `.cache/security/` in mode-0700 run directories, outside Playwright's
`test-results/` cleanup. They can contain
source code: do not publish them indiscriminately. Logs from dependency audits
may contact the configured package registries and advisory services.

## PHP analysis and run evidence

PHPStan adds a separate PHP 8.2-targeted level-1 gate over runtime PHP. Its explicit
baseline covers missing external GLPI symbols only; it is not PHP taint analysis.
CI also records private commit/snapshot-bound reports, including the runtime
package hashes. See [toolchain policy](toolchain.md) for pins, baseline limitations,
report privacy, update responsibilities and independent-enforcement boundaries.

## Source-security policy

The `javascript-code-scanning.qls` and `python-code-scanning.qls` default suites
match the former GitHub default setup's suite family. The pinned bundle contains
`codeql/javascript-queries` 2.5.0 and `codeql/python-queries` 1.9.0. JavaScript
analysis also supports TypeScript. These are not security-extended suites.

The former main rule required CodeQL security alerts at `high_or_higher`, with
quality-alert threshold `none`. Locally, security-severity >=7.0 blocks (high
7.0-8.9, critical >=9.0). Lower scores and non-security findings remain in SARIF
but do not block. Missing/malformed SARIF, missing security severity on security
results, missing or not explicitly successful invocations, warning/error analysis
notifications, extraction errors/warnings, missing query packs and nonzero CLI
exits block the gate. Each language must report both baseline-expected and
successfully-extracted file diagnostics. An independent snapshot inventory requires
all `.py` files for Python and `.js`, `.jsx`, `.mjs`, `.cjs`, `.ts`, `.tsx`, `.mts`,
`.cts`, `.html`, `.htm`, `.vue` files for JavaScript/TypeScript, without test or
generated-file exclusions. Both that inventory and the CodeQL baseline must be
subsets of successfully extracted source files. `summary.json` records the three
file lists for each language. New source formats require a reviewed inventory
update; extraction is not proof that every dynamic runtime behavior is modeled. Suppressions and existing-alert baselines are not
honored: **all** high/critical results in the candidate block, which is stricter
than relying only on new PR alerts.

This preserves language coverage, default-suite family and severity policy, not
bit-identical results: hosted CodeQL and its models evolve independently; local
versions are deliberately pinned. No Actions-language analysis is needed while
there are no workflow files. CodeQL does not provide PHP analysis here. PHP lint,
contract checks, authorization tests and browser tests remain separate controls,
not a general PHP taint-analysis claim.

## Dependency coverage and differences

`composer audit` checks known advisories for installed Composer dependencies;
`npm audit --audit-level=moderate` checks the locked npm dependency graph and
fails for moderate or higher advisories. Dev dependencies are included. Failed
or unavailable audits block CI. Version locks are retained and no automatic
force-fix is performed.

These audits are **not semantically identical to Dependency Review**. Dependency
Review examines dependencies introduced/changed by a PR using GitHub's dependency
graph and advisory coverage. Local audits check the current graph, including
existing vulnerabilities, using each registry's advisory support. Neither is a
proof against unknown vulnerabilities or malicious but unreported packages.
They do not review arbitrary Docker base images, OS packages, tool binaries,
licenses or every transitive supply-chain risk. The former Dependency Review
workflow had no additional license policy. The local moderate npm threshold
preserves its minimum severity policy, not identical advisory/diff coverage.

## Pipeline, pre-push and release

`all` and `quick` both run dependency audits, CodeQL, the PHP 8.2-8.5 matrix,
contracts, package checks, JS/UI and performance checks. `all` additionally runs
disposable packaged GLPI installation/migration/SMTP/browser integration. The
pre-push hook invokes `scripts/ci all` and checks source cleanliness and HEAD
stability. No success cache or automatic skip is used.

Release remains an explicit local operation, not an Actions approval:

```sh
python3 scripts/release/publish.py --dry-run  # default: all local gates
python3 scripts/release/publish.py --dry-run --ci-mode quick  # no integration
# Only with separate authorization to actually publish:
python3 scripts/release/publish.py --publish --ci-mode all
```

Publication requires a clean committed checkout and refuses both `--skip-ci` and
`--ci-mode quick`. HEAD and checkout cleanliness are rechecked after CI and
immediately before publication; avoid concurrent checkout edits because this is
not an exclusive lock. Dry-run may explicitly use quick or test-only skip-ci; neither
authorizes publication. No release is triggered by installation, scanning or tests.

## Verify failure behavior

```sh
python3 -m unittest discover -s tests/release -v
python3 tests/security/codeql-fixtures.py --output /absolute/new/private-directory
```

The unit tests cover installer integrity rejection, artifact-directory isolation,
SARIF threshold boundaries, invalid/missing results, missing/incomplete invocations,
extraction/coverage diagnostics, incomplete source coverage, suppressions,
missing/wrong tools, nonzero analysis exit, CI wiring, Actions Dependabot removal,
mandatory full release CI and checkout/HEAD drift after CI or during build.
Installer archive tests exercise regular-file extraction without `extractall` and
reject paths, links and special members before writes. The compatibility test
does not require a separately installed Python 3.11 interpreter. The explicit real-fixture command must find high/critical command
injection in **both** languages and prove that the real gate rejects it. Fixture
files use `.fixture` suffixes so the normal project scan does not analyze them
as deployable JS/Python code. Never execute the vulnerable fixtures.

## Enforcement and updates

Local hooks can be bypassed or uninstalled. Once hosted requirements are removed,
GitHub cannot enforce successful local checks. Preserve PR rules and manual review
of the exact candidate, tool versions and local evidence. No rule is changed by
these scripts. Updating the bundle is a reviewed change to version, archive digest
and query-pack versions, followed by policy tests, real fixtures and full CI.

## Official references

- [CodeQL CLI and usage prerequisites](https://docs.github.com/en/code-security/codeql-cli/getting-started-with-the-codeql-cli/about-the-codeql-cli)
- [CodeQL terms](https://github.com/github/codeql-cli-binaries/blob/main/LICENSE.md)
- [Official bundle installation](https://docs.github.com/en/code-security/codeql-cli/getting-started-with-the-codeql-cli/setting-up-the-codeql-cli)
- [Pinned release](https://github.com/github/codeql-action/releases/tag/codeql-bundle-v2.27.4)
- [CodeQL SARIF](https://docs.github.com/en/code-security/codeql-cli/using-the-advanced-functionality-of-the-codeql-cli/sarif-output)
- [Alert severity](https://docs.github.com/en/code-security/code-scanning/managing-code-scanning-alerts/about-code-scanning-alerts)
