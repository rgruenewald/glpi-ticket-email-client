<!-- markdownlint-disable MD013 -->

# User guide: GLPI Ticket Email Client

[Documentation home](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [Deutsch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Benutzerhandbuch-DE) · [Installation and administration](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-EN)

Write an email from a ticket, start a new conversation, or add an internal note without leaving GLPI. This guide covers the current `main` code, including changes after release **2.6.1**. Supported runtime: GLPI 11.0.x and PHP 8.2 or later; the documented reference environment is GLPI 11.0.8. See [version notes](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Version-Notes-EN) for released and unreleased changes.

The screenshots below are examples from the 2.4.0 test instance with synthetic tickets and example.test recipients. Available actions and fields depend on your permissions and the administrator's configuration. Button names may differ with the GLPI language; the email reply action may also be labelled **Answer**.

## Quick start

1. Open a ticket and choose **Email reply** or the configured **Answer** action.
2. Leave **Email** selected. Check To, CC, and BCC.
3. Write a subject and message, and add the files the recipient needs.
4. Review the status switches and any recipient warning.
5. Choose **Send** once. Check the ticket timeline and send log for the outcome.

> **BCC is not private inside GLPI.** Every ticket reader can see the full BCC list in the ticket followup and audit detail. BCC recipients are excluded from the email's visible To/CC headers.

## 1. Reply from a ticket

The reply action opens a dialog over the ticket. **Email** is the default mode. It offers recipients, a subject, GLPI's rich-text editor, and attachments.

![Current email reply dialog with recipients, ticket routing marker, editor, attachment area, and status switches](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/compose-en.png)

Is the action missing? Ask your administrator to check your ticket permissions and the reply policy. Sending requires ticket access and GLPI’s native permission to add a followup to that ticket. Ticket update permission alone is not enough. Reading a ticket alone does not permit sending.

### Choose recipients

- **To:** ticket requesters are filled in by default.
- **CC:** ticket observers are filled in by default.
- Assignees are not automatically added.
- **BCC:** add recipients when they must not appear in the email's visible headers. Remember that ticket readers can still see them.

Use addresses separated by commas, semicolons, or line breaks. For GLPI users, type at least two characters of a login, name, or default email address, then choose a suggestion. Suggestions respect entity visibility. External email addresses are also supported.

Check the recipient chips before sending. Use a chip's **×** to remove an address, or the field's clear button to empty the field. At least one valid recipient across To, CC, or BCC is required; a BCC-only message is allowed. Invalid non-empty address tokens cause an error rather than being silently removed.

### Subject and conversation routing

Write the subject text beside the fixed **[GLPI #ticket-id]** marker. The marker is normally included in the outgoing subject so GLPI can associate replies with the ticket.

Only enable **Start a new conversation** when you deliberately want to omit that marker. **Replies may create a new ticket.** This option does not itself create another ticket when replying from an existing ticket.

A subject and non-empty message are required. Depending on the entity configuration, a ticket template may supply subject text and an editable signature. Check both before sending.

### Templates and knowledge base

Use the available **Answer templates**, **Solution templates**, source selector, and **Knowledge base** action to prepare the message. Administrators can hide individual controls.

Check inserted content before sending. **Adding a knowledge-base article can replace the editor text.** Copy any text you need to keep before inserting an article. A template selection is not the same as changing the ticket status; review the status switches separately.

## 2. Files, inline images, and public history

### Upload a new file

Choose **Choose files** or drag files into the attachment area. Check the displayed selection before sending. GLPI's configured upload limits apply; oversized or rejected files must be corrected before sending.

On current `main`, uploads from successfully sent emails are also stored as ticket documents and linked to the followup. They are selectable in later replies. Existing documents are not duplicated. This change is not included in release 2.6.1.

For inline images, use the editor's image controls, paste a copied image, or drag an image into the message body. Check its appearance before sending.

### Reuse ticket attachments

If the ticket or its public followups have attachments, select the individual files you want to include. Open a file's link in a separate tab to check it first. Files from private followups are never offered or sent through this selection.

### Include public ticket history

**Attach public ticket history** is off by default. Enable it to append the ticket's public history to the message body. Despite the label, it does not automatically select existing files. Public history and individual attachment selection are independent.

Private followups and their documents are excluded. Review the public history too: public ticket content may still contain information you do not want to share with these recipients.

## 3. Internal notes and switching modes

Choose **Internal note** to use GLPI's native private-followup form. It has no Ticketmailer email recipient fields or generated email signature. Write the note, add files if needed, check the status options, and use the native **Add** action.

![Current internal-note dialog using GLPI's private-followup editor and native Add action](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/internal-note-en.png)

The note is private under GLPI's native permissions and visibility rules. **It does not enter Ticketmailer's SMTP or audit pipeline. GLPI's configured native followup notifications still apply**, so do not assume that adding a private note can never trigger an email notification.

Switching between Email and Internal note keeps each form's entered text and attachments while the current dialog remains mounted. These are not saved drafts: do not rely on recovery after reloading, leaving the ticket, or ending the session.

[Watch: switch Email → Internal note → Email](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/videos/switch-modes.mp4) (about 7 seconds, English UI, silent).

*The real UI clip shows the recipient fields disappearing in note mode and the email text returning after switching back. It is trimmed at the start and end, plays at original speed, and does not show a send.*

## 4. Choose ticket status

Without an explicit status change, replies and notes preserve the current status, including on solved or closed tickets where GLPI permits followups.

| Switch | Effect |
| --- | --- |
| **Waiting** | Sets the ticket to Waiting after the action, where the transition is allowed. |
| **Solved** | Requests solving after the action. It stays visible but is disabled when rights, required fields or the lifecycle prevent solving. |

For emails, Waiting is initially enabled if the entity or user preference requests it and the transition is allowed. Turn it off to keep the current status. A solution template alone does not solve a ticket. If both values are submitted, Solved takes precedence.

If the ticket status changed while the dialog was open, reload before sending. Status changes happen after successful delivery or note creation. An SMTP failure does not change the status. A status change that fails after delivery does not trigger another SMTP attempt.

## 5. Mail collector warning

A recipient matching an active GLPI mail collector's email login triggers a warning. Sending to a collector can feed mail back into GLPI.

1. Check the address and remove or correct it when appropriate.
2. If it is intentional, explicitly confirm the mailbox override.
3. Submit again only after reviewing the warning.

The server rechecks the address and confirmation before sending. **The guard is best effort:** it compares exact normalized email logins, not aliases, forwarding rules, or non-email collector logins. No warning does not prove that an address is safe.

## 6. Send and check the result

Choose **Send** once after reviewing recipients, subject, body, files, history, and status options. Ticketmailer makes one SMTP attempt through GLPI's existing mail settings; there is no automatic retry or delivery queue.

| Outcome | What it means | What to do |
| --- | --- | --- |
| Sent and recorded | SMTP succeeded and the email was added as a ticket followup. | Check the timeline or sent-email detail. SMTP success is not proof that the recipient read the message or that it reached their inbox. |
| Failed | The SMTP attempt failed. | Open the secure send detail and ask your administrator to investigate. There is no automatic resend. |
| Incomplete | SMTP succeeded, but a later GLPI step failed: followup creation, document linking or the requested status change. A followup may already exist. | **Do not resend.** The email has already been sent. Ask your administrator to resolve the missing ticket record. |

If the page times out or the result is unclear, check **Sent emails** before taking any further action. Do not assume that a missing timeline entry means the email was not sent.

### Ticket timeline and send log

A complete email send creates a normal public ticket followup containing the sender, time, To/CC/BCC, subject, message, and secure attachment links. That email followup does not trigger a second GLPI-generated notification email.

Open the ticket's **Sent emails** tab, then select a subject to see the audit detail, delivery information, and linked files. Only ticket readers can view message details or open stored outbound attachments. This includes the complete BCC list.

![Current sent-email detail showing the full BCC list, message, and stored attachment](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/sent-detail-en.png)

Private notes remain native GLPI followups and do not appear as Ticketmailer email audit entries.

## 7. Start a new email without an existing ticket

Choose **Assistance → New email**. In German GLPI installations the menu may be named **Helpdesk**. This is different from **Start a new conversation** in a ticket reply.

![Current New email page with Sender entity selection, recipient fields, editor, and Send action](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/new-email-en.png)

1. Choose **Sender**: the list contains authorized entities with a configured sender email address.
2. Add recipients, a subject, a message, and optional files.
3. Check the selected entity and signature.
4. Choose **Send** once.

Sending creates the ticket in the selected sender entity, then sends and records the email. To recipients become requesters and CC recipients become observers. BCC recipients do not become observers, but remain visible to ticket readers in the sent record.

Changing Sender determines the ticket entity and signature without switching your active GLPI entity. The action requires ticket create, update, and read rights for an authorized entity. If no valid sender is available, ask an administrator to check the entity email configuration and your rights.

If a failure happens after ticket creation, check the created ticket and send log before trying anything again. Do not create duplicate tickets or resend an email whose SMTP attempt already succeeded.

## 8. Limits and troubleshooting

- Ticketmailer is an outbound composer, not an inbox. It does not fetch mail or synchronize IMAP.
- It does not save persistent drafts, queue sends, or retry automatically.
- Email transport is configured in GLPI, not in Ticketmailer.
- Buttons, templates, source controls, status defaults, and timeline ordering can be configured by an administrator.
- A stored file requires ticket-read access even if you know its download URL.

For a problem report, provide the ticket ID, time, displayed outcome, and redacted error text. Never include SMTP credentials, real recipient lists, or private ticket content in a public issue.

[Installation and administration](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-EN) · [Security policy](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/SECURITY.md) · [Back to documentation home](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home)

## More help

- [Installation](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-EN)
- [Administrator guide](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administrator-Guide-EN)
- [Troubleshooting](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Troubleshooting-EN)
- [Version notes](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Version-Notes-EN)
