# Contributing to ticketmailer

## Before opening a change

- Discuss behavior changes in an issue before implementing them.
- Keep the plugin compatible with PHP 8.1+ and GLPI 10.0.x.
- Do not add SMTP settings, notification-engine delivery, inbound mail handling, drafts, queues, editor libraries, or GLPI core patches.
- Preserve the send invariant: validate → create audit intent → exactly one SMTP attempt → create one `_disablenotif` timeline followup after SMTP success.
- Preserve ticket authorization and CSRF checks on every controller and AJAX endpoint.

## Development setup

Use the bundled local stack:

```bash
docker compose up -d
```

The development-only credentials and Mailpit endpoint are documented in the README. Do not commit local databases, uploaded files, `vendor/`, secrets, or generated artifacts.

## Quality bar

Run the contract verifier from the repository root:

```bash
ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh
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

Use a focused branch and describe the problem, behavior change, verification performed, and any manual GLPI/Mailpit checks. Keep documentation in English and update the README when installation, configuration, security, or user-visible behavior changes.
