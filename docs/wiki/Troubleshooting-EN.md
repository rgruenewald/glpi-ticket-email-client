# Troubleshooting

[Home](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [Deutsch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Fehlerbehebung-DE) · [Administrator guide](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administrator-Guide-EN)

## First: establish the delivery outcome

Before retrying, check the ticket’s **Send log**, the audit detail and timeline. A browser error or missing followup is not proof that SMTP failed. Internal notes have no email-send audit row.

| Audit status | Meaning | Next step |
| --- | --- | --- |
| `pending` | Durable send intent was written; no terminal result is recorded yet. | If it remains pending, investigate with an administrator before resending. |
| `sent` | SMTP succeeded and post-send recording completed. | Check the recipient mailbox; do not resend just because a dialog looked stale. |
| `failed` | The SMTP attempt failed. | Correct the cause and inspect the audit error before a deliberate retry. |
| `sent_with_error` | SMTP succeeded, but a later step failed. | Inspect existing followup, documents and ticket status. Do not resend just to repair GLPI state. |

SMTP success means the transport accepted the message, not guaranteed inbox delivery or a read receipt. After a crash, an interrupted request or an ambiguous transport result, reconcile with the mail system before a manual retry. Ticketmailer does not automatically retry partial failures.

## Reply action missing or access denied

1. Confirm the plugin is enabled and compatible with GLPI 11.0.x / PHP ≥ 8.2.
2. Confirm the active profile and entity, ticket read access, and GLPI’s native right to add a followup.
3. Check entity/profile reply policy and global button visibility. A promoted or redirected native Answer may replace the separate entry point.
4. Reload after permission or configuration changes. Changing visibility does not bypass rights.

If **Solved** is disabled, check status-transition rights, required fields and whether the ticket is already solved or closed. Do not change profile rights merely to bypass a correct lifecycle restriction.

## Form rejected before sending

- **Invalid recipient:** use valid addresses and the documented separators. A malformed token rejects the whole request; it is not silently omitted.
- **No recipient:** ticket replies accept To, CC or BCC. New conversations require a To recipient.
- **Subject/body required:** provide a human subject and non-empty message. Hidden fields still need valid values.
- **Followup source unavailable:** select an active source that permits followups, or clear the optional selection.
- **Ticket status changed:** reload the dialog, review the current state and reapply the intended transition. A stale form is not sent.
- **Mailbox warning:** verify the matching active collector login. Confirm the override only if delivery to it is intentional.
- **Attachment too large or inaccessible:** reduce the combined size, check GLPI/PHP upload limits and verify access to the selected document. Removed or unreadable documents cannot be reused.

## SMTP fails or the message never arrives

Check GLPI’s SMTP host, port, TLS/authentication and the entity’s sender configuration. Ticketmailer has no separate credentials. Use the audit error and GLPI/mail-server logs to distinguish connection, authentication, sender-policy and downstream filtering problems.

Do not post credentials, tokens, full customer messages or unredacted recipient lists in an issue. SMTP success followed by spam filtering or a later bounce is not the same as an SMTP send failure.

## Message delivered, but ticket incomplete

For `sent_with_error`, identify the failed step:

- **Followup creation:** the message was sent even if no timeline entry exists.
- **Document linking:** a followup may already exist. On current code after 2.6.1 its audit link is retained even when document linking fails.
- **Status change:** delivery or note creation may have succeeded while the requested status transition did not. Inspect the current ticket status and lifecycle rights.

Repair or document the missing GLPI state using normal authorized procedures. **Do not press Send again just to produce a followup, document link or status change.** For a new conversation, also check whether the new ticket already exists before starting another one.

## Sent attachment not offered in a later reply

Check the installed version first. Release 2.6.1 predates the change that persists newly sent uploads as selectable ticket documents. Updating does not backfill old uploads. On code containing the fix, check document access, file readability and any `sent_with_error` audit result.

## Unexpected content, status or notifications

- Check entity defaults and personal preferences for template, signature and Waiting.
- Waiting may be preselected; clear it if the status should remain unchanged.
- Without an explicit transition, 2.6.1 and later preserve status for replies and notes. Older code can behave differently.
- Selecting a solution template inserts text. It is the Solved switch that requests the status change.
- Email followups suppress native notification delivery. Internal notes can still trigger native private-followup notifications.
- Full BCC visibility in the timeline and audit is intentional; see the [privacy policy](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administrator-Guide-EN#privacy-retention-and-uninstall).

## Report a bug

Include plugin/GLPI/PHP versions, active profile/entity context, reproduction steps with synthetic data, expected/actual result, audit ID/status and whether SMTP delivery occurred. Use [GitHub Issues](https://github.com/rgruenewald/glpi-ticket-email-client/issues). For security concerns, follow the [security policy](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/SECURITY.md) instead of publishing sensitive details.
