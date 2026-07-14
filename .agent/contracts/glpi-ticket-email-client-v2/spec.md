# Contract: GLPI Ticket Email Client v2

## Type
Feature / v2 cutover.

## Goal
Turn `ticketmailer` into a ticket-context email reply client for GLPI 10. A permitted user composes an outbound email from a ticket using GLPI’s rich-text editor, To/CC/BCC, subject, and attachments. The send is SMTP-delivered once, durably audited, and represented as a normal GLPI timeline followup.

## Supersedes v1
This contract supersedes conflicting parts of `.agent/contracts/ticket-mailer/spec.md`:

- The v1 exclusion of core ticket-history/followup integration is removed. A v2 successful send creates an `ITILFollowup`.
- The v1 **ASSIGN → CC** default is replaced by **OBSERVER → CC**.
- The v1 recipient behavior silently dropping malformed raw tokens is replaced by server-side rejection.
- The v1 BCC count-only detail behavior is replaced by the locked v2 visibility policy below.
- The plugin still does **not** use GLPI’s notification engine to deliver the composed email. However, it may create an `ITILFollowup` with `_disablenotif=1` for timeline representation.

## Locked product decisions

1. **Reply policy:** Per entity/profile policy decides whether **E-Mail antworten** is available, promoted, or hides the native reply control. No unsupported core patch or DOM/CSS-only suppression is an acceptable implementation.
2. **Timeline:** A successful email creates a standard GLPI followup holding the complete outbound email and secure attachment links.
3. **Native notifications:** The followup is created with `_disablenotif=1`; only the composed SMTP email is delivered to recipients.
4. **Incoming mailbox collision:** Matching an active GLPI mail collector’s valid-email `login` produces a warning and needs an explicit, CSRF-protected POST override. This is documented as best effort; aliases, forwarding, and non-email logins are not detected.
5. **Actor defaults:** Requesters default to To. Observers alone default to CC. Assignees are not auto-added.
6. **Deliberate BCC visibility:** BCC is supported and its full address list is visible to every user who may read the ticket, both in the timeline representation and secure audit detail. SMTP message headers must still omit BCC.
7. **Attachments:** Every ticket reader may download outbound attachments through a ticket-authorized, secure route. Direct filesystem paths are never rendered.

## Non-goals

- IMAP polling, inbound-mail parsing, mail collector administration, alias discovery, or a guaranteed mailbox-loop detector.
- A standalone mail client, SMTP configuration UI/server, draft storage, queue/retry daemon, or a generic workflow/rules engine.
- A core GLPI patch or an unverified replacement of the standard reply control.
- Hiding BCC from users who can read the ticket; this is intentionally not a v2 privacy property.
- New editor libraries. Use the GLPI-bundled rich-text editor.

## Target compatibility

- GLPI 11.0.x, verified against GLPI 11.0.8.
- PHP 8.2+.
- Existing GLPI SMTP configuration and direct `GLPIMailer` transport path.

## Affected surfaces

| Area | Required change |
|---|---|
| Schema | Evolve `glpi_plugin_ticketmailer_logs`; add reply-policy persistence and versioned migration. |
| Compose | Change actor defaults, raw recipient validation, collector warning/override. |
| Send | Create audit intent, send SMTP exactly once, create suppressed-notification timeline followup, record all outcomes. |
| Timeline | Add a focused `ITILFollowup` integration class; no direct native notification event call. |
| Files | Store generated attachment identifiers; serve downloads only after ticket-read authorization. |
| Policy | Persist minimal entity/profile reply policy with deterministic precedence. |
| Audit | Preserve complete recipients, body, attachments, SMTP result, followup link/status, override evidence. |
| UX/i18n | Add English/German labels and errors; update UI and secure detail view. |
| Tests | Extend PHPUnit and contract verifier; retain manual GLPI/Mailpit checks. |

Expected implementation files: `setup.php`, `hook.php`, `sql/install.sql`, `sql/uninstall.sql`, `sql/update-1.1.0.sql`, `inc/replypolicy.class.php`, `inc/recipients.class.php`, `inc/mailboxguard.class.php`, `inc/audit.class.php`, `inc/timeline.class.php`, `inc/mailer.class.php`, ticket integration class(es), `front/compose.php`, `front/send.php`, `front/download.php`, `front/log_entry.php`, recipient/upload AJAX endpoints, compose/detail templates, `js/composer.js`, locale catalogs, README, and tests. Exact GLPI action registration may be proven during implementation; without proof, the native reply control remains available.

