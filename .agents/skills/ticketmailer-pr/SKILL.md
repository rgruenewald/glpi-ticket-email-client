---
name: ticketmailer-pr
description: Prepare or review ticketmailer change PR titles, descriptions, verification evidence, risk assessments and UI evidence. Use before opening or updating a normal PR.
---

# Ticketmailer pull requests

1. Read ../../../.github/pull_request_template.md and the PR section in
   ../../../CONTRIBUTING.md. For a generated release PR, use
   ../ticketmailer-release/SKILL.md instead; do not replace Release Please's body.
2. Load the available global pr-description skill for English description drafting
   and diff/commit inspection. Use its full mode and map the result to the local
   template headings. Project requirements supplement that skill, not a second
   competing description format. If unavailable, follow the template directly
   and report that limitation.
3. Inspect the actual branch diff and verification records. Propose a Conventional
   Commit title suited to squash merging. Do not infer tests from filenames or
   treat an earlier commit's results as proof for changed code.
4. Assess delivery and duplicate-send behavior, permissions, privacy, database
   migrations and GLPI/PHP compatibility. State unaffected areas with reasons;
   distinguish known failures, unrun checks and remaining uncertainty.
5. For visible UI changes request suitable before/after screenshots, using only
   synthetic data. Consult ../ticketmailer-release-media/SKILL.md for capture.
   If evidence is missing, say so; do not fabricate it or label a mockup as runtime.
6. Draft or review only within the current authorization. A skill invocation does
   not authorize pushing, opening/updating a public PR, merging or publishing.

Do not repeat the general PR skill's Git procedure or invent additional required
CI checks. The template is a review standard; it is not an automated semantic gate.
