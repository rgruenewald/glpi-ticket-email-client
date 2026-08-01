<!-- markdownlint-disable MD013 -->

# User Guide (English)

[Wiki home](Home) · [Deutsch](Benutzerhandbuch-DE)

## 1. Send an email from a ticket

1. Open the ticket.
2. Select **Email reply**.
3. The reply form opens inside the ticket.
4. Choose **Email** or **Internal note**. **Email** is already selected.

Use **Email** to write to people outside the ticket. An **Internal note** is only for staff who can view the ticket. Internal notes have no email recipients or email signature.

![Email form open in a ticket](images/email-compose-form.png)

Is **Email reply** missing? Contact your GLPI administrator.

## 2. Write an internal note and use the knowledge base

1. Choose **Internal note** at the top.
2. Write your note in the message box.
3. Add files if needed.
4. Check the **Waiting** and **Solved** switches.
5. Save the note.

The note is always internal. Only people with access to the ticket can see it. No email is sent.

Use **Answer templates** to add prepared text to the note. Choose a template, then check the added text. You can change the text afterward.

### Find text in the knowledge base

1. Choose **Knowledge base** in the form.
2. Search for a suitable article.
3. Check the article.
4. Add the article to the note.
5. Change the added text if needed.

Adding an article replaces the text in the message box. Copy any text you still need before adding the article.

## 3. Choose recipients

The form provides **To**, **CC**, and **BCC**.

These recipients are often filled in:

- Requesters → **To**
- Observers → **CC**

You can add more recipients:

- Type a name, then choose the right person from the list.
- You can also enter an email address.
- Separate several addresses with a comma, semicolon, or Enter.

You must enter at least one valid address. GLPI marks an incorrect address. You can also send an email using only **BCC**.

### BCC is visible in the ticket

Email recipients do not see BCC addresses.

Everyone who can read the ticket can see the BCC addresses in the ticket.

Do not use BCC to hide addresses from those people.

## 4. Write the subject and message

- Fill in **Subject** and **Message**.
- GLPI adds the ticket number to the subject. This sends a reply back to the same ticket.
- Choose **Start a new conversation** only when the reply should no longer belong to this ticket. A reply may then create a new ticket.
- You can format the text.
- You can change the subject, message, and signature.
- Email recipients from a template are not used.
- You only see information you are allowed to view.

### Use answer and solution templates

**Answer templates** add prepared text to the message.

1. Choose a template under **Answer templates**.
2. Check the added text.
3. Change or add text if needed.

The template text replaces the previous message. The email signature stays in place. Choose the template first, then write your own text.

**Solution templates** work in the same way. They also turn on **Solved** and turn off **Waiting**. Check the text and ticket status before sending.

## 5. Add files and images

### New files

Select **Choose files**. You can also drag files into the attachment area.

Check the files before sending. If a file is too large, GLPI shows a message.

### Images in the message

Drag an image into the message box. You can also paste a copied image there.

### Files from the ticket

You can choose existing files from the ticket. These include files attached to the ticket and files from public replies. Use the link beside a file to check it before sending.

Choosing files and **Attach public ticket history** are separate options. Files from internal notes are not shown.

## 6. Attach public ticket history

Choose **Attach public ticket history** when the recipients should see earlier public replies.

This option is off at first. Internal notes are never included.

## 7. Choose the ticket status

There are two switches at the bottom of the form:

- The pause symbol means **Waiting**.
- The check mark means **Solved**.

You can turn on only one switch. Turning on one switch turns off the other.

### For an email

**Set ticket status to waiting after sending.** is normally already on. Do not want to change the status? Turn **Waiting** off. You can turn on **Solved** instead.

The status changes only after the email was sent successfully and added to the ticket.

### For an internal note

Turn on **Waiting** or **Solved** only when you also want to change the ticket status. The status changes after the note is saved.

## 8. Warning about a recipient address

GLPI warns you when an address may send the email back to GLPI. This could create many unwanted emails.

1. Check the address.
2. Remove or correct it if needed.
3. Is the address correct? Confirm the warning.
4. Choose **Send** again.

GLPI cannot find every possible forwarding address. Check the address carefully.

## 9. Send the email

1. Check recipients, subject, and message.
2. Check files and status options.
3. Select **Send** once.

Do not choose **Send** again while sending is in progress. If an error occurs, the plugin does not send the email again by itself.

## 10. Check the send

After a successful send, the email appears in the ticket history.

![Successful email entries in the ticket timeline](images/ticket-email-timeline.png)

You can see the message, recipients, and attachments there. Everyone with access to the ticket can see this information.

More details are available in the **Sent emails** tab.

![Sent email list](images/sent-email-log.png)

![Sent email detail](images/sent-email-detail.png)

## 11. Email sent but not visible in the ticket

Sometimes the email was sent but could not be saved in the ticket. GLPI then shows **Incomplete send (timeline failed)**.

**Do not send the email again.** The recipients may already have it. Open **Sent emails**, then contact your GLPI administrator.

## 12. Troubleshooting

| Problem | Solution |
| --- | --- |
| **Email reply** is missing | Contact your GLPI administrator. |
| Address is invalid | Correct or remove the address. |
| No recipient | Add at least one address. |
| File is too large | Choose a smaller file. |
| Mailbox warning appears | Check the address. Confirm only when intended. |
| Send failed | Open **Sent emails** and contact your GLPI administrator. |
| Email was sent but is missing from the ticket | Do not send it again. Open **Sent emails** and contact your GLPI administrator. |
| Attachment does not open | Check your sign-in and ticket access. |
