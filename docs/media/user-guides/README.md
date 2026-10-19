# User guide media: provenance and reproduction

## Scope

Current Ticketmailer 2.4.0 user documentation, source commit 20df4fa4f8efb563ad80a06977a1e145934f92a4 plus uncommitted documentation changes. No product code was changed.

Runtime: documented disposable local GLPI 11.0.8 / Mailpit environment. Chromium from repository-pinned Playwright 1.64.1, 1440 × 1080, device scale 1. English (en_GB) and German (de_DE) fixture sessions. The public local fixture login is glpi/glpi. No real tickets, addresses, SMTP credentials or external transport are used.

## Assets

- Eight screenshots under ../../wiki/images/current/: reply modal, native private-note modal, proactive New email, and sent-email detail in each language.
- ../../wiki/videos/switch-modes.mp4: real English UI capture, about 6.7 seconds, silent H.264 MP4, original speed. One continuous excerpt starting at 3.1 seconds from the private raw capture; login and navigation trimmed away. No overlays, simulated typing, time changes, or fake outcomes. Shows Email → Internal note → Email and email text retained. It does not show sending.
- Screenshots retain the real interface. The full-page modal screenshots include the dimmed ticket background. English UI may still use the literal title “E-Mail antworten” in the local runtime. The German sent detail intentionally contains the same English synthetic email body as the English detail: it is one actual send, not a translated second email.

All eight images and a half-second contact sheet of the final clip were inspected for personal information, tokens, browser chrome, clipping, and misleading edits. Only synthetic ticket content, example.test recipients and the local fixture account are visible. Raw browser videos remain outside the repository.

## Capture

Use only the documented local disposable environment. Read ../../verification/docker-e2e.md first; do not point these scripts at a shared or production runtime. Run from the repository root with the pinned Playwright already available:

- node docs/media/user-guides/capture.cjs
- node docs/media/user-guides/capture-delivery.cjs

The first script creates a synthetic ticket, briefly changes the fixture user's UI language for capture, restores the previous value in finally, and records a private raw English video under /tmp/ticketmailer-guide-video. The second creates another synthetic ticket and sends **one real test email to local Mailpit**, including To/CC/BCC and handover.txt, then captures the same sent detail in both languages. It does not send to the internet. Both scripts leave the new synthetic tickets in the development database.

For the selected capture, export the reviewed excerpt with FFmpeg using -ss 3.1, -t 6.7, -an, -c:v libx264, -pix_fmt yuv420p and -movflags +faststart. On a fresh capture determine trim points again; browser timings vary. Inspect the entire exported clip before replacing the tracked asset.

## Checks actually performed

- Both language variants opened in the actual GLPI runtime.
- Email/native-note switching captured and retained email body observed.
- Synthetic attachment send produced audit status sent and timeline_status recorded (ticket 148, audit 40 in the capture environment).
- A Mailpit lookup and stored followup check are recorded in the task's verification output; this is a focused documentation smoke check, not a full release or permission regression suite.
- No cloud renderer, external upload, release publication, or public video hosting used. MP4s are ordinary repository links; playback behavior depends on GitHub's renderer.
