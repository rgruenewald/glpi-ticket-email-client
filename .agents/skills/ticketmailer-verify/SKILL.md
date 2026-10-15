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
ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh
```

Expect `PASS: N, FAIL: 0` and exit 0.

- Covers required files, key patterns, and forbidden notification-delivery greps.
- **v1** `.agent/contracts/ticket-mailer/score.sh` is **legacy** — do not treat as gate when it conflicts with v2.

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
