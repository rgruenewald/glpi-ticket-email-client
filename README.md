# GLPI Ticket Email Client

A plugin for **GLPI 11**. It lets ticket agents send emails directly from a ticket.

Sent emails are shown in the ticket history and send log.

> **Compatibility:** PHP 8.2 or later; GLPI 11.0.x. Tested with GLPI 11.0.8.

## See it in GLPI

Write a formatted email from a ticket. The sent email stays visible in the ticket history.

<p align="center">
  <img src="docs/wiki/images/email-compose-form.png" width="52%" alt="Ticketmailer compose form in GLPI with To, CC, BCC, subject, rich-text body, ticket-history option, attachments, and Send action">
  <img src="docs/wiki/images/ticket-email-timeline.png" width="42%" alt="A delivered Ticketmailer message recorded as a standard followup in the GLPI ticket timeline">
</p>

The send log shows successful and failed attempts. Only ticket readers can view it.

<p align="center">
  <img src="docs/wiki/images/sent-email-log.png" width="100%" alt="Ticketmailer's sent-email audit list in GLPI showing send time, subject, recipient counts, and delivery status">
</p>

## Features

- Choose **Email** (default) or **Internal note** when replying from a ticket.
- Email keeps Ticketmailer's To/CC/BCC, signature, audit, and direct SMTP workflow.
- Internal note mounts GLPI's native private-followup form; it has no Ticketmailer recipients or generated signature and never enters Ticketmailer's audit/SMTP pipeline. GLPI's configured native followup notifications remain unchanged.
- Write formatted messages.
- Add files, images, and public ticket history.
- Use GLPI's existing mail settings.
- Keep sent emails in the ticket history and send log.
- Choose a ticket template for each entity.
- Available in English and German.

## Important security and privacy behavior

Read this section before installing the plugin.

- Everyone who can read a ticket can also see its BCC recipients.
- BCC recipients are hidden from recipients of the email.
- Only ticket readers can open stored attachments.
- A warning appears if an address matches a GLPI mail collector.
- The plugin sends each email only once. It does not retry automatically.
- A send is incomplete if the email was sent but could not be added to the ticket history. Do not send it again.

## Requirements

- GLPI 11.0.x.
- PHP 8.2 or later.
- A working GLPI core SMTP configuration.
- A GLPI user who can update tickets or add followups.
- Ticket readers can view the send log and stored attachments.
- Database permissions sufficient for GLPI plugin installation and upgrade.

## Installation

1. Download a release archive or clone this repository.
2. Place the source in the GLPI plugins directory as `ticketmailer`:

   ```text
   <glpi-root>/plugins/ticketmailer
   ```

3. Ensure the web-server user can read the plugin files and write GLPI's configured document directory.
4. From the GLPI root, install and enable the plugin:

   ```bash
   php bin/console plugin:install ticketmailer
   php bin/console plugin:enable ticketmailer
   ```

5. Configure SMTP under GLPI's core mail settings. GLPI Ticket Email Client has no SMTP host, credentials, or transport settings of its own.
6. Open a ticket as an authorized agent and verify that **Email reply** appears beside **Answer**.

### Upgrade

1. Back up the GLPI database and document directory using your normal GLPI maintenance procedure.
2. Replace the plugin directory with the new release while preserving the directory name `ticketmailer`.
3. Run GLPI's plugin update flow from the administration UI or CLI.
4. Confirm that the plugin reports version `2.5.0` and execute the smoke checks below. <!-- x-release-please-version -->

The installer applies the versioned database migrations included in `sql/`. Do not edit plugin tables manually during an upgrade.

## Configuration

### SMTP

Configure email delivery in GLPI. The plugin has no separate mail settings.

### Per-entity preferences

A GLPI administrator can open the plugin configuration page and choose an entity. The following settings are available:

- **Ticket notification template** — choose one ticket template for each entity.
  - Child entities can inherit it.
  - It fills the subject and adds an editable signature.
  - Recipients still come from the email form.
  - Users only see data they are allowed to view.
- **Set ticket status to waiting after sending** — enabled by default.
- **Show newest ticket entries first** — enabled by default.
- **Hide GLPI native Answer action** — disabled by default. When enabled, the plugin uses GLPI 11's supported `timeline_answer_actions` hook to remove the duplicate action-bar entry. The native followup form remains mounted, so **Internal note** keeps GLPI's private followup lifecycle.

## Email or Internal note

Open **Email reply**, then choose a mode. **Email** is selected by default. Each mode keeps its own mounted draft and attachments when switching.

**Internal note** uses GLPI's native private-followup form, with private visibility enforced. Native permissions, CSRF, editor/uploads, templates, source, pending reasons/status, timeline/history, merged tickets, and notification configuration remain authoritative.

