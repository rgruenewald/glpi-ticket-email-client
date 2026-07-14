# Spec: GLPI Ticket Email Plugin (`ticketmailer`)

## Type

Feature.

## Goal

Add a GLPI plugin that provides an integrated email compose UI inside
a ticket. The plugin must support multi-recipient To/CC/BCC fields,
rich-text body, inline images, file attachments, per-send audit
logging on the ticket, an email-style "forward ticket" action that
emails the full ticket history, and full i18n for English (default)
and German. The plugin must reuse GLPI's configured SMTP server and
must not route mail through GLPI's native notification engine.

## Non-goals

- Inbound mail / IMAP / reply parsing.
- Replacing or extending GLPI's native notifications for
  requesters/watchers.
- Operating its own SMTP server.
- Migration from Zammad or other products.
- First-class i18n beyond en/de.
- Webhooks, push, or non-email channels.
- Multi-tenant / multi-instance deployment.
- A custom template engine beyond what GLPI already provides.

## Affected surfaces

### New files (plugin lives at `plugins/ticketmailer/` inside the GLPI install, but is developed in the repo root)

```
setup.php                              # plugin registration, version, hooks
hook.php                               # install/uninstall/check_config
composer.json                          # name, type, license, require (PHP, glpi)
README.md                              # install/usage
.gitignore                             # vendor/, _files/, _cache/, _log/, .env, …
docker-compose.yml                     # GLPI 10 + mailpit
docker/glpi/Dockerfile                 # GLPI image with plugin mounted
docker/mailpit/Dockerfile              # mailpit capture server
inc/
  plugin.class.php                     # PluginTicketmailer class (init/version)
  config.class.php                     # reading GLPI mail config
  mailer.class.php                     # send(): builds + sends via GLPI mailer
  composer.class.php                   # build email from compose form
  forwarder.class.php                  # extracts full ticket history (email-style forward)
  audit.class.php                      # audit log CRUD
  recipients.class.php                 # To/CC/BCC normalization + validation
  hook.class.php                       # hook handlers
  tickettab.class.php                  # injects UI into ticket page
  log_tab.class.php                    # audit log tab on the ticket
front/
  compose.php                          # compose form entry
  send.php                             # send entry (POST)
  forward.php                          # forward form
  log.php                              # audit log list/filter
  log_entry.php                        # single audit log detail view
ajax/
  upload.php                           # attachment upload (chunked if needed)
  upload_image.php                     # inline image upload
  validate_recipients.php              # live email validation
  forward_preview.php                  # preview of forwarded email
sql/
  install.sql                          # glpi_plugin_ticketmailer_logs schema
  uninstall.sql                        # DROP TABLE
  update_*.sql                         # migrations (one per version)
locales/
  ticketmailer.pot           # template (extracted from source)
  ticketmailer.en.po         # English (default, complete)
  ticketmailer.de.po         # German (complete)
css/
  ticketmailer.css
js/
  composer.js                          # rich text editor wiring, To/CC/BCC chips
  forward.js
templates/
  compose.html.twig
  forward.html.twig
  log_entry.html.twig
```

### New DB table

```text
glpi_plugin_ticketmailer_logs
  id                  BIGINT PK
  tickets_id          INT  NOT NULL  FK glpi_tickets(id)
  users_id            INT  NOT NULL  FK glpi_users(id)            -- sender
  sent_at             DATETIME NOT NULL
  subject             VARCHAR(255) NOT NULL
  body_html           MEDIUMTEXT                                  -- nullable
  body_text           MEDIUMTEXT                                  -- nullable
  recipients_to       MEDIUMTEXT NOT NULL                         -- JSON array
  recipients_cc       MEDIUMTEXT                                  -- JSON array
  recipients_bcc      MEDIUMTEXT                                  -- JSON array, server-side only
  attachments         MEDIUMTEXT                                  -- JSON array of {filename, path, mime, size}
  inline_images       MEDIUMTEXT                                  -- JSON array of {cid, path, mime}
  status              ENUM('sent','failed') NOT NULL
  error_message       TEXT                                        -- nullable, only when failed
  remote_msg_id       VARCHAR(255)                                -- Message-ID of the SMTP send
  INDEX (tickets_id, sent_at)
  INDEX (users_id)
```

