# Contributing to ticketmailer

## Before opening a change

- Discuss behavior changes in an issue before implementing them.
- Keep the plugin compatible with PHP 8.2+ and GLPI 11.0.x.
- Do not add SMTP settings, notification-engine delivery, inbound mail handling, drafts, queues, editor libraries, or GLPI core patches.
- Preserve the send invariant: validate → create audit intent → exactly one SMTP attempt → create one `_disablenotif` timeline followup after SMTP success.
- Preserve ticket authorization and CSRF checks on every controller and AJAX endpoint.

## Development setup

Use the bundled local stack:

```bash
docker compose up -d --build
```

The development-only login and required SMTP environment variables are documented in the README. Keep SMTP credentials in the ignored `.env`; do not commit local databases, uploaded files, `vendor/`, secrets, or generated artifacts.

## Quality bar

Run the contract verifier from the repository root:

```bash
ROOT=. bash scripts/verify/score.sh
```

When dependencies are available, also run:

```bash
composer install
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

Use Conventional Commit PR titles and squash merges. Release Please maintains a
reviewable release PR; merging it explicitly authorizes that version. See
[Release management](docs/RELEASING.md) for required gates and GitHub setup.

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
