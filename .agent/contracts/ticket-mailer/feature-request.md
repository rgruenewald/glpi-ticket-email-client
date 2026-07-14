# Feature Request: GLPI Ticket Email Plugin (`ticketmailer`)

## Source

Original request (German, paraphrased + clarified):

> Initialize a new git bare repo with worktree support. Then create a
> specification. The goal is the development of a GLPI plugin that
> allows sending emails within a ticket, similar to a normal email
> client or competitor Zammad. It should be possible to define To, CC,
> and BCC. Multiple recipients should be insertable in each. This is
> completely independent of the GLPI native notification function via
> requester or watcher. It should support text, HTML text, images, and
> attachments. Additionally the email should be logged internally in
> GLPI (who, when, what, where, how) it was sent. Logging to or
> within the ticket. It should also be possible to forward a ticket
> (last message or entire history) via email. For testing, a local
> Docker-based GLPI instance should be installed. The email server
> setup should use the email server configured in GLPI.

## Goal

Build a GLPI plugin that adds an integrated email-compose UI inside a
ticket — independent of GLPI's native notification engine — with
To/CC/BCC, rich content (text + HTML + inline images + attachments),
per-send audit logging on the ticket, an email-style "forward
ticket" action (full ticket history, subject `Fwd: [#NNN] <title>`),
and full i18n for English (default) and German.

## Constraints

- **Repo layout**: bare + worktree per global `AGENTS.md` convention;
  plugin source lives in `glpi-plugin-email/main/` worktree.
- **GLPI target**: GLPI ≥ 10.0, PHP ≥ 8.1.
- **Mail transport**: must reuse GLPI's existing SMTP configuration
  (`$CFG_GLPI['smtp_*']` / `Config::getConfigurationValue('core', …)`)
  — no separate mailer config in the plugin.
- **Independence**: emails sent through this plugin must NOT enter
  GLPI's `NotificationEvent` / `NotificationTarget` pipeline
  (requesters and watchers stay untouched).
- **UI**: rich-text body editor, file picker for attachments, paste
  and drag-and-drop for inline images, multiple recipient chips
  (To/CC/BCC).
- **Audit**: per-send log entry on the ticket with sender, sent_at,
  subject, recipient lists (To/CC/BCC), delivery status, failure
  reason.
- **Forwarding**: email-style forward, single mode (full ticket
  history). Subject is `Fwd: [#<tickets_id>] <ticket title>`; body
  is a preamble + description + all followups, each in a quoted
  block. The "last message only" alternative from the original
  request is dropped in v1.
- **Testing env**: `docker-compose.yml` brings up GLPI plus a local
  SMTP capture (mailpit) so the full send path can be exercised
  without leaking real mail.
- **Plugin hygiene**: no hardcoded secrets, runtime artifacts in
  `.gitignore`, conventional GLPI plugin file layout.
- **i18n**: plugin ships locale files for English (default) and
  German. All user-facing strings use GLPI's `__()` function.

## Non-goals

- Inbound mail / IMAP polling / reply parsing.
- Replacing or extending GLPI's native notifications.
- Operating its own SMTP server.
- Migration tooling from Zammad or other products.
- First-class i18n beyond English and German.
- Webhooks, push, or non-email channels.
- Multi-tenant / multi-instance deployment concerns.

## Resolved decisions (was: Open questions)

All five were answered before freeze. Decisions are LOCKED; the
implementer must not silently re-open them.

- **OQ1** (forward mode) — **email-style forward, full ticket
  history**. The "last message only" alternative is dropped in v1.
  See spec § A11.
- **OQ2** (forward subject) — **`Fwd: [#<tickets_id>] <ticket
  title>`**, e.g. `Fwd: [#1234] Printer offline in lab 2`. See
  spec § A11.
- **OQ3** (drafts) — **no drafts in v1**. A draft endpoint may
  be added in v2.
- **OQ4** (audit detail view) — **full read-only view** of the
  composed email: To/CC + BCC count, rendered HTML body,
  plain-text alternative, attachment list (downloadable),
  inline-image `cid:` references. See spec § A12.
- **OQ5** (rich text editor) — **TinyMCE (GLPI-bundled)**. No
  abstraction layer; the implementation uses GLPI's `tinyMCE` JS
  API directly.

## Consulted context

- `skill://development-workflow` — pipeline, contract format,
  cleanup phase.
- `skill://grill-with-docs` — **N/A**: project has no `CONTEXT.md`,
  `CONTEXT-MAP.md`, or `docs/adr/` yet (greenfield).
- `skill://review-advice` — qualitative review of the spec draft;
  logged in **Review log** below.
- `skill://herdr-workflow` — applies to implementer, not contract
  authoring; flagged as prerequisite in `IMPLEMENTATION_PROMPT.md`.
- Global `AGENTS.md` — bare + worktree layout, `wt` tool usage, dev
  test guidance, `wt-clone` recommendation.
- GLPI plugin conventions (publicly known; cited for the verifier):
  `setup.php`, `hook.php`, `inc/*.class.php`, `front/*.php`,
  `ajax/*.php`, `locales/*.po`, `sql/*.sql`, namespace
  `GlpiPlugin\<Name>`, constants `PLUGIN_<NAME>_*`.
- GLPI mailer (publicly known): `$CFG_GLPI['smtp_host' | 'smtp_port'
  | 'smtp_username' | 'smtp_passwd' | 'smtp_mode' | 'smtp_check_certificate']`,
  `Toolbox::sendEmail()` / direct `PHPMailer` construction with these
  values.

## Review log

After the spec draft was written, a self-review pass
(`review-advice` lens) was performed. Findings and the resulting
adjustments:

- **HIGH (correctness)** — Verifier originally could not detect
  accidental use of GLPI's native notification engine. **Added**
  score.sh check #8: grep for `Notification::raiseEvent` /
  `NotificationEvent` / `NotificationTarget` in `inc/`, `front/`,
  `ajax/`. Now any such call fails the build.
- **HIGH (correctness)** — "Uses GLPI mailer" was vague.
  **Sharpened** to: plugin must reference `$CFG_GLPI['smtp_*']` or
  `Config::getConfigurationValue('core', 'smtp_*')`, AND must not
  define its own SMTP host/port/username/password config form.
  Encoded in score.sh check #9.
- **MEDIUM (data-safety)** — Audit table schema was unspecified.
  **Added** explicit column list under § Affected surfaces, encoded
  in score.sh check #7.
- **MEDIUM (clarity)** — "Forward" semantics were ambiguous.
  **Promoted** to § Open questions so it blocks implementation.
- **LOW (maintainability)** — `.gitignore` was not in original
  contract. **Added** score.sh check #13 covering `vendor/`,
  `node_modules/`, `.env`, `_files/`, `_cache/`, `_log/`,
  `_sessions/`.

Result: spec is **Frozen / Ready for implementation** (not yet
implemented). The 5 open questions have been resolved and
locked; the implementer may proceed.
