# Implementation Prompt

Copy-paste the block below into `/forge .agent/contracts/ticket-mailer/`
or a fresh task. The contract is the source of truth — this prompt
just makes the harness re-load it.

```text
Contract:       .agent/contracts/ticket-mailer/
Type:           Feature
Goal:           Build a GLPI plugin (`ticketmailer`) that adds an
                integrated email-compose UI inside a ticket with
                To/CC/BCC, rich text, inline images, attachments,
                per-send audit logging on the ticket, an email-style
                forward action (full ticket history, subject
                `Fwd: [#NNN] <title>`), and full i18n for English
                (default) and German. Must reuse GLPI's SMTP config
                and must NOT use GLPI's notification engine.

Locked files (DO NOT EDIT):
  - .agent/contracts/ticket-mailer/feature-request.md
  - .agent/contracts/ticket-mailer/spec.md
  - .agent/contracts/ticket-mailer/score.sh
  - .agent/contracts/ticket-mailer/allowed-files.txt
  - .agent/contracts/ticket-mailer/IMPLEMENTATION_PROMPT.md

Verifier entrypoint: .agent/contracts/ticket-mailer/score.sh
Success criterion:  ./score.sh prints "PASS: N, FAIL: 0" and exits 0.

Allowed files (implementation): see allowed-files.txt.
Anything outside allowed-files.txt is out of scope for this slice.

Pre-flight (MUST, in order):
  1. Per global AGENTS.md: convert the empty directory into a
     bare + worktree repo (use `wt-clone` or the documented manual
     conversion). The plugin source lives in the `main` worktree.
     This step is OUT of scope for score.sh.
  2. Load skill://herdr-workflow and follow its 4-step protocol
     for every code change.
  3. Per skill://development-workflow, smallest complete vertical
     slice first, no speculative abstractions.

Read first:
  - feature-request.md  (original ask, constraints, non-goals)
  - spec.md             (acceptance criteria, invariants, gaps)

Build:
  - setup.php, hook.php, composer.json, docker-compose.yml
  - inc/, front/, ajax/, sql/, locales/, css/, js/, templates/
  - README.md with install/usage and a "smoke test" section
    listing M1..M8 from spec.md § Verification contract.
  - .gitignore covering vendor/, _files/, _cache/, _log/,
    .env, _sessions/, node_modules/

Verify (MUST, in order, before declaring done):
  2. Manual smoke test M1..M8 from spec.md (Docker + GLPI +
     mailpit, including the i18n language-switch check). Each
     must pass.
  3. All OQs resolved — see the Resolved decisions table in
     spec.md. Do not silently re-open a locked decision; if a
     conflict appears, surface it before coding.

If a verifier check cannot be satisfied without changing the
spec, STOP and surface the conflict; do not weaken score.sh.
```
