# Version notes

[Home](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [Deutsch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Versionshinweise-DE) · [Releases](https://github.com/rgruenewald/glpi-ticket-email-client/releases) · [Full changelog](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/CHANGELOG.md)

These notes explain the behavior relevant to users and administrators since the previous wiki, which described 2.4.0. They are not a replacement for release artifacts or the full changelog. Check your installed version before assuming an unreleased feature is available.

## Current main, after 2.6.1: sent attachments (not yet released)

The code contains an attachment fix after the latest published release **2.6.1**:

- New uploads from successfully sent emails become GLPI ticket documents and are linked to the corresponding followup.
- They are available for selection in later replies. Reusing an existing document does not create a duplicate.
- Native notifications for automatic document links are suppressed.
- If document linking fails after SMTP, the result is `sent_with_error`. The existing followup link is retained; SMTP is not repeated.
- Old sent uploads are not automatically backfilled into ticket documents.

The next release version is managed by the release process. Do not infer that a 2.6.2 package is published merely because a changelog section exists.

## 2.6.1: status preservation and lifecycle checks

- Replies and internal notes preserve the ticket status unless an explicit transition is selected, including on solved/closed tickets where GLPI allows communication.
- Native followup rights, lifecycle transitions and required solve fields are validated before SMTP or note creation.
- Solved remains visible but disabled when unavailable. A form based on an outdated ticket status is rejected.
- A status change that fails after delivery is reported without resending the email.

## 2.6.0: expanded internal-note composer

Internal notes can use attachments, followup source and explicit status actions, subject to native rights. The composer offers followup, task and solution templates. Inserting template text is not equivalent to creating a task or solving the ticket. Native private-followup notifications remain distinct from composed-email delivery.

## 2.5.0: interface configuration

Administrators can configure labels, icons, tooltips and visibility for compose buttons and fields. Native Answer can be routed to Ticketmailer. Visibility settings do not bypass server-side permissions or validation.

## 2.4.0: documentation baseline

The existing screenshot examples originate from this generation of the composer. Current permissions, status validation and attachment persistence may differ from the pictured controls. The text in these pages takes precedence over screenshot details.
