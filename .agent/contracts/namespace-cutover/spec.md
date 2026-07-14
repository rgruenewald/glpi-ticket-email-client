# Contract: GLPI Ticket Email Client namespace cutover

## Type

Data-preserving GLPI plugin namespace cutover.

## Goal

Replace the private runtime identity `ticketmailer` with the sole active identity `ticketmailer`, without changing outbound-email behavior or losing data.

| Surface | Required value |
|---|---|
| Visible GLPI product name | `GLPI Ticket Email Client` |
| Repository/project name | `glpi-ticket-email-client` |
| Plugin directory/shortname | `ticketmailer` |
| PHP lifecycle and hook functions | `plugin_ticketmailer_*` |
| PHP classes | `PluginTicketmailer…` |
| Translation domain | `ticketmailer` |
| Audit table | `glpi_plugin_ticketmailer_logs` |
| Reply-policy table | `glpi_plugin_ticketmailer_reply_policies` |
| Config table | `glpi_plugin_ticketmailer_configs` |
| Plugin document root | `GLPI_PLUGIN_DOC_DIR . '/ticketmailer'` |

Technical names contain no spaces. The visible product name must match exactly.

## Existing v2 invariants

This cutover does not change the v2 product contract:

- Compose/send uses GLPI-configured SMTP exactly once after a durable audit intent.
- It does not use GLPI notification-delivery APIs for composed mail.
- A successful send records one suppressed-notification `ITILFollowup`; SMTP success plus a failed followup remains an incomplete outcome.
- Recipient validation, BCC visibility, mailbox override, ticket ACLs, secure downloads, actor defaults, reply-policy precedence, and no-DOM/CSS native-reply hiding remain unchanged.
- No new dependency, SMTP UI, draft storage, IMAP, queue/retry worker, core patch, or compatibility architecture is added.

## Required runtime cutover

### Bootstrap and lifecycle

1. Rename every `PLUGIN_TICKETMAILER_*` constant in `setup.php` to `PLUGIN_TICKETMAILER_*`.
2. Rename `plugin_version_ticketmailer`, `plugin_init_ticketmailer`, prerequisite/config callbacks, every lifecycle function in `hook.php`, post-init callback, item-purge callback, hook key, callback string, and asset registration to `ticketmailer`.
3. `plugin_version_ticketmailer()['name']` equals exactly `GLPI Ticket Email Client`.
4. Change every active global class reference from `PluginTicketmailer…` to `PluginTicketmailer…`, including `Plugin::registerClass()` string references, static calls, and controllers.
5. Class source files remain suffix-named where GLPI naming supports it; do not rename a file solely because its class prefix changed. Remove no valid file merely for cosmetic symmetry.
6. Rename active technical strings in controllers, Twig, JavaScript, CSS, Composer metadata/autoload metadata, Docker mount paths, test setup, active verifier, active skills, README, CONTEXT, and ADR/product documentation.
7. Rename `css/ticketmailer.css` to `css/ticketmailer.css` and its GLPI hook registration. Review CSS/JS selectors and IDs individually: change only namespaced plugin identifiers, not semantic UI vocabulary.

## Greenfield schema

`sql/install.sql` creates exactly these plugin tables and no `ticketmailer` table:

```text
glpi_plugin_ticketmailer_logs
glpi_plugin_ticketmailer_reply_policies
glpi_plugin_ticketmailer_configs
```

It represents the complete current schema: audit fields, timeline/mailbox fields, reply-policy fields, and all entity configuration fields. `sql/uninstall.sql`, runtime queries, and active tests use only the new names. Historic numbered migrations must not be executed against a newly created new-namespace schema.

## Legacy migration source

Old identifiers are allowed only inside a bounded migration helper, migration tests/fixtures, and precise upgrade documentation:

```text
glpi_plugin_ticketmailer_logs
→ glpi_plugin_ticketmailer_logs

glpi_plugin_ticketmailer_reply_policies
→ glpi_plugin_ticketmailer_reply_policies

glpi_plugin_ticketmailer_configs
→ glpi_plugin_ticketmailer_configs

GLPI_PLUGIN_DOC_DIR . '/ticketmailer'
→ GLPI_PLUGIN_DOC_DIR . '/ticketmailer'
```

No alias class, wrapper, re-export, dual hook registration, or long-lived parallel runtime name is permitted.

## Migration algorithm

The new `plugin_ticketmailer_install()` owns this route.

### Preflight

Before copying, moving, deleting, or dropping anything:

