---
name: ticketmailer-compose-send
description: ticketmailer compose/send pipeline, recipients, audit intent, SMTP once, timeline followup, mailbox guard, ACL, forward.
---

# Skill: ticketmailer-compose-send

## When to use

- Compose UI, send POST, recipient validation, uploads, downloads
- Audit log shape, timeline followup, mailbox collision, reply policy UX
- Forward ticket action
- Anything touching ACs A1–A9-ish in the v2 contract

Canonical detail: `.agent/contracts/glpi-ticket-email-client-v2/spec.md`.

## Entry surfaces

| Path | Role |
|---|---|
| `front/compose.php` | compose form |
| `front/send.php` | POST send |
| `front/forward.php` | forward form |
| `front/download.php` | attachment stream (ticket view ACL) |
| `front/log.php` / `log_entry.php` | audit list / detail |
| `ajax/validate_recipients.php` | live validation + mailbox warn |
| `ajax/upload.php` / `upload_image.php` | attachments / inline images |

## Actor defaults (open compose)

- **To:** ticket requesters (alt email first, else default user email; skip empty)
- **CC:** observers only
- **Assignees:** not auto-added
- User may add internal users and external addresses to any field

## Recipient rules

- Split on supported delimiters; **reject** every malformed non-empty token (no silent drop)
- Normalize valid addresses; redisplay fields on error
- ≥1 valid address in To ∪ CC ∪ BCC required
- **BCC-only allowed** (envelope recipient)
- SMTP: BCC not placed on visible To/CC headers

## Mailbox guard

- Compare normalized recipients to active `glpi_mailcollectors` rows whose `login` is a valid email
- Best-effort only (no aliases/forwarding/non-email logins)
- AJAX: non-authoritative warning
- POST: recheck; require explicit override evidence; reject missing/false/forged/stale
- Audit stores override flag + matched logins JSON

## Send pipeline (order matters)

1. AuthZ (login + ticket update/followup) + CSRF
2. Validate subject, non-empty body, recipients, uploads, mailbox override
3. Create audit **intent**: `status=pending`, `timeline_status=pending`
4. **One** SMTP send via `PluginTicketmailerMailer` / GLPI SMTP config
5. On SMTP fail → `status=failed`, safe error, **no** success followup, surface failure
6. On SMTP ok → `status=sent`, store Message-ID
7. Create exactly one `ITILFollowup` (`itemtype=Ticket`) with full outbound content + secure attachment links, **`_disablenotif=1`**
8. On followup ok → `followups_id`, `timeline_status=recorded`
9. On followup fail → keep sent audit, `timeline_status=failed`, hard incomplete outcome (no SMTP retry)

**Forbidden on this path:** `NotificationEvent::raiseEvent`, `Notification::raiseEvent`, `NotificationMailing::send`, or other notification-delivery APIs.

## BCC visibility (locked)

- Full BCC list visible to every user who can read the ticket (timeline + audit detail)
- Compose UI must warn before send
- Not a privacy property — do not “fix” by hiding BCC from ticket readers

## Attachments

- Server-generated storage ids; user MIME/path not trusted
- Descriptors in audit JSON; URLs carry audit id + attachment id only
- `front/download.php`: resolve server-side; require `Ticket::canViewItem()` before stream
- Respect GLPI upload limits; no same-name overwrite of another attachment

## Reply policy

- Table: `glpi_plugin_ticketmailer_reply_policies`
- Precedence: exact entity+profile → entity default (`profiles_id` null) → global `available`
- Modes: `available` | `promoted` | `hide_native`
- Effective: if `hide_native` and no proven GLPI hide extension point → treat as `promoted` (never DOM/CSS hide)

## Forward (secondary)

- Action builds full ticket history (description + followups chronological, quoted headers)
- Subject pattern: `Fwd: [#<id>] <title>`
- Empty recipient defaults; still goes through send/audit rules as implemented
- No “last message only” mode in current product

## UI / i18n

- GLPI-bundled rich text only
- Warn: BCC + attachments visible to all ticket readers
- Labels/errors: en + de gettext domain `ticketmailer`