## Data contract

### Audit log migration
The existing audit table retains `tickets_id`, `users_id`, timestamps, subject, HTML/text, To/CC/BCC JSON, attachment/inline-image JSON, SMTP status/error, and Message-ID. Migration adds:

```text
followups_id       INT NULL                 -- created GLPI followup
status             ENUM('pending','sent','failed') NOT NULL
timeline_status   ENUM('pending','recorded','failed') NOT NULL
 timeline_error    TEXT NULL
mailbox_override   TINYINT NOT NULL DEFAULT 0
mailbox_matches    MEDIUMTEXT NULL           -- JSON list of matched collector logins
```

`pending` is an internal durable intent state. A user-facing success requires both `status='sent'` and `timeline_status='recorded'`.

### Reply policy
A minimal plugin table has `entities_id`, nullable `profiles_id`, and `mode` in `available|promoted|hide_native`. Effective policy precedence is:

1. exact entity/profile row;
2. entity default row;
3. global default `available`.

No rule inheritance beyond that order is required.

### Attachments
Stored attachment descriptors use a generated server-side ID plus filename, trusted MIME, size, and controlled storage reference. URLs contain an audit ID and attachment ID only. `front/download.php` resolves descriptors server-side and requires `Ticket::canViewItem()` before streaming.

## Acceptance criteria

### A1 — Entry and effective reply policy
A ticket user who may update/follow up sees **E-Mail antworten** when the effective policy permits it. The effective policy honors exact entity/profile, entity default, then global default precedence. `hide_native` is implemented only through a verified GLPI extension point; absent that proof, implementation must retain the native reply control and treat the policy as `promoted`/`available`, never hide it with DOM/CSS manipulation.

### A2 — Actor recipient defaults
Opening E-Mail antworten defaults all requester addresses to To and all observer addresses to CC. Assignees are not auto-added. Alternative actor emails are used first; otherwise the GLPI user default email is used. Empty/unavailable actor emails are omitted. Users may add internal users and external email addresses to each field.

### A3 — Compose experience
The form exposes To, CC, BCC, Subject, rich HTML Body, normal attachments, and inline images. It uses the GLPI-bundled rich-text editor; no editor dependency is added. Subject and non-empty body are required. It offers an unchecked **Attach public ticket history** option instead of a separate forwarding action. The sender may individually select attachments from the ticket and public follow-ups independently of that option; private follow-ups and their documents are never offered or sent. Each offered attachment can be opened in a separate tab through a ticket-read-authorized endpoint. Enabling the option appends the public ticket history to the message body only.

### A4 — Strict raw recipient parsing
Server-side parsing splits raw To/CC/BCC input on supported delimiters, rejects every malformed non-empty token with a field error, normalizes valid addresses, and preserves the fields in the error form. Invalid tokens must never be silently discarded. At least one valid recipient in To, CC, or BCC is required. Whether BCC-only is allowed is explicit: **v2 allows it**, because a BCC recipient is a valid SMTP envelope recipient.

### A5 — Incoming-mailbox warning and override
For each normalized recipient, the server compares exact normalized addresses with `login` values from active `glpi_mailcollectors` rows only when `login` is a valid email address. Matching is best effort and must state that aliases, forwarding, and non-email logins are not detected. A match:

- is shown by AJAX as a non-authoritative warning;
- requires a visible explicit override control; and
- is rechecked during POST before any audit intent, file finalization, SMTP send, or followup.

The POST must reject missing, false, forged, or stale override evidence. Audit records the accepted override and matched addresses.

### A6 — Authorization, CSRF, and controlled files
Compose, send, recipient validation, upload, and download require GLPI login. Compose/send/upload require the appropriate ticket update/followup right. Download and all audit/detail access require `Ticket::canViewItem()`. POST and AJAX CSRF checks are enforced. Files use configured GLPI upload limits, generated storage identifiers, ticket-scoped ownership checks, and server-determined MIME; user-provided paths, MIME, or same-name uploads cannot overwrite or expose another attachment.

