<!-- markdownlint-disable MD013 -->

# User Guide (English)

[Wiki home](Home) · [Deutsch](Benutzerhandbuch-DE)

## 1. Send an email from a ticket

1. Open the ticket.
2. Select **Email reply**.
3. The email form opens inside the ticket.

![Email form open in a ticket](images/email-compose-form.png)

Is **Email reply** missing? Contact your GLPI administrator.

## 2. Choose recipients

The form provides **To**, **CC**, and **BCC**.

These recipients are often filled in:

- Requesters → **To**
- Observers → **CC**

You can add more recipients:

- Find GLPI users with autocomplete.
- Enter other email addresses directly.
- Separate addresses with a comma, semicolon, or Enter.

At least one valid address is required. BCC-only sending is allowed. Invalid addresses are shown.

### BCC is visible in the ticket

Email recipients do not see BCC addresses.

Every ticket reader can see them in the send log. After a successful send, they also appear in the ticket history.

Do not use BCC to hide addresses from other ticket readers.

## 3. Write the subject and message

- **Subject** and **Message** are required.
- You can format the text.
- A template may fill the subject and signature.
- You can change the subject, message, and signature.
- Recipients from the template are not used.
- You only see data you are allowed to view.

## 4. Add files and images

### New files

Select **Choose files**. You can also drag files into the attachment area.

Check the files before sending. GLPI upload limits apply.

### Images in the message

Drag an image into the editor. You can also paste it from the clipboard.

### Files from the ticket

You can select public ticket attachments. Private notes and their files are not offered.

## 5. Attach public ticket history

Enable **Attach public ticket history** when the recipient should receive the public history.

The option is off by default. Private notes are not sent.

## 6. Choose the ticket status

These options may appear beside **Send**:

- Set the ticket to **Waiting**
- Set the ticket to **Solved**

Check the selection before sending.

## 7. Mail-loop warning

GLPI warns you when a recipient matches an active mail collector.

1. Check the address.
2. Remove or correct it if needed.
3. Confirm only when the address is correct.
4. Send again.

The check cannot find every alias or forwarding address.

## 8. Send the email

1. Check recipients, subject, and message.
2. Check files and status options.
3. Select **Send** once.

The plugin does not retry automatically.

## 9. Check the send

After a complete send, the email appears in the ticket history.

![Successful email entries in the ticket timeline](images/ticket-email-timeline.png)

The entry shows the message, recipients, and attachments. Every user who can read the ticket can see this data.

More details are available in the **Sent emails** tab.

![Sent email list](images/sent-email-log.png)

![Sent email detail](images/sent-email-detail.png)

## 10. Troubleshooting

| Problem | Solution |
| --- | --- |
| **Email reply** is missing | Contact your GLPI administrator. |
| Address is invalid | Correct or remove the address. |
| No recipient | Add at least one address. |
| File is too large | Choose a smaller file. |
| Mailbox warning appears | Check the address. Confirm only when intended. |
| Send failed | Open the send log. Contact your administrator. |
| Email is missing from ticket history | Do not send again. The email may already have been delivered. Contact your administrator. |
| Attachment does not open | Check your sign-in and ticket access. |