1. Create/verify complete new schema idempotently.
2. Determine the legacy table state. If any legacy table exists, all three must exist; a partial set is a safe failure.
3. Verify every legacy table can map every persisted column to its destination, including IDs, JSON/text values, audit status/timeline columns, configs, and nullable `profiles_id` reply policies.
4. If source tables exist, all destination tables must be empty. Any source plus non-empty destination is a conflict; do not merge, overwrite, or delete either side.
5. Validate file-root state. Eligible state is legacy root only. Both roots, a destination collision, or source tables plus a target-only file root is a conflict.

### Copy and validate

1. Use GLPI DB APIs and explicit constant column maps. Do not interpolate request-controlled SQL.
2. Copy all rows, preserving primary keys and every persisted column.
3. Verify source/destination row counts and each row’s full mapped content before continuing.
4. Move the document root only after database equivalence succeeds. Prefer same-filesystem `rename()`. A cross-device fallback must copy to a unique staging root, verify every regular file and safe relative path, atomically promote the staging root, then remove source.
5. On copy, validation, move, or cleanup failure: log a safe GLPI error, return false, and never report success.

### Completion and repeat behavior

1. Drop legacy tables only after database equivalence and file migration succeed; verify each drop.
2. Successful state is new tables/new root with no legacy source. A second install is a no-op.
3. DDL and filesystem operations cannot be treated as one transaction. If interruption leaves both source and destination, later install detects a conflict and stops visibly. It must not guess, silently resume, merge, overwrite, or discard data.
4. Recovery is restore from the pre-upgrade backup or administrator-led resolution of the reported conflict. No permanent migration-marker table is added; greenfield schema remains the three named tables.

## GLPI registry transition

GLPI 10.0.7 source establishes that plugin directory drives `plugin_version_<directory>` discovery and `plugin_<directory>_install()` invocation. Thus `ticketmailer` is a new registry identity.

Deployment procedure:

1. Back up GLPI database and legacy plugin document root.
2. Deploy under `plugins/ticketmailer`; do not leave `ticketmailer` running in parallel.
3. Install and enable **GLPI Ticket Email Client** through GLPI, causing `plugin_ticketmailer_install()` to run.
4. Validate migrated audit data, entity configs, profile policies, and attachment storage.
5. Do **not** call legacy `plugin_ticketmailer_uninstall()`: it currently removes the legacy tables/files required as migration sources.
6. Do not directly update GLPI registry tables. If an old registry entry remains after successful cutover, leave it inactive pending a GLPI-supported administrator/maintainer cleanup procedure; document this limitation precisely.

## Localization

- All active `__()`, `_n()`, `_sn()`, `_x()`, and equivalent calls use domain `ticketmailer`.
- Rename/update POT and EN/DE PO catalogs to the `ticketmailer` identity and regenerate tracked `en_GB.mo`/`de_DE.mo` through the repository’s proven toolchain.
- GLPI loads MO files from `<plugin-directory>/locales/`; the generic MO names remain `en_GB.mo` and `de_DE.mo`.
- Keep historical v1 contracts and historical planning records as historical evidence; they are not active runtime references.

## Required tests

1. Exact new identity: visible name, bootstrap callbacks, hook keys, class prefix, and no active old runtime callback.
2. Greenfield install yields only the three `ticketmailer` tables and target file root.
3. Detection requires all three legacy tables, including reply policies.
4. Successful migration preserves an audit row, entity configuration, profile-bound reply policy, all IDs, and document storage.
5. Repeated successful install makes no duplicate/copy mutation.
6. Existing target data/files plus legacy source is a hard conflict with neither source nor target modified.
7. Injected database/file failure and mixed interrupted state return a visible failure with no silent data loss.
8. Existing v2 compose/send/timeline/ACL/recipient behavior still passes under renamed identity.
9. Locale POT/EN/DE parity and tracked MO output are consistent.

## Required verification

1. Full project verify skill.
2. Updated active v2 `score.sh` with exit 0.
3. Focused migration/identity PHPUnit tests and affected suite.
4. `php -l` for each changed PHP file.
5. Fresh-install smoke.
6. Seeded upgrade smoke with legacy logs, configs, reply policies, and files; then repeat migration and assert no duplicates.
7. Final scoped scan for `ticketmailer`. Every remaining result must be bounded migration code, migration test/fixture, explicit upgrade documentation, or historical v1/planning material. Any active code, SQL, asset, locale, Docker, test, verifier, or product document result fails.

## Delivery constraints

Implementation uses a new Herdr worktree and produces a transferable patch only. It does not commit, push, merge, reset, clean, or modify user work in `main`.
