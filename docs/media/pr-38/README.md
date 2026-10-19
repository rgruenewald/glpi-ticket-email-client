# PR #38: compose toolbar evidence

Four unedited Playwright element screenshots of the real ticket compose modal.
The crop is the template/source toolbar only, so recipients, ticket contents and
unrelated timeline controls are not included. No mockups or generated UI.

## Source and environment

- Before: 17f3e41 (parent of the fix).
- After: de5ba99 (toolbar fix).
- Runtime: repository's local GLPI 11 Docker development instance at
  http://127.0.0.1:8080; synthetic ticket 137; GLPI test account.
- Chromium 156.0.8078.4, Playwright, headless, 1440 × 1000 viewport,
  device scale factor 1, English UI, en-US browser locale.
- Same instance, fixture, session setup, viewport and ticket-button entry path
  for both versions. No mail or note was submitted.

## Capture method

The change modifies only CSS and a browser test. Each capture uses the same
running application and a fresh browser page, with the stylesheet request
intercepted and fulfilled with the exact contents of
`git show <revision>:public/css/ticketmailer.css`. This isolates the changed
asset without changing the database or the local working copy. It excludes the
unrelated, uncommitted timeline-button CSS adjustment. This is stylesheet replay
in the real runtime, not a full historical application deployment.

Log in, open /front/ticket.form.php?id=137, click
.ticketmailer-action-reply, select Email, capture
.ticketmailer-mail-row--followup-meta, switch to Internal note, capture
.ticketmailer-note-options > .row. Wait for each row to be visible and another
800 ms for layout to settle before locator.screenshot(). Repeat with the other
revision's stylesheet.

## Files and review

- before-email.png / after-email.png: Email toolbar.
- before-internal_note.png / after-internal_note.png: Internal note toolbar.

All four images were visually inspected: only generic toolbar labels are visible;
no personal data, addresses, credentials, tokens, notifications or browser chrome.
Before shows mismatched column sizing and spacing; after shows the shared grid.
The note toolbar is two pixels wider because the email panel has a 1px border.
These images demonstrate the desktop appearance, not SMTP or note submission.
Mobile geometry is covered separately by the 390px browser regression test.
