# Installation and upgrades

[Home](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [Deutsch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-DE) · [Administrator guide](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administrator-Guide-EN)

## Requirements

- GLPI **11.0.x**, PHP **8.2 or later**. GLPI 11.0.8 is the reference environment; GLPI 11.1 is not supported.
- An operational GLPI installation with outbound SMTP configured by an administrator.
- A backup of the GLPI database and plugin files before installation or upgrade.

Use a versioned [release package](https://github.com/rgruenewald/glpi-ticket-email-client/releases), not a branch snapshot, for production. These pages also describe unreleased changes, explicitly marked in the [version notes](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Version-Notes-EN).

## 1. Install the package

1. Download `ticketmailer-<version>.tar.gz` from the chosen GitHub release and verify its published SHA-256 checksum from `SHA256SUMS`.
2. Extract the package into GLPI’s `plugins/` directory, or the `marketplace/` plugin directory used by your installation.
3. Keep the directory name exactly `ticketmailer`. Do not nest it inside a second `ticketmailer/` directory or use a version suffix.
4. Ensure the web server can read the plugin files and write to GLPI’s normal files directory. Follow your installation’s existing ownership and permission policy; do not make the plugin world-writable.

Expected layout, using `plugins/` as an example:

```text
<glpi>/plugins/ticketmailer/
├── setup.php
├── hook.php
├── inc/
├── front/
├── ajax/
├── templates/
├── assets/
├── locales/
└── sql/
```

The package contains runtime files and compiled translations. Node.js, npm, Composer and source-build tooling are not required for package installation.

## 2. Install and enable in GLPI

1. Sign in as an administrator and open **Setup → Plugins**.
2. Find **GLPI Ticket Email Client**, choose **Install**, then **Enable**.
3. Configure outbound email in GLPI’s own settings. Ticketmailer has no SMTP settings page.
4. Review **Setup → General → Ticket email**, entity settings and profile permissions using the [administrator guide](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administrator-Guide-EN).

Menu names can vary with GLPI’s language. Missing translated strings may require GLPI’s usual cache refresh after an upgrade.

## 3. Verify before production use

Use a synthetic ticket and a controlled test mailbox, not customer data.

- An authorized user sees the configured reply action; a ticket reader without followup rights cannot send.
- To defaults to requesters, CC to observers, BCC is empty.
- Send one test message and verify it in the receiving mailbox.
- Verify one corresponding email followup and the send log. The email followup must not cause an additional native notification.
- If you use status switches, verify permitted transitions and that an unchecked switch preserves the status.
- On versions containing the post-2.6.1 attachment fix, verify that a sent upload appears among the ticket’s documents and can be selected in another reply.

Internal notes are different: native private-followup notifications may apply. Do not use a note test as proof that email replies suppress native notification delivery.

## Upgrade

1. Read the [version notes](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Version-Notes-EN) and the [full changelog](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/CHANGELOG.md).
2. Back up the database and plugin files; schedule the update so nobody sends from an old open dialog.
3. Disable the plugin, replace its files with the new package, then run the **Update** action if GLPI offers it.
4. Enable the plugin, refresh GLPI’s cache if required, and repeat the smoke checks above.

Do not click **Uninstall** as an upgrade step. Updating files does not retroactively convert old attachment records into ticket documents; check the release notes for the installed version.

## Disable or remove

Disabling the plugin stops its UI integration while retaining its tables. Uninstalling **drops plugin tables, including configuration, preferences and the audit log**. Preserve required audit data and backups before uninstalling. Ticket followups and GLPI documents are separate core records.

## Development setup

Contributors can use the repository’s Docker and SMTP-sink environment. See the rendered [repository installation guide](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/docs/INSTALLATION.md) and [contributing guide](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/CONTRIBUTING.md). These development tools are not a production deployment method.
