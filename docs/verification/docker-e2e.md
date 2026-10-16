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