### A7 — Durable SMTP intent and exactly-once send attempt
After all validation, create audit intent `status='pending'` and `timeline_status='pending'`. Submit exactly one SMTP send through GLPI’s configured SMTP settings. The compose/send path must not call `NotificationEvent::raiseEvent`, `Notification::raiseEvent`, `NotificationMailing::send`, or another GLPI notification-delivery API.

On SMTP failure, mark the audit row `status='failed'`, store a safe error message, leave no successful-send followup, and show the failure from secure audit detail. No automatic resend occurs.

### A8 — Complete timeline followup without duplicate notifications
On SMTP success, persist `status='sent'` and Message-ID. Create exactly one standard `ITILFollowup` linked to the ticket with `itemtype=Ticket`, `items_id`, current sender, sanitized complete outbound content, and `_disablenotif=1`. The content includes sender, sent time, To, CC, **full BCC list**, subject, rendered body, and secure attachment links. Record its ID as `followups_id` and set `timeline_status='recorded'`.

With GLPI notifications enabled, this followup must not cause a second GLPI-generated outbound email. The SMTP message itself must not put BCC recipients in visible To/CC headers.

### A9 — Timeline-failure semantics
If SMTP succeeds but followup creation fails, retain the sent audit row, set `timeline_status='failed'`, store the failure, and show a hard incomplete-send outcome. Do not automatically resend SMTP. Do not report full success until the followup is recorded. This is an external-SMTP failure boundary, not a silent fallback.

### A10 — BCC and attachment reader visibility
Every ticket reader may see the complete BCC list in the followup and audit detail and may open secure outbound attachment links. A user without ticket-read access receives no BCC data, attachment metadata, body, or download bytes.

### A11 — Audit detail and timeline consistency
Audit detail and its linked followup expose identical sender, recipient, subject, body, attachment, and delivery fields under ticket-read authorization. Neither view renders raw filesystem paths, untrusted HTML, attachment descriptors from another ticket, or SMTP credentials. BCC remains excluded from outgoing visible headers but intentionally remains visible in these ticket views.

### A12 — Localization and migration
All new user-facing labels, error text, policy labels, and incomplete-send state are translated through GLPI and present in `ticketmailer.pot`, English, and German catalogs. Install creates new schema idempotently; upgrade from v1 runs a versioned migration without losing existing audit rows. Existing rows receive safe defaults for new timeline/override fields.

## Required automated verification

1. Requester/observer defaults; assignee exclusion; fallback and alternative emails.
2. Policy precedence and refusal to use DOM/CSS-only native-reply hiding.
3. Invalid raw tokens rejected server-side; valid BCC-only send allowed.
4. Collector-login exact match warning; POST override required and recorded; alias limitation documented.
5. CSRF, update/followup authorization, ticket-read download authorization, generated file identity, and overwrite resistance.
6. SMTP success creates one mail/audit/followup with `_disablenotif`, full recipient content including BCC, and secure links.
7. SMTP failure versus followup-after-SMTP failure produces the distinct required statuses and never retries SMTP.
8. Ticket reader can see BCC/download; non-reader cannot. SMTP visible headers contain no BCC.
9. Locale parity and migration field presence.

## Required manual verification — GLPI 11.0.8 + Mailpit

- Verify the selected ticket action integration uses a supported GLPI extension point.
- Send a rich email with To/CC/BCC, normal attachment, and inline image. Mailpit has exactly one message with expected envelope/body/attachments and no BCC visible header.
- Open the ticket as a second ticket reader: complete followup shows To/CC/BCC/body and links download. A user without ticket read is denied every view/download.
- Enable GLPI notifications and verify `_disablenotif` prevents a duplicate followup notification.
- Configure a receiver with an email-valued `login`: verify warning, required override, audit evidence, and documented alias limitation.
- Induce SMTP failure and followup failure independently; verify A7/A9 states and no automatic resend.

## Delivery constraints

- No production implementation starts from this contract until the GLPI 11 action/policy integration is source- or runtime-proven.
- No compatibility shim retains ASSIGN→CC defaults.
- No custom mail transport, editor library, or unrequested background job is added.
