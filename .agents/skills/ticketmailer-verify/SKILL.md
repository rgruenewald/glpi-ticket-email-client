---
name: ticketmailer-verify
description: How to verify ticketmailer changes — v2 score.sh, PHPUnit, and Docker/SMTP smoke checks.
---

# Skill: ticketmailer-verify

## When to use

After any non-trivial code, SQL, template, locale, or contract-surface change. Before claiming done.

## 1. Structural verifier (canonical)

From plugin root (this worktree):

```bash
ROOT=. bash scripts/verify/score.sh
```

Expect `PASS: N, FAIL: 0` and exit 0.

- Covers required files, key patterns, and forbidden notification-delivery greps.
- **v1** `docs/archive/contracts/ticket-mailer/score.sh` is **legacy** — do not treat as gate when it conflicts with v2.

## 2. PHPUnit

```bash
composer install   # if vendor/ missing
vendor/bin/phpunit
```

- Config: `phpunit.xml`, suite under `tests/`
- Bootstrap: `tests/bootstrap.php`
- Acceptance coverage lives mainly in `tests/AcceptanceTest.php`

If PHP/GLPI stubs are incomplete in a bare checkout, still keep score.sh green; note PHPUnit blockers honestly.

## 3. Real Docker / browser verification

Use [the local E2E recipe](../../../docs/verification/docker-e2e.md).
From the worktree to mount:

```bash
scripts/dev up
scripts/dev test
scripts/dev e2e
```

The helper selects the rootless Docker context explicitly. GLPI and Mailpit bind
only to loopback. No external SMTP credentials are needed. Use synthetic data;
never point this suite at a real SMTP server. The SMTP-outage test stops and
restarts Mailpit, clearing captured mail. Run serially against the shared stack.

The browser suite covers login, ticket composition, To/CC/BCC, attachment
upload/download, one captured delivery, audit and timeline, denied access and
SMTP failure, public-history privacy, CID images, mailbox override, entity
boundaries and entity/profile policy precedence. See the recipe for remaining coverage limits. PHPUnit and JS
checks are separate from browser tests; report each result explicitly.

## Compose dialog and layout checks

For changes to the ticket reply UI, verify the ticket-button entry rather than
using front/compose.php alone. The direct URL is a fallback and does not prove
modal behavior.

Use the isolated local test instance and synthetic fixtures. Open a ticket, click
.ticketmailer-timeline-action, and verify that the compose modal opens without
page navigation. Switch to Internal note and exercise the changed control there.
For solution templates, verify clipboard output, unchanged editor content and
status switches on selection, repeated Email/Internal note switching, native
note submission with its marker visible in the ticket, and reopening/closing.

For side-by-side controls, assert both horizontal order and the same vertical
row. For stacked mobile controls, assert vertical order. A CSS-source assertion
or increasing X coordinates alone does not prove the rendered layout.

Before/after screenshots must use the same entry path, mode, language, viewport,
and comparable synthetic fixture. Record the source revision for each image.
Capture the changed dialog state, not the fallback page, when the normal user
path is modal. State the GLPI version and distinguish actual browser checks from
static checks or previously recorded evidence.

## UI performance regression gate

For UI/JavaScript/CSS changes also run:

~~~sh
npm ci
npx playwright install chromium
npm run test:ui
npm run test:performance
~~~

See docs/performance/regression-tests.md for fixed JS/CSS references, timing tolerances,
negative-case evidence and scope limits. CI runs .github/workflows/ui-performance.yml.
Do not automatically refresh references or increase budgets after a failure.

## Release surfaces

For release tooling also load ../ticketmailer-release/SKILL.md and run the offline
Python contract tests, package build/verify and disposable packaged integration
documented in ../../../docs/RELEASING.md. Source-tree smoke tests do not replace
the packaged-code gate.
