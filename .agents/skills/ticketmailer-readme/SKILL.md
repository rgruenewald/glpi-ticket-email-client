---
name: ticketmailer-readme
description: Audit or improve ticketmailer's README and GitHub-safe visual assets without weakening product, compatibility, security, privacy, or failure-semantics documentation.
---

# Skill: ticketmailer-readme

Project-local adaptation informed by `oil-oil/beautify-github-readme` (MIT, copyright 2026 oil-oil). This skill narrows that general method to ticketmailer; it does not copy its asset, motion, showcase, or publishing workflows.

## When to use

Use for README audits, README restructuring, screenshots, static README SVGs, or other repository-homepage presentation work.

Default to a read-only audit. Editing requires an explicit README or asset request. Reading the README or repository does not authorize edits.

## Sources of truth

Before proposing changes, read:

1. `README.md`
2. `CONTEXT.md`
3. `.agent/contracts/glpi-ticket-email-client-v2/spec.md` for affected claims
4. `docs/adr/0001-v2-locked-product-decisions.md` when product boundaries matter
5. Existing proof under `docs/wiki/images/`

Never infer behavior from visuals alone. Verify every product, compatibility, security, privacy, and failure claim against the sources above.

## Fixed project story

```text
Audience: GLPI administrators and authorized ticket agents
Value: Send ticket-context email through GLPI's existing SMTP transport while preserving a durable audit and timeline record.
Primary proof: Existing compose, timeline, sent-email-detail, and audit-log screenshots
First successful action: Install as ticketmailer, enable it, open a ticket, select Email reply
Visual theme: Restrained GLPI-native operational UI; ticket → email → audit/timeline
```

## Modes

Use exactly one mode:

- **Audit** — inspect and report; change nothing.
- **Visual refresh** — preserve the information architecture; improve hierarchy and use real screenshots.
- **Asset-only** — create only explicitly requested static assets; do not edit or embed into `README.md` without separate approval.

Do not perform a full marketing redesign unless explicitly requested. Show a local preview and diff before any commit, push, PR, or publication. Those git/network actions always require explicit authorization.

## Content rules

Use this sequence where it improves clarity:

```text
Value → real UI proof → mechanism → first use → detail
```

Preserve as searchable Markdown:

- GLPI/PHP compatibility and verified version
- marketplace-publication disclaimer
- BCC visibility and outbound-header behavior
- ACL and attachment behavior
- mailbox-guard limits and override requirement
- exactly-one SMTP attempt; no retry, queue, drafts, or inbound processing
- SMTP-success/followup-failure incomplete outcome
- requirements, installation commands, configuration, non-goals, and verification

Never hide critical material in SVG, screenshots, `<details>`, or a distant FAQ. In particular, keep the BCC warning prominent before installation. Do not invent adoption, benchmarks, compatibility, marketplace status, testimonials, features, or guarantees.

Prefer one concise end-to-end example over repeated feature prose. Keep commands, paths, status values, links, and frequently updated facts in Markdown.

## Visual rules

Use existing screenshots as primary proof:

```text
docs/wiki/images/email-compose-form.png
docs/wiki/images/ticket-email-timeline.png
docs/wiki/images/sent-email-detail.png
docs/wiki/images/sent-email-log.png
docs/wiki/images/global-settings.png
```

Prefer one strong screenshot or one restrained composition over a decorative gallery. Do not alter screenshots in ways that misrepresent the UI. Check them for personal, ticket, recipient, host, or other sensitive data before embedding.

Optional new visual: at most one static workflow SVG when it communicates this verified path:

```text
ticket → validation → audit intent → exactly one SMTP attempt → ITILFollowup → complete/incomplete result
```

For SVG:

- store under `assets/readme/`;
- use a `1200`-unit-wide `viewBox`, self-contained background, system fonts, `<title>`, and `<desc>`;
- use simple shapes, paths, text, gradients, and clipping only;
- no scripts, `foreignObject`, remote fonts/images, essential hover behavior, or animation;
- keep essential text large enough for GitHub mobile scaling;
- provide meaningful Markdown/HTML alt text.

Do not create GIFs, decorative section banners, showcase walls, promotional badges, or generic SaaS/marketing graphics. Derive colors and spacing from the real GLPI UI/screenshots. Favor operational trust, restrained contrast, thin rules, and sparse technical composition.

## Minimal-change workflow

1. Inspect the sources of truth and existing screenshots.
2. Record the exact audience, value, proof, first action, and visual direction.
3. Identify the smallest change that materially improves comprehension.
4. Draft exact copy/embeds or an asset proposal before broad restructuring.
5. Render locally at wide and narrow GitHub-like widths.
6. Verify claims, links, image paths, alt text, contrast, clipping, and screenshot privacy.
7. Show preview plus `git diff`; state intentionally untouched files.

For README-only changes, do not run unrelated code suites. If product claims changed, run the relevant project verification from `.agents/skills/ticketmailer-verify/SKILL.md`.

## Quality bar

- First screen explains what the plugin does and why its delivery path differs.
- Real UI proof appears early.
- Security/privacy boundaries remain conspicuous and exact.
- Visuals communicate product behavior; none are merely decorative.
- README remains useful if images fail.
- Result is clearer, not merely longer or prettier.
- Unrelated docs, code, screenshots, and assets remain untouched.
