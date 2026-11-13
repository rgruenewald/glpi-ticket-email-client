# Contributing to ticketmailer

## Before opening a change

- Discuss behavior changes in an issue before implementing them.
- Keep the plugin compatible with PHP 8.2+, GLPI 12.0.x (primary) and GLPI 11.0.x (compatibility). GLPI 10 is not supported.
- Do not add SMTP settings, notification-engine delivery, inbound mail handling, drafts, queues, editor libraries, or GLPI core patches.
- Preserve the send invariant: validate → create audit intent → exactly one SMTP attempt → create one `_disablenotif` timeline followup after SMTP success.
- Preserve ticket authorization and CSRF checks on every controller and AJAX endpoint.

## Development setup

Use the bundled local stack:

```bash
docker compose up -d --build
```

The development-only login and required SMTP environment variables are documented in the README. Keep SMTP credentials in the ignored `.env`; do not commit local databases, uploaded files, `vendor/`, secrets, or generated artifacts.

## Local push gate

Run `./scripts/install-hooks` after cloning. `./scripts/bootstrap-worktree` also
installs it. Git hooks are not automatically activated by cloning; installation
is required. The relative `core.hooksPath=.githooks` applies to every worktree
in this repository. Existing custom hook paths are preserved by refusing to
overwrite them.

The pre-push hook runs `./scripts/check-local` before publishing a branch or tag.
It rejects non-HEAD commits, uncommitted tracked/untracked source changes and
source changes during the check run, installs locked
dependencies, and runs PHP/unit/contracts/package checks, UI tests, the actual
Chromium performance benchmark, and all browser E2E tests against a disposable
packaged GLPI stack. Failures or missing prerequisites block the push. Deleting
a remote ref needs no tests. No success cache or automatic skipping is used.

Prerequisites: Docker (rootless context by default), mise with the versions in
`.mise.toml` installed/trusted, PHP for standalone local checks, Playwright Chromium system libraries and the pinned
local CodeQL bundle (`python3 scripts/security/install-codeql.py`). CI always uses the pinned Composer container and builds extension-complete PHP
8.2–8.5 test images; it does not substitute host PHP or Composer. `scripts/composer`
provides the same Composer entrypoint for standalone commands. Set
`TICKETMAILER_DOCKER_CONTEXT` to select another Docker context. Allow several
minutes plus image downloads on the first run. The isolated test stack uses
dynamically selected loopback ports (stable across service restarts) and removes its own containers/data on exit.
Never use production data or an external SMTP server.

Git hooks and local CI are developer guardrails: `make ci` or `./scripts/ci all`
executes the full gate matrix locally (PHP 8.2–8.5 lint and PHPUnit, security audits,
contracts, UI performance, and disposable packaged integration). Local CI runs
completely independent of external CI services. Both all and quick include
fail-closed local CodeQL JS/TS and Python analysis; quick omits packaged
integration and cannot authorize publication. See
[local security gates](docs/verification/local-security.md) for installation,
severity policy, dependency-audit coverage differences and local-enforcement limits.

## Toolchain and evidence

See [toolchain policy](docs/verification/toolchain.md) for version pins, PHPStan
baseline limits, private commit/snapshot-bound evidence, update responsibilities
and the distinction between local gates and independent enforcement.

## Quality bar

Run the contract verifier from the repository root:

```bash
ROOT=. bash scripts/verify/score.sh
```

When dependencies are available, also run:

```bash
bash scripts/composer install
vendor/bin/phpunit
```

Add a focused regression test for behavior changes. Tests must cover externally observable behavior, authorization boundaries, validation failures, and error-state transitions where relevant.

## Translations

All user-visible text must be present in all three files:

- `locales/ticketmailer.pot`
- `locales/ticketmailer.en.po`
- `locales/ticketmailer.de.po`

Keep English and German translations complete and do not introduce raw untranslated UI strings.

## Pull requests

Use a focused branch and describe the problem, behavior change, verification performed, and any manual GLPI/SMTP checks. Keep documentation in English and update the README when installation, configuration, security, or user-visible behavior changes.

## Releases

Releases are published to GitHub via the local release tool `python3 scripts/release/publish.py`.
Releases require passing all local CI gates (`make ci`), version alignment, and deterministic
build verification. The publisher defaults to all and rejects `--publish --ci-mode quick`
and `--publish --skip-ci`; release approval is an explicitly authorized local
`--publish` invocation, not a GitHub Actions workflow. Dry-run mode (`--dry-run`, default) verifies assets offline without publishing;
explicit `--publish` publishes release assets and notes to GitHub. See
[Release management](docs/RELEASING.md) for details.

## Change pull requests

Use [.github/pull_request_template.md](.github/pull_request_template.md) for normal
PRs. Explain the user problem, approach, actual test results and limitations.
Assess delivery/duplicate-send behavior, permissions, privacy, database migrations
and GLPI/PHP compatibility. State why an area is unaffected when appropriate.
For visible UI changes supply relevant before/after screenshots or explain missing
evidence. Use synthetic data only. Videos are optional; see
[Release media](docs/RELEASE-MEDIA.md).

Agents use the local ticketmailer-pr skill, supplementing the global pr-description
skill. Generated release PRs instead follow ticketmailer-release and the release
checklist, preserving Release Please's managed body. Templates and skills guide
review; they do not automatically validate the truth of PR claims.
