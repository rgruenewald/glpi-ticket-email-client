# AGENTS.md — ticketmailer

Project operating rules for agents. Product facts live in `CONTEXT.md` and the v2 contract — do not restate them here.

## Source of truth

1. **Behavior / ACs:** `.agent/contracts/glpi-ticket-email-client-v2/spec.md`
2. **Kernel facts:** `CONTEXT.md`
3. **Locked product decisions:** `docs/adr/0001-v2-locked-product-decisions.md`
4. **v1 contract** (`.agent/contracts/ticket-mailer/`): historical only; ignore when it conflicts with v2
5. **`.agent/planning/`:** research history, not live rules, unless the task explicitly revisits a ticket

## Before changing behavior

- Read the owning v2 acceptance criterion and the relevant skill under `.agents/skills/`.
- Prefer existing `PluginTicketmailer*` classes and GLPI APIs over new abstractions.
- Do not add: SMTP config UI, notification-engine delivery on the compose path, draft storage, IMAP, editor libraries, or DOM/CSS hiding of native reply.
- UI strings: update `locales/ticketmailer.pot` + `en` + `de` together.

## Layout / git

- This repo is already bare+worktree style; active work is under `main/` (or a feature worktree beside it).
- New worktrees: create **inside** the project folder; path = branch name (`wt switch --create …` or absolute `git worktree add`).
- Push the worktree branch only (`git push origin <branch>`), never force main casually.
- Do not override git author/committer env for commits.

## After changes

- Run the verify skill: `.agents/skills/ticketmailer-verify/SKILL.md`
- Structural gate: v2 `score.sh` must stay green for touched surfaces.
- Do not commit secrets, `_files/`, vendor, or `.agent/forge_state.json`.

## Global vs project

Global harness skills (TDD, review, guardrails, herdr, etc.) stay global. Project skills hold only plugin-specific guidance.

## Class / file conventions

- PHP classes: `PluginTicketmailer…` in `inc/*.class.php` (GLPI plugin naming).
- Front controllers under `front/`; AJAX under `ajax/`; Twig under `templates/`.
- SQL migrations: one file per version bump; keep `install.sql` equivalent to latest greenfield schema.
