---
name: glpi-plugin-runtime
description: GLPI 10 plugin bootstrap, hooks, install/migrate, class map, and docker mount for ticketmailer.
---

# Skill: glpi-plugin-runtime

## When to use

- Changing `setup.php`, `hook.php`, install/uninstall, migrations
- Adding hooks, tabs, rights checks, or new `inc/*.class.php` types
- Docker / local GLPI mount issues
- Version bumps or plugin enable/install flow

## Plugin identity

| Item | Value |
|---|---|
| Directory name | `ticketmailer` (must match GLPI plugins path) |
| Version defines | `PLUGIN_TICKETMAILER_VERSION`, `MIN/MAX_GLPI` in `setup.php` |
| Gettext domain | `ticketmailer` |
| Composer | `ticketmailer/ticketmailer`, type `glpi-plugin` |

## Bootstrap rules

- GLPI loads `setup.php` inside a closure → register `$PLUGIN_HOOKS` **inside** `plugin_init_ticketmailer()`, not at file top-level.
- Lifecycle install/uninstall/check live in `hook.php` (and helpers), not scattered.
- Mark CSRF compliance via `$PLUGIN_HOOKS['csrf_compliant']['ticketmailer'] = true`; still validate tokens on POST handlers.
- Assets: `add_css` / `add_javascript` hooks point at `css/` and `js/`.
- Tabs and late registration: `post_init` → `plugin_ticketmailer_post_init`.
- Ticket purge cascade: `item_purge` on `Ticket`.

## Class map (inc/)

| Class | Role |
|---|---|
| `PluginTicketmailer` | descriptor |
| `PluginTicketmailerConfig` | read GLPI `smtp_*` |
| `PluginTicketmailerMailer` | PHPMailer send |
| `PluginTicketmailerComposer` | compose payload / HTML→text |
| `PluginTicketmailerForwarder` | full-history forward body |
| `PluginTicketmailerAudit` | audit CRUD / intent |
| `PluginTicketmailerRecipients` | parse/validate To/CC/BCC |
| `PluginTicketmailerMailboxGuard` | collector login collision |
| `PluginTicketmailerReplyPolicy` | entity/profile modes |
| `PluginTicketmailerTimeline` | `ITILFollowup` write |
| `PluginTicketmailerTicketTab` / `LogTab` | ticket UI tabs |
| `PluginTicketmailerHook` | lifecycle helpers |

Naming: GLPI plugin style `PluginTicketmailer*`, files `inc/<name>.class.php`.

## Schema / migrate

- Greenfield: `sql/install.sql` (logs + reply_policies, v2 columns).
- Upgrade: `sql/update-1.1.0.sql` (and future `update-X.Y.Z.sql`).
- Uninstall: `sql/uninstall.sql`.
- Install path must leave schema equivalent to latest `install.sql`.
- Table prefix pattern: `glpi_plugin_ticketmailer_*`.

## Local runtime

```bash
docker compose up -d
# GLPI http://localhost:8080  (glpi/glpi)
# mailpit http://localhost:8025
# plugin mounted at /var/www/html/plugins/ticketmailer
```

Host install into an existing GLPI:

```bash
cp -R . /var/www/glpi/plugins/ticketmailer
php bin/console plugin:install ticketmailer
php bin/console plugin:enable ticketmailer
```

SMTP for the plugin always comes from GLPI core config (docker sets host `mailpit:1025`).

## Do not

- Ship a plugin-side SMTP settings form
- Patch GLPI core
- Register hooks at `setup.php` top-level
- Invent a second autoload layout beside existing `inc/` classes