### New GLPI plugin metadata

```php
// in setup.php
define('PLUGIN_TICKETMAILER_VERSION', '1.0.0');
define('PLUGIN_TICKETMAILER_MIN_GLPI', '10.0.0');
define('PLUGIN_TICKETMAILER_MAX_GLPI', '10.99.99');
```

### Existing surfaces touched (read-only or hook-augmented)

- `glpi_tickets` — read access only, no schema change.
- GLPI mailer config (`$CFG_GLPI['smtp_*']`) — read access only.
- Ticket display page — adds a new tab "Outbound email" and a
  compose-action button on the standard ticket view (no layout
  change to the standard tabs).

## Acceptance criteria

A1. `setup.php` declares `PLUGIN_TICKETMAILER_VERSION`,
    `PLUGIN_TICKETMAILER_MIN_GLPI`, `PLUGIN_TICKETMAILER_MAX_GLPI`,
    and registers the plugin via `$PLUGIN_HOOKS['csrf_compliant']`,
    `$PLUGIN_HOOKS['post_init']`, `$PLUGIN_HOOKS['item_purge']`
    (for cascading delete of logs when a ticket is removed),
    `$PLUGIN_HOOKS['plugin_ticketmailer_install']`,
    `$PLUGIN_HOOKS['plugin_ticketmailer_uninstall']`.

A2. `hook.php` defines the functions
    `plugin_ticketmailer_install` (creates the audit table),
    `plugin_ticketmailer_uninstall` (drops it), and
    `plugin_ticketmailer_post_init` (registers the ticket tab).

A3. After install, a new tab **"Outbound email"** appears on the
    ticket page (alongside the standard tabs).

A4. The compose form exposes three recipient fields — **To**, **CC**,
    **BCC** — each accepting multiple addresses entered as chips
    (tokenized on `,` or `;` or `[Enter]`, validated against
    RFC 5322 e-mail grammar).

A5. The compose form provides a rich-text body editor that produces
    both HTML and a plain-text alternative (`multipart/alternative`).

A6. The compose form supports file attachments via picker and inline
    images via drag-and-drop and paste; both upload via dedicated
    AJAX endpoints and become MIME parts (`multipart/mixed` for
    attachments, inlined `Content-ID` for images).

A7. Empty To/CC/BCC (i.e., To empty AND CC empty AND BCC empty)
    blocks the send with a field-level validation error; BCC
    without To AND CC is also blocked (RFC 5321 § 3.3 requires at
    least one non-empty RCPT envelope).

A8. Submitting valid form data sends a single email via the GLPI
    mailer (PHPMailer constructed from `$CFG_GLPI['smtp_*']`) and
    writes one row to `glpi_plugin_ticketmailer_logs` with
    `status='sent'`, the rendered body, the normalized recipient
    lists, attachment/image JSON, and the SMTP `Message-ID`.

