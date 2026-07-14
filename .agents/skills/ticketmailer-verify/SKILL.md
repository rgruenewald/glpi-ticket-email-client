---
name: ticketmailer-verify
description: How to verify ticketmailer changes — v2 score.sh, PHPUnit, docker/mailpit smoke.
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

## 3. Runtime smoke (docker)

```bash
docker compose up -d
# GLPI  : http://localhost:8080  (glpi / glpi)
# mailpit: http://localhost:8025
```

Manual checks (README M1–M8, aligned with v2 where noted):

| Id | Check |
|---|---|
| M1 | compose stack healthy |
| M2 | login technician |
| M3 | compose+send → audit row `sent` |
| M4 | message in mailpit (To/CC/BCC/body) |
| M5 | forward subject/body shape |
| M6 | purge ticket cascades audit rows |
| M7 | SMTP fail → audit `failed` + UI error |
| M8 | de/en UI strings, no raw `__()` keys |

Also verify v2-specific paths when touched: timeline followup present, `_disablenotif` behavior (no second notification mail), mailbox warning/override, secure download ACL.

## What “green” means

| Change type | Minimum bar |
|---|---|
| Structural / rename / new surface | v2 `score.sh` |
| Logic in `inc/` or send path | score.sh + PHPUnit (or targeted test) |
| End-to-end mail behavior | docker smoke against mailpit |
| Locales only | score.sh locale checks + spot UI language switch |

## Do not

- Invent a second CI system in-repo without need
- Report success if SMTP sent but timeline followup failed (incomplete send)
- Use v1 score.sh as the sole gate for v2 surfaces
