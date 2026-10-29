# Administrator guide

[Home](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [Deutsch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administratorhandbuch-DE) · [Installation](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-EN) · [User guide](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/User-Guide-EN)

## Scope and permissions

Ticketmailer is a ticket-context email client, not a mailbox or notification engine. It has no IMAP reader, draft storage, or SMTP settings of its own. Recipient suggestions for visible GLPI users are available from two typed characters. Incoming replies still depend on your GLPI mail collectors and routing setup.

Users need access to the ticket and GLPI’s native right to add a followup. The server enforces entity access and followup rights on submission, including for solved and closed tickets. The reply policy controls presentation, not authorization. Administrative configuration requires GLPI configuration rights.

## Outbound transport and sender

Configure and test SMTP in GLPI’s core email settings. Ticketmailer uses `GLPIMailer` directly and does not queue the composed message through GLPI’s notification engine. It makes one SMTP attempt per send request; it is not a guaranteed exactly-once delivery service across user retries or network failures.

For a reply, GLPI resolves the From address and display name from the ticket’s entity. For a new outgoing conversation, the selected sender entity determines the sender, template, signature, preferences and eventual ticket entity. Users may select only accessible entities. An invalid sender or unavailable entity blocks sending.

Changing a sender entity rebuilds entity-specific defaults while preserving the typed subject, recipients and message. Advise users to check these fields again before sending.

## Global and entity settings

Open **Setup → General → Ticket email** for global defaults and interface settings. Open an entity’s **Ticket email** tab for its entity-specific settings. Menu labels follow the GLPI language.

| Setting | Purpose |
| --- | --- |
| Default notification template | Supplies the initial subject and body from a GLPI notification template; this does not enable notification-engine delivery. |
| Signature | Rich-text signature with supported variables. A non-empty signature is resolved through the entity ancestry. |
| Waiting by default | Initial value of the Waiting switch where the ticket permits that transition. |
| Newest timeline entry first | Controls the displayed timeline order. |
| Reply policy by profile | Makes the email action available, promotes it, or requests native-answer replacement. |

Where supported, users can save preferences for the effective template, signature and default Waiting behavior. These preferences are settings, **not drafts**. Entity configuration and user preferences can therefore explain why two users see different initial content.

The global configuration also controls labels, icons, tooltips and visibility for the Ticket email entry points and compose-dialog fields. Hiding a field does not grant rights or disable server validation; check a real dialog after changing visibility. Do not hide required input without providing valid defaults.

## Reply policy and native Answer

Policies are evaluated for the ticket’s entity and active profile. Precedence is exact entity/profile policy, then entity-wide policy, then the global default (available).

| Mode | Effect |
| --- | --- |
| Available | Offers Ticketmailer alongside GLPI’s native reply. |
| Promoted | Presents Ticketmailer as the preferred reply action. |
| Hide native | Requests replacement through a supported GLPI extension point. If no verified extension point is available, the effective behavior falls back to promoted. |

There is no core patch or DOM/CSS hiding fallback. The **Native “Answer” opens Ticket email** interface option routes the native answer action into Ticketmailer. Do not describe an unsupported native-control suppression as enforced policy.

## Templates, signatures and followup source

Use GLPI notification templates for initial subject/body rendering. Followup templates, task templates and solution templates are available in the corresponding composer sections and can be inserted or copied. A task or solution template used as text does not itself create a GLPI task or solution.

Ticket, agent and entity variables are listed in **Available variables** in the settings UI. Values unavailable in the current context render empty. Template and signature HTML is sanitized. Verify templates with a synthetic ticket in each relevant language and entity.

The optional followup source must be active and allowed for followups. **Set ticket status to Solved**, not merely selecting solution-template text, performs the explicit solve action where GLPI permits it. Required fields and lifecycle rights are checked before delivery.

## Recipient defaults and mailbox guard

- To: ticket requesters. CC: ticket observers. BCC: empty. Assigned technicians are not added to CC automatically.
- Recipients are validated and deduplicated server-side, ignoring case, with To → CC → BCC precedence.
- A ticket reply needs at least one recipient in To, CC or BCC. A new outgoing conversation requires at least one To recipient.
- A recipient matching an active GLPI collector’s valid-email login blocks sending until the user explicitly confirms the warning on a CSRF-protected submission.

The mailbox guard is **best effort**: aliases, forwarding, non-email logins, inactive collectors and some mailbox configurations are not detected. It is not loop prevention. Configure and monitor incoming mail separately.

## Timeline, status and notification behavior

A successful email is represented by a standard GLPI followup containing recipients, full subject, message and secure attachment links. The followup is created with `_disablenotif=1`: the composed SMTP email is the recipient delivery, and the followup must not fan out another native email.

Without an explicit switch, replies and notes preserve the current ticket status. Waiting and Solved transitions require native lifecycle rights; Solved also requires the relevant mandatory fields. Solved remains visible but disabled when unavailable. A stale form whose ticket status has changed is rejected before sending. An error after delivery is reported without an SMTP retry.

Internal notes are native private followups, without the email recipient fields or send audit. **Native private-followup notifications can still apply** to authorized internal users. Do not promise that notes generate no notifications at all.

## Attachments and storage

Uploads and selected ticket documents are subject to ticket/document access checks and GLPI’s upload-size limit. Followup download links enforce ticket read permission and do not expose raw storage paths.

The current code after 2.6.1 also persists successfully sent uploads as GLPI ticket documents and links them to the created followup. Reused existing documents are not duplicated; automatic native notifications for these document links are suppressed. There is no migration that backfills older sent uploads into ticket documents.

A post-send document-link failure produces a partial-error result, keeps the existing followup link, and does not retry SMTP. Check both the timeline and the ticket’s documents before recovery.

## Privacy, retention and uninstall

**Full BCC addresses are visible to every ticket reader in the followup and audit detail.** BCC is excluded from the email’s visible To/CC headers only. Review this with data owners before deployment.

The plugin stores recipient lists, subject, sanitized HTML/plain text, attachment metadata, audit status, configuration and preferences in GLPI’s database. Files are stored under `GLPI_PLUGIN_DOC_DIR/ticketmailer/`; current code can additionally create core GLPI document records for sent files. Include the relevant database and GLPI files in backups. No automatic expiry or retention cleanup is provided.

Uninstalling drops plugin tables, including the audit log. Disabling retains them. Decide retention and export requirements before uninstalling. See [installation and removal](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-EN).

## Operational checks

Use [troubleshooting](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Troubleshooting-EN) for failures and the [version notes](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Version-Notes-EN) for version-specific behavior. Report bugs with versions, audit ID/status and sanitized reproduction steps, never SMTP credentials or real customer message contents.