A9. The send path does **not** call any of
    `Notification::raiseEvent`,
    `NotificationEvent::raiseEvent`,
    `NotificationTarget::*::getNotificationTargets`, or
    `NotificationMailing::send`; the verifier (check #8) enforces
    this. Verified manually by sending one email in a test
    instance and observing zero entries in GLPI's
    `glpi_events` notification log for the affected ticket.

A10. SMTP failure (wrong host, bad credentials, timeout) does not
     block the response: the email is still recorded in the audit
     log with `status='failed'` and `error_message` populated; the
     UI shows the failure inline.

A11. A "Forward" action on the ticket (button in the ticket actions
     menu) opens a compose form pre-filled as an email-style
     forward of the full ticket history. The subject is set to
     `Fwd: [#<tickets_id>] <ticket title>` (e.g.
     `Fwd: [#1234] Printer offline in lab 2`); the body is a
     brief preamble (`Fwd ticket #N: <title>`) followed by the
     ticket description and all followups in chronological order,
     each entry preceded by a quoted header block
     (`On <date>, <author> wrote:`) rendered as a blockquote;
     To/CC/BCC are empty and the user fills them before sending.
     There is no "last message" sub-mode.

A12. The "Outbound email" tab on the ticket page lists audit log
     entries for that ticket, newest first, showing sender, sent_at,
     subject, recipient count, and a status pill (sent/failed). A
     click opens a **full read-only detail view** of the composed
     email: To/CC + BCC count, rendered HTML body, plain-text
     alternative, attachment list with download links, and inline
     image list with `cid:` references.

A13. Purging a ticket cascades and removes all rows from
     `glpi_plugin_ticketmailer_logs` for that ticket
     (`item_purge_ticket` hook).

A14. `docker-compose.yml` brings up:
     - `glpi`: GLPI 10.x with the plugin mounted at
       `plugins/ticketmailer`.
     - `db`: MariaDB.
     - `mailpit`: local SMTP/IMAP capture with a web UI on
       `:8025`. GLPI is pre-configured (via env or first-run
       wizard + a setup script) to point at `mailpit:1025`.
     All three services start and `glpi` is reachable at
     `http://localhost:8080` (or whatever port the compose file
     chooses).

A15. README documents: install, configure, "how the audit log
     appears on the ticket", "how to view a sent email", and
     "limitations" (no inbound mail; no drafts in v1).
A16. i18n (English default, German complete):
     - All user-facing strings (UI labels, button text, log entry
       descriptions, error messages, and the rendered subject/body
       in the audit-log detail view) are wrapped in GLPI's `__()`
       translation function (or `__s()` / `__n()` for sprintf / plurals).
     - The plugin ships with `locales/ticketmailer.pot` (template),
       `locales/ticketmailer.en.po` (English, default — every key
       translated), and `locales/ticketmailer.de.po` (German —
       every key translated).
     - Missing translations fall back to the source string
       (English); no `__()`-key leaks visible in either language.

## Invariants and edge cases

- **Independence from native notifications** (A9) is structural,
  not just behavioral: even if GLPI's notification engine changes,
  the plugin's emails must not appear in it.
- **BCC secrecy**: BCC addresses are never serialized into
  the HTML/text body, never copied to the read-only detail view
  beyond the count, and are stored only in the server-side log
  row.
- **UTF-8** is assumed everywhere; subject and body are
  base64/quoted-printable encoded as needed.
- **Attachment size**: per-file limit matches GLPI's
  `$CFG_GLPI['upload_max_size']`; the verifier does not check this
  but the implementation must respect it.
- **Invalid email grammar** is rejected at the form level;
  duplicates within the same field are de-duplicated; duplicates
  across fields are kept (caller's intent) but the audit log
  records them faithfully.
- **Empty body**: blocked (UI requires non-empty body).
- **Permission gate**: only users with `UPDATE` right on the
  ticket (i.e. can write followups) see the compose / forward
  buttons. ACL is enforced via GLPI's `Ticket::canUpdateItem()`.
- **Concurrent sends** to the same ticket are allowed; the audit
  log is append-only and ordered by `sent_at`.
- **DB migrations**: install is idempotent (uses `CREATE TABLE IF
  NOT EXISTS`); updates use versioned `update_*.sql` files read
  from the plugin version constant.
- **Plugin uninstall** drops the table and removes any
  attachments stored under GLPI's `_files` directory
  (`plugin_ticketmailer/` subtree). It does not remove the
  rows from the `glpi_events` log, but this plugin never adds
  any.
- **i18n completeness**: every user-facing string goes through
  `__()` (or `__s()` / `__n()`). No hardcoded English or German
  literals in `templates/`, `front/`, `ajax/`, or `inc/` (except
  for technical identifiers, SQL column names, and audit-log
  keys). The English and German `.po` files are at parity — every
  string displayed by the UI appears in both files.

## Assumptions

- **GLPI 10.0+** with PHP 8.1+ is the target. Earlier versions are
  out of scope.
- A single plugin install covers the whole instance; multi-tenant
  concerns are out of scope.
- **TinyMCE** (bundled with GLPI 10) is the rich text editor.
  Confirmed by product owner (→ OQ5, Resolved). No alternate
  editor library is used; the implementation uses GLPI's
  `tinyMCE` JS API directly. HTML→text extraction is a server-side
  concern in `composer.class.php`.
- **No drafts in v1.** The compose form is single-shot: open, fill,
  send. Half-typed messages are lost on navigation. A draft endpoint
  is a v2 addition.
- The Docker image for GLPI is `diouxx/glpi` (or upstream
  `glpi/glpi`); the implementer may swap as long as the image
  supports plugin auto-mount and the resulting service runs on
  PHP 8.1+.
- A local mail capture (mailpit) is acceptable for end-to-end
  testing in dev; the production deployer will point GLPI at a
  real SMTP server and the same code path is expected to work.
- The plugin does not provide a config UI for SMTP — it always
  reads from GLPI's configuration.
- Audit log retention is unbounded in v1; a TTL or housekeeping
  job is a future addition.

## Resolved decisions (was: Open questions)

All five were answered before freeze. Decisions are LOCKED; the
implementer must not re-open them without surfacing a new conflict.

| #   | Question                                | Decision                                                                                                                                          | See    |
|-----|------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|--------|
| OQ1 | Forward mode (last message vs. full)     | **Email-style forward, full ticket history.** The "last message only" alternative from the original request is dropped in v1.                    | A11    |
| OQ2 | Forwarded subject prefix + format        | **`Fwd: [#<tickets_id>] <ticket title>`** (e.g. `Fwd: [#1234] Printer offline in lab 2`).                                                          | A11    |
| OQ3 | Drafts in v1                            | **No drafts.** Half-typed messages are lost on navigation. A draft endpoint may be added in v2.                                                  | A15    |
| OQ4 | Audit log detail view                    | **Full read-only view** of the composed email: To/CC + BCC count, rendered HTML body, plain-text alt, attachment list (downloadable), inline-image `cid:` references. | A12    |
| OQ5 | Rich text editor                        | **TinyMCE (GLPI-bundled).** No abstraction layer; the implementation uses GLPI's `tinyMCE` JS API directly.                                       | A5     |
| —   | i18n scope (confirmed at freeze review)  | **English (default) and German** at parity. Other languages are community contributions.                                                            | A16    |

## Verification contract

The single source of truth for pass/fail is
`.agent/contracts/ticket-mailer/score.sh`. It runs deterministic
local checks only — no Docker, no real GLPI, no network. It exits
non-zero on any failed check.

It encodes, in order:

1. Required files present (`setup.php`, `hook.php`, `composer.json`,
   `docker-compose.yml`, `README.md`, `.gitignore`).
2. PHP syntax (`php -l`) on every `*.php` file under the repo,
   skipped gracefully when `php` is not installed.
3. `composer.json` parses as JSON.
4. `docker-compose.yml` parses via `docker compose config` when
   `docker` is installed; skipped otherwise.
5. `setup.php` declares `PLUGIN_TICKETMAILER_VERSION` and at least
   one `PLUGIN_TICKETMAILER_*` constant.
6. Required hooks defined: `plugin_ticketmailer_install`,
   `plugin_ticketmailer_uninstall`, `plugin_ticketmailer_post_init`
   (in `setup.php` or `hook.php`).
7. `sql/install.sql` (or any `sql/*.sql`) contains the audit table
   with required columns: `sender_id`/`users_id`, `sent_at`,
   `subject`, `recipients_to`, `recipients_cc`, `recipients_bcc`,
   `status`, `tickets_id`.
8. No reference to `Notification::raiseEvent`,
   `NotificationEvent::raiseEvent`,
   `NotificationTarget::getNotificationTargets`, or
   `NotificationMailing::send` in `inc/`, `front/`, `ajax/`.
   (Independence from the native notification engine.)
9. The plugin's email send path references
   `$CFG_GLPI['smtp_*']` or
   `Config::getConfigurationValue('core', 'smtp_*')`.
10. No hardcoded password, host/port/credential strings in
    `inc/`, `front/`, `ajax/`, `setup.php`, `hook.php`
    (positive lookbehind for `password|passwd|secret|token`).
11. To/CC/BCC fields referenced in compose form code
    (e.g. `recipients_to`, `recipients_cc`, `recipients_bcc`,
    or tokenized form keys).
12. Forward UI present: at least one file under `front/`,
    `templates/`, or `ajax/` references `forward` and one of
    `last_message` / `full_history` / `last-message` /
    `full-history`.
13. `.gitignore` covers `vendor/`, `node_modules/`, `.env`,
    `_files/`, `_cache/`, `_log/`, `_sessions/`.

A passing run prints `PASS: N, FAIL: 0` and exits 0.

### Manual / out-of-band checks (not encoded in score.sh)

These are not automatable in a single-shot verifier without
spinning up a full GLPI + Docker + mailpit stack, so they live in
the README's "smoke test" section and must be executed by the
implementer before declaring the slice done:

- M1. Bring up `docker compose up -d`; verify all services healthy.
- M2. Open GLPI, install the plugin, log in as a technician.
- M3. Open a ticket, click "Compose", fill To/CC/BCC + body +
  attachment, send. Confirm a row appears in
  `glpi_plugin_ticketmailer_logs` with `status='sent'`.
- M4. Open mailpit at `:8025`; confirm the message arrived with
  the expected To/CC/BCC and rendered body.
- M5. Click "Forward"; verify subject is `Fwd: [#<id>] <title>`,
  body is the description + all followups in chronological order
  each in a quoted block, and recipient set is empty.
- M6. Purge a ticket with logs; verify cascading delete removed
  its log rows.
- M7. Trigger an SMTP failure (point GLPI at a wrong port) and
  send; verify a row with `status='failed'` and an error
  message is written, and the UI surfaces the failure.
- M8. Switch GLPI user language to German; verify every plugin
  label (compose form, forward button, audit log tab, log entry
  detail, error messages) renders in German. Switch back to
  English; verify English. No `__()`-key leaks visible in either
  language.

## Gaps

What the verifier **cannot** check, and what the implementer
must verify by other means:

- **No runtime test** — score.sh does not bring up GLPI, mailpit,
  or any service. M1–M8 above are the runtime proof.
- **No UI rendering** — the verifier cannot see whether the rich
  text editor mounts, chips work, or the layout breaks at
  narrow widths. Manual browser test required.
- **No real SMTP** — the verifier cannot exercise the actual
  PHPMailer send. Manual smoke test against mailpit required.
- **No ACL check** — the verifier cannot see whether the
  compose/forward buttons are correctly hidden from
  read-only users. Manual test with at least two roles
  (technician, observer) required.
- **No idempotency under concurrent install** — the verifier
  cannot prove that re-running the install SQL is safe; the
  implementation must use `CREATE TABLE IF NOT EXISTS` and the
  implementer must test.
- **No upgrade path proof** — `update_*.sql` files are not
  exercised. Manual test of a v0 → v1 upgrade required.
- **No i18n completeness beyond parse** — the verifier checks that
  the `.po` files parse and that `__()` is used. Translation parity
  (every English key has a German counterpart, and vice versa) is
  partially covered by the invariant but ultimately a manual
  concern. M8 is the runtime proof.
- **GLPI's own upgrade of internal mailer classes** could
  break the plugin — beyond the verifier's horizon. ADRs and a
  pinned `composer.json` requirement (`"glpi/glpi": "^10.0"`)
  mitigate this.
- **The bare+worktree repo init** is a setup prerequisite, not
  in scope for the plugin verifier. The implementer follows
  global `AGENTS.md` and `wt`/`wt-clone` before running
  score.sh.
