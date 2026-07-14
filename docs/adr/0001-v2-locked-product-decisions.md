# ADR 0001 — v2 locked product decisions

- **Status:** Accepted
- **Date:** 2026-07 (codified from planning T04–T06 + v2 contract)
- **Contract:** `.agent/contracts/glpi-ticket-email-client-v2/spec.md`
- **Planning:** `.agent/planning/glpi-ticket-email-client/` (T04, T05, T06, T08, T09)

## Context

`ticketmailer` began as an outbound compose + plugin audit tab (v1). v2 turns it into a ticket-timeline email reply client. Several product choices conflict with v1 non-goals or defaults and must stay stable across implementation churn.

## Decisions

### D1 — Reply policy (not core replacement)

Per entity / optional profile: email reply is `available`, `promoted`, or `hide_native`. No unsupported core patch. `hide_native` only via a verified GLPI extension point; without proof, keep native reply and treat policy as promoted/available — never DOM/CSS suppression.

### D2 — Timeline via ITILFollowup

Successful SMTP send creates one standard `ITILFollowup` with full outbound content and secure attachment links. Plugin audit remains the durable intent/outcome store and links `followups_id`.

### D3 — No duplicate native mail

Followup is created with `_disablenotif=1`. Composed mail is delivered only by the plugin’s single SMTP send. Compose path must not call GLPI notification-delivery APIs.

### D4 — Mailbox collision: warn + override

Match normalized recipients against active mail collector `login` values that are valid emails. Best-effort; aliases/forwarding/non-email logins out of scope. Match requires explicit CSRF-protected POST override; audit records override + matches.

### D5 — Actor defaults

Requesters default to **To**. Observers only default to **CC**. Assignees are not auto-added.

### D6 — Deliberate BCC visibility

BCC is supported. Full BCC addresses are visible to every user who may read the ticket (timeline + secure audit detail). Compose warns the sender. SMTP still omits BCC from visible To/CC headers. Hiding BCC from ticket readers is explicitly **not** a v2 goal.

## Consequences

- v2 contract supersedes conflicting v1 provisions (followup integration, ASSIGN→CC default, silent token drop, BCC count-only detail).
- Schema gains pending/sent/failed + timeline status, mailbox override fields, reply-policy table (`sql/install.sql`, `update-1.1.0.sql`).
- Incomplete send (SMTP ok, followup fail) is a first-class failure mode — no silent “success”.
- Agents and humans treat these as invariants; change requires a new ADR and contract update.

## Non-decisions (still out of scope)

IMAP/inbound · drafts · SMTP UI · guaranteed loop detection · core reply control replacement without proof.
