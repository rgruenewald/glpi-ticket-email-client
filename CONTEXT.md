# ticketmailer — project context

## Identity

- **Name:** `ticketmailer` (GLPI plugin directory / gettext domain)
- **Type:** GLPI 11 plugin (`composer.json` type `glpi-plugin`)
- **Runtime:** PHP ≥ 8.2, GLPI ^11.0 (verified against 11.0.8)
- **License:** GPL-3.0-or-later
- **Version constant:** `PLUGIN_TICKETMAILER_VERSION` in `setup.php` (currently 2.0.0)

## One-liner

Ticket-context reply client: **Email** (default) sends once via GLPI’s configured direct mail transport, stores durable plugin audit, and records a public `ITILFollowup`; **Internal note** mounts GLPI’s native private `ITILFollowup` form/lifecycle without Ticketmailer audit/SMTP.

## Vocabulary

| Term | Meaning |
|---|---|
| Audit log | Row in `glpi_plugin_ticketmailer_logs` (intent + outcome) |
| Timeline followup | Standard GLPI `ITILFollowup` on the ticket (`_disablenotif=1`) |
| Reply policy | Per entity/(optional) profile mode: `available` \| `promoted` \| `hide_native` |
| Mailbox guard | Best-effort match of recipients vs active `glpi_mailcollectors.login` emails |
| Mailbox override | Explicit CSRF-protected POST flag when collector match exists |
| Actor defaults | Requesters → To; observers → CC; assignees not auto-added |
| Secure download | `front/download.php` streams by audit+attachment id after ticket view ACL |

## Hard invariants

1. Ticketmailer Email compose/send must **not** call GLPI notification-delivery APIs (`NotificationEvent::raiseEvent`, `Notification::raiseEvent`, `NotificationMailing::send`, etc.). Native Internal note preserves GLPI's configured followup notifications.
2. Email only: after validation, create audit intent (`status=pending`, `timeline_status=pending`) → **exactly one** SMTP attempt → no auto-resend.
3. Internal note posts GLPI's native private `ITILFollowup` form/controller/model; no Ticketmailer recipients, signature, audit, mailer, or outbound timeline formatter.
4. Email success requires `status=sent` **and** `timeline_status=recorded`. SMTP ok + followup fail → incomplete outcome, keep sent audit, set `timeline_status=failed`.
5. ACL: compose/send/upload need ticket update/followup rights; download + audit detail need `Ticket::canViewItem()`. Login + CSRF on POST/AJAX.
6. Email recipients: server rejects malformed non-empty tokens (no silent drop). BCC-only is allowed. SMTP headers omit BCC from To/CC.
7. **BCC is ticket-visible** by design (timeline + audit detail). Not a privacy feature. UI warns before Email send.
8. Mailbox match → warn + require override; aliases/forwarding/non-email logins **not** detected.
9. `hide_native` is stored but effective policy demotes to `promoted` until a real GLPI extension point is proven — never hide native reply via DOM/CSS.
10. No plugin SMTP config UI; read GLPI core `smtp_*` only. No new editor library (GLPI-bundled rich text).
11. i18n: English + German catalogs must stay complete for UI strings.

## Architecture (pointers)

```
setup.php / hook.php     plugin meta, hooks, install/uninstall
inc/*.class.php          PluginTicketmailer* domain logic
front/*.php              compose, send, forward, log, download
ajax/*.php               validate, upload, forward_preview
templates/*.html.twig    compose / forward / log_entry UI
sql/                     install + versioned updates
locales/                 ticketmailer.{pot,en.po,de.po}
docker/ + compose        GLPI 11 + MariaDB + Mailpit
tests/                   PHPUnit acceptance
```

**Canonical product contract:** `.agent/contracts/glpi-ticket-email-client-v2/spec.md`
**Historical (superseded on conflict):** `.agent/contracts/ticket-mailer/`
**Planning history (not live rules):** `.agent/planning/glpi-ticket-email-client/`
**Locked decisions ADR:** `docs/adr/0001-v2-locked-product-decisions.md`

## Schema

- `glpi_plugin_ticketmailer_logs` — audit + SMTP + followup link + mailbox override evidence
- `glpi_plugin_ticketmailer_reply_policies` — `entities_id`, nullable `profiles_id`, `mode`
- Fresh install: `sql/install.sql` · Upgrade path: `sql/update-1.1.0.sql` · Drop: `sql/uninstall.sql`

## Non-goals

IMAP/inbound parse · drafts · queue/retry daemon · SMTP server/UI · core GLPI patches · notification-engine delivery of composed mail · guaranteed mailbox-loop detector · i18n beyond en/de as first-class.

## Skills

| Skill | When |
|---|---|
| `.agents/skills/glpi-plugin-runtime/SKILL.md` | hooks, install, class map, docker mount |
| `.agents/skills/ticketmailer-compose-send/SKILL.md` | compose/send/timeline/mailbox/ACL |
| `.agents/skills/ticketmailer-verify/SKILL.md` | score.sh, PHPUnit, smoke |

## Verify (quick)

```bash
ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh
# optional: composer install && vendor/bin/phpunit
# optional: docker compose up -d → README smoke M1–M8
```
