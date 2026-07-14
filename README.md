# GLPI Ticket Email Client

A GPL-3.0-or-later plugin for **GLPI 10** that lets authorized ticket agents compose and send a ticket-context email without using GLPI's notification delivery pipeline.

Ticketmailer sends one SMTP message through GLPI's existing mail configuration, keeps a durable audit record, and creates one notification-suppressed `ITILFollowup` in the ticket timeline.

> **Compatibility:** PHP 8.1 or later; GLPI 10.0.x. The implementation is verified against GLPI 10.0.7. Marketplace publication is not implied by this repository.

## Features

- Inline **Email reply** action beside GLPI's native **Answer** control.
- Rich HTML email using GLPI's bundled editor.
- To, CC, and BCC recipients; GLPI-user autocomplete; strict server-side address validation.
- Requesters prefilled in To and observers prefilled in CC. Assignees are not added automatically.
- Normal attachments, server-validated inline images, and optional public ticket-history content and attachments.
- SMTP delivery through GLPI core configuration only; no plugin SMTP settings and no GLPI notification-engine delivery.
- Durable send audit and one standard `ITILFollowup` with `_disablenotif=1` after a successful SMTP send.
- Per-entity compose preferences: subject prefix, signature, ticket waiting status, timeline order, auto-open reply form, and recipient-autocomplete email visibility.
- English and German user-interface translations.

## Important security and privacy behavior

Read this section before installing the plugin.

- The plugin stores the complete **To, CC, and BCC** recipient lists in the audit record and timeline followup. Every user permitted to read the ticket can see BCC recipients. BCC is not private within GLPI.
- Outgoing SMTP headers do not expose BCC recipients in To or CC headers.
- Ticket attachments and outbound audit attachments are served only through ticket-read-authorized routes. Filesystem paths are not rendered in the UI.
- Recipient addresses matching an active GLPI mail collector login trigger a warning. Sending requires an explicit confirmation. This is best effort: aliases, forwarding, and non-email collector logins cannot be detected.
- Sending is deliberately single-attempt. There is no automatic retry, resend queue, draft storage, or inbound-mail processing.
- SMTP success without a successful timeline followup is recorded as an incomplete send. The plugin does not retry SMTP in that case.

## Requirements

- GLPI 10.0.x.
- PHP 8.1 or later.
- A working GLPI core SMTP configuration.
- A GLPI user with permission to update the ticket or add followups. Ticket readers may view the audit log and download recorded outbound attachments.
- Database permissions sufficient for GLPI plugin installation and upgrade.

## Installation

1. Download a release archive or clone this repository.
2. Place the source in the GLPI plugins directory as `ticketemailclient`:

   ```text
   <glpi-root>/plugins/ticketemailclient
   ```

3. Ensure the web-server user can read the plugin files and write GLPI's configured document directory.
4. From the GLPI root, install and enable the plugin:

   ```bash
   php bin/console plugin:install ticketemailclient
   php bin/console plugin:enable ticketemailclient
   ```

5. Configure SMTP under GLPI's core mail settings. GLPI Ticket Email Client has no SMTP host, credentials, or transport settings of its own.
6. Open a ticket as an authorized agent and verify that **Email reply** appears beside **Answer**.

### Upgrade

1. Back up the GLPI database and document directory using your normal GLPI maintenance procedure.
2. Replace the plugin directory with the new release while preserving the directory name `ticketemailclient`.
3. Run GLPI's plugin update flow from the administration UI or CLI.
4. Confirm that the plugin reports version `1.4.0` and execute the smoke checks below.

The installer applies the versioned database migrations included in `sql/`. Do not edit plugin tables manually during an upgrade.

## Configuration

### SMTP

Configure SMTP only in GLPI core settings. GLPI Ticket Email Client reads GLPI's configured SMTP host, port, authentication, TLS mode, and certificate-verification setting. It does not add an SMTP configuration page and does not bypass GLPI's transport configuration.

### Per-entity preferences

A GLPI administrator can open the plugin configuration page and choose an entity. The following settings are available:

- **Ticket subject prefix** — use `%d` for the ticket ID; default `[#%d]`.
- **Email signature** — rich HTML; GLPI Ticket Email Client generates the plain-text alternative.
- **Set ticket status to waiting after a successful email send** — enabled by default.
- **Show newest timeline entries first** — enabled by default.
- **Open the Email reply form when a ticket is opened** — enabled by default.
- **Show email addresses in recipient autocomplete** — enabled by default.

Reply-policy rows support exact entity/profile and entity-default precedence. The stored `hide_native` mode is intentionally treated as `promoted` until GLPI provides a verified extension point for hiding its native reply control. GLPI Ticket Email Client never hides native controls with DOM or CSS workarounds.

## Sending an email

1. Open a ticket and select **Email reply** beside **Answer**.
2. Add recipients to **To**, **CC**, and/or **BCC**. Separate manually entered addresses with a comma, semicolon, or Enter. BCC-only delivery is supported.
3. Enter a subject and non-empty message body.
4. Add files, paste or drop supported inline images, and optionally select public ticket attachments.
5. Enable **Attach public ticket history** only when the recipient should receive the ticket description and public followups. Private followups and their documents are never included.
6. Resolve any active-mailbox warning by reviewing the recipients and explicitly confirming the override only when appropriate.
7. Select **Send**. The interface prevents duplicate submissions while the request is in progress.

A successful send has both `status = sent` and `timeline_status = recorded` in the audit log. If SMTP fails, no successful-send followup is created. If SMTP succeeds but the followup cannot be created, the audit record remains available with `timeline_status = failed` and the result must be treated as incomplete.

## Data stored by the plugin

`glpi_plugin_ticketemailclient_logs` stores the ticket and sender IDs, timestamp, subject, HTML and plain-text body, complete recipient lists, attachment descriptors, mailbox-override evidence, SMTP result, message ID, and linked followup/timeline status.

Outbound files are stored under GLPI's plugin document directory. They are addressed by generated identifiers and resolved server-side only after ticket-read authorization.

Retention is not currently configurable. Align ticket deletion, database backups, and document-directory backups with your organization's privacy and retention policy.

## Non-goals

GLPI Ticket Email Client does not provide:

- inbound email processing, IMAP polling, or reply parsing;
- email drafts, scheduled delivery, a queue, or automatic retry;
- SMTP configuration or credentials separate from GLPI;
- a generic mail client or workflow engine;
- a GLPI core patch;
- a guaranteed mail-loop detector.

## Development and verification

The repository includes a local GLPI/MariaDB/Mailpit development stack:

```bash
docker compose up -d
```

- GLPI: `http://localhost:8080` (`glpi` / `glpi` in the development stack only)
- Mailpit: `http://localhost:8025`

Run the contract verifier from the repository root:

```bash
ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh
```

When PHP dependencies are installed, run the PHPUnit suite:

```bash
composer install
vendor/bin/phpunit
```

Before a release, manually verify one rich email with To/CC/BCC, normal and inline attachments, the mailbox-warning override, ticket-reader download access, an SMTP failure, and a followup-after-SMTP failure. Confirm Mailpit contains exactly one outbound message and no BCC header.

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
docker/     Local GLPI and Mailpit development images
```

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md) for development and contribution rules. See [SECURITY.md](SECURITY.md) for vulnerability reporting.

## License

Copyright © Ronny Gruenewald.

Licensed under the [GNU General Public License, version 3 or later](LICENSE).