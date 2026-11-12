---
name: ticketmailer-release-media
description: Prepare optional ticketmailer UI screenshots and short release videos with synthetic fixtures and reproducible browser scenarios. Use for UI evidence or release demonstrations, not as a release gate.
---

# Ticketmailer release media

Read ../../../docs/RELEASE-MEDIA.md before capturing or editing media. Resolve
commands from the repository root. For README assets also load
../ticketmailer-readme/SKILL.md and preserve its accuracy and privacy rules.

1. Identify the visible change and exact source commit. Prefer before/after
   screenshots; propose a short video only when motion explains the change better.
   Non-UI changes normally need prose, not video.
2. Select an existing browser scenario or define the smallest new capture scenario.
   Do not rewrite an existing verification test into a presentation-only assertion.
   Record fixture, browser, language, viewport, steps and expected visible outcome.
3. Use an explicitly chosen disposable development environment with synthetic
   tickets and example.test recipients. Check local instructions before starting
   services; do not silently reuse shared development state or production data.
4. Capture real behavior. Inspect every selected frame for personal data, tokens,
   browser chrome, notifications and misleading crops. Keep traces/session files
   private. Do not upload raw captures to an external rendering service by default.
5. HyperFrames is an optional, not yet integrated post-processing candidate.
   Before a pilot, verify the exact product/version, official API, local/cloud
   execution, export support, licensing and costs. Obtain permission for new
   external uploads or charges. Do not invent commands or install dependencies
   merely because this skill was loaded.
6. Follow the media specification and provenance checklist in the document. Identify
   mockups, cuts, speed changes and animation as such; they are not runtime proof.
7. Report completed checks and missing ones. Media creation does not authorize
   public upload, release publication or Marketplace submission. Media is optional
   and must never block an otherwise verified release.
