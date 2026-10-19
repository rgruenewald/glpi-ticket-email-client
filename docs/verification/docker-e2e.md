# Local GLPI end-to-end environment

## Scope and safety

Disposable development instance: GLPI 11.0.8, PHP 8.5, MariaDB 10.11 and Mailpit.
This does not demonstrate GLPI 10 compatibility. GLPI listens on
http://127.0.0.1:8080 and Mailpit on http://127.0.0.1:8025. No database or SMTP
port is published. No SMTP relay or forwarding is configured.

Default upstream test logins: `glpi / glpi` and `post-only / postonly`. These
are public fixture credentials, not production secrets. Never import real tickets,
mail credentials or personal data. Ignored browser traces may contain session
tokens. Mailpit stores mail in memory and loses it on restart.

## Prerequisites

Docker Engine with Compose, Node.js/npm, and the Linux `acl` package. On this
workstation the `rootless` Docker context and enabled user service are configured
for `ronny`; user lingering is enabled. The system daemon is unchanged. Docker
rootless setup selected the new context as default. The helper explicitly selects
it regardless of the current default.

On a fresh machine install the Docker rootless prerequisites, including `uidmap`
and `slirp4netns`, configure non-overlapping subordinate UID/GID ranges and run
`dockerd-rootless-setuptool.sh install`. Use `--force` only to install alongside
an existing system daemon. In a shell without XDG_RUNTIME_DIR, set it to
`/run/user/$(id -u)`. Enable `systemctl --user enable --now docker`. Do not copy
another user's UID ranges.

## Commands

Run in the worktree to test:

```bash
scripts/dev up
scripts/dev test
scripts/dev e2e
scripts/dev status
scripts/dev logs
scripts/dev stop
```

The first E2E run downloads pinned npm dependencies and Chromium. If browser
system libraries are missing, inspect `npx playwright install-deps chromium` and
install the required packages with administrator approval.

`up` builds images, prepares mount points and rootless read ACLs, and waits for
healthy services. The source is mounted read-only; runtime data lives in volumes.
No recursive chmod/chown touches the repository. `prepare` derives the host UID
for Apache (container UID 33) and grants read/traverse ACLs on those runtime paths.
Only plugin runtime directories and entry files receive ACLs. Git metadata,
.env, dependencies and other worktree files are not mounted in GLPI. Default
ACLs on runtime directories cover new files. Rerun `scripts/dev prepare` after restrictive editor
file replacements.

All worktrees share Compose project `ticketmailer`. Starting another worktree
switches the source mount while preserving the database and encryption key. Run
tests serially. Use this recipe's Compose file: older worktrees may restore older
stack settings. The PHPUnit service is opt-in rather than a permanently running
service.

## Automated coverage

- Real Chromium login, ticket view, native rich-text editor, To/CC/BCC, upload
  and send of a text attachment.
- Exactly one matching captured message, content and attachment checks, persisted
  sent audit and normal timeline followup.
- Authorized attachment download and denied anonymous compose/download.
- Self-service and read-only accounts denied composition, sending and uploads.
- Recursive entity access and its removal; direct foreign-entity compose, send,
  upload, preview, autocomplete, recipient validation and document access denied.
- Entity/profile policy precedence and hide_native-to-promoted fallback. These
  presentation modes do not introduce a send-denial policy.
- Public history and selected attachment in a new conversation, with private
  notes and unselected/private/foreign attachments excluded or rejected.
- Inline-image endpoint MIME validation and matching CID/image bytes in the
  received MIME message; non-image upload rejected.
- Mailbox warning, disabled send button, server-side rejection without override,
  and a successful explicit override persisted in the audit.
- SMTP outage records failed audit without a followup or delivery; Mailpit
  restarts in a finally block. This intentionally clears its captured mail.

Each run adds synthetic tickets, users, profiles, entities and audits without
deleting previous records. Temporary mail-collector rows are removed in finally
blocks; only fixture-created profile grants and policies are changed. Fixture
accounts have a public, local-only test password and must never be used outside
this loopback-only environment.
Mailpit synthesizes BCC headers from envelope recipients for its UI, so its raw
API cannot prove absence of BCC headers on the wire. That privacy invariant is
not claimed as covered here. The inline scenario exercises the real upload
endpoint, submits its returned identifiers, and inserts the CID using the native
editor source dialog. Clipboard/paste and the editor image-picker integration
are not covered, nor is rendering in Outlook or other external mail clients. Existing Node tests
check the duplicate-submit UI lock; the browser suite checks a single captured
delivery, not server-side idempotency.

Failures produce ignored `test-results/` screenshots and traces. Inspect with
`npx playwright show-trace test-results/<case>/trace.zip`.

## Persistence and reset

`stop` and `docker --context rootless compose down` preserve volumes. Restart
with `scripts/dev up`. Keep database and GLPI config volume together: config
contains the encryption key. Never delete either independently.

Only when intentionally discarding **all** synthetic tickets, audits, uploads,
configuration and database contents:

```bash
docker --context rootless compose down --volumes
scripts/dev up
```

This affects only the local Compose project on the rootless daemon. Never use a
global Docker prune to reset this environment.

## Interactive development

Run `make dev` in the worktree you want to test. It builds and prepares the
shared stack, prints the worktree path, streams logs and stays in the foreground.
Ctrl-C, SIGTERM and terminal hangup stop GLPI, MariaDB and Mailpit; named volumes
and test data are retained. SIGKILL, machine failure and forcibly killing only
Make cannot guarantee cleanup. Use `make stop` for recovery. The interactive
override disables automatic container restarts.

Use another terminal for `make test` or `make e2e`. Keep `make dev` running
until browser tests finish; stopping its database interrupts those tests.
Existing `scripts/dev up` remains detached for automation. Release integration
uses its own disposable stack and ports; no CI workflow is changed.
Do not start another worktree against the shared stack during the session.
Code is bind-mounted. Before GLPI starts, interactive mode reads the persisted
plugin version and blocks downgrades. Required installations/updates first back up
the database and config/files under ~/.local/state/ticketmailer-backups (outside
Git), then run the GLPI installation/update command and activate the plugin.
READY is printed only after the mounted version is installed and active.
Schema-relevant changes (SQL files, hook.php and inc/hook.class.php) are hashed.
A new or changed fingerprint triggers backup and a forced run of the existing
GLPI install/migration hook, even at the same product version. Only successful
application and activation record the fingerprint under
~/.local/state/ticketmailer-schema, keyed to the DB volume and plugin-table
creation identity rather than the worktree. Missing state causes safe reapplication.
No tracked version file is modified. This is not a schema downgrade mechanism;
older same-version branches may need a matching database backup or fresh database.
Previously recorded update-*.sql files may not be edited or removed; startup
rejects this because column guards can skip edits to an already applied migration.
Add a new guarded migration instead. On first adoption there is no historical
ledger, so existing migrations are assumed immutable; restore/reset the database
if their old application cannot be trusted.
Hooks must remain idempotent; new SQL migrations must be wired into migrateSchema.
Restart make dev after changing schema files while a session is running.
Concurrent interactive sessions are blocked; detached dev up is not covered by
this lock and must not be used against the shared instance at the same time.
Update failures stop services; backups remain for manual recovery.
Keep this backup directory outside synchronization and review retention manually.