## Sending an email

1. Open a ticket and select **Email reply**.
2. Add at least one recipient.
3. Check the subject and message.
4. Add files or public ticket history if needed.
5. Review any warning shown by GLPI.
6. Select **Send** once.

The send is complete when the email appears in the ticket history.

If it does not appear, check the send log. Do not send it again until you know whether it was delivered.

## Data stored by the plugin

The plugin stores sent messages, recipients, files, delivery results, and ticket-history links.

Only ticket readers can open this data. Set suitable backup, deletion, and retention rules for your organization.

## Non-goals

GLPI Ticket Email Client does not provide:

- inbound email processing, IMAP polling, or reply parsing;
- email drafts, scheduled delivery, a queue, or automatic retry;
- SMTP configuration or credentials separate from GLPI;
- a generic mail client or workflow engine;
- a GLPI core patch;
- a guaranteed mail-loop detector.

## Development and verification

The shared local stack provides GLPI 11.0.8, MariaDB and Mailpit. No external SMTP
account is needed. GLPI listens on http://127.0.0.1:8080 (test login `glpi / glpi`),
and captured mail on http://127.0.0.1:8025. No relay is configured. Use synthetic
data only. The helper explicitly selects the `rootless` Docker context.

```bash
scripts/dev up
scripts/dev test
scripts/dev e2e
```

`test` runs PHPUnit, JavaScript checks and the v2 structural gate. `e2e` installs
the pinned browser dependencies and runs real Chromium scenarios: composition,
attachment delivery/download, audit/timeline, denied access and SMTP failure.
The outage test restarts Mailpit, clearing its in-memory inbox.

Starting another worktree switches the read-only plugin source mount without
deleting the shared database. Runtime data and encryption keys persist in named
volumes. See [the E2E recipe](docs/verification/docker-e2e.md) for prerequisites,
rootless permissions, coverage limits and explicit reset instructions.

## Repository layout

```text
ajax/       Upload, recipient validation, and autocomplete endpoints
css/        Plugin styles
front/      GLPI front controllers
inc/        Plugin domain and integration classes
js/         Compose and ticket-timeline behavior
locales/    Gettext template and English/German catalogues
sql/        Fresh-install schema and upgrade migrations
templates/  Twig views
tests/      PHPUnit and browser-behavior tests
docker/     Local GLPI development image
```

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md) for development and contribution rules. See [SECURITY.md](SECURITY.md) for vulnerability reporting.

## License

Copyright © Ronny Gruenewald.

Licensed under the [GNU General Public License, version 3 or later](LICENSE).

### New email

In **Assistance → New email**, write a proactive message without first creating a ticket. **Send** creates the ticket in the entity selected in Sender, then sends and records the message. To recipients become requesters; CC recipients become observers. BCC recipients are visible to ticket readers but do not become observers. Sender lists authorized entities with their configured email address; its selection determines the ticket entity and signature without changing the active GLPI entity. This action requires ticket creation and update rights in the central interface. The entity signature is appended at send time. Cancelling creates no ticket. Ticket-template mandatory fields and business rules still apply; if creation or post-create authorization fails, no email is sent. Failed or uncertain delivery must be checked on the existing ticket before any manual retry. Incoming replies require a configured GLPI mail collector and reply routing; the ticket marker alone does not retrieve mail.
### Configurable ticket actions

Global settings select the buttons in Ticketmailer's grouped action bar: Answer,
Task, Solution, Document and Internal note. Task and Document open GLPI's native
forms. Solution opens the email modal with Solved enabled and Waiting disabled;
Internal note opens the same modal in private-note mode. Existing GLPI permissions
still apply. All buttons default to enabled. Hiding replaced native actions is
optional: GLPI's supported timeline descriptor hook hides only actions with an
enabled, permitted replacement. Disabled replacement buttons leave native actions
available. Other plugins and approval actions are not suppressed.

Answer templates, solution templates and followup source are independent global
UI switches. These affect only Ticketmailer's modal, not standalone GLPI forms.
The private-note form also follows the answer-template and followup-source switches.
Switches are stored in GLPI's plugin configuration namespace, without a schema migration.

To, CC and BCC suggest active GLPI users by login, first name, last name or default
email address after two characters. Results respect active entity visibility,
including recursive profile assignments; free email addresses remain supported.
Suggestions show the user name and email; selected chips show the user name.

The action bar uses short localized labels and native solid GLPI buttons.
Enabled replacements automatically remove duplicate native menu entries through
GLPI descriptors; disabled replacements keep their native entries. Settings are
grouped into Ticket actions, Email dialog and Behavior using native switches.
