<?php
/**
 * front/send.php — v2 send pipeline:
 * parseRaw → mailbox guard → audit intent → SMTP once → timeline followup.
 */

use Glpi\Application\View\TemplateRenderer;

require_once __DIR__ . '/../inc/bootstrap.php';

Session::checkLoginUser();

$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id) || !( $ticket->canUpdateItem() || (method_exists($ticket, 'canAddFollowups') && $ticket->canAddFollowups()) )) {
    Html::displayRightError();
}
$web = Plugin::getWebDir('ticketmailer');


$subject = trim((string) ($_POST['subject'] ?? ''));
$body_html = (string) ($_POST['body_html'] ?? '');
$body_text = (string) ($_POST['body_text'] ?? '');
$include_history = !empty($_POST['include_history']);
$selected_history_attachments = array_values(array_filter(
    (array) ($_POST['history_attachments'] ?? []),
    static fn (mixed $attachment): bool => is_scalar($attachment),
));

$recipients_to_raw  = (string) ($_POST['recipients_to']  ?? '');
$recipients_cc_raw  = (string) ($_POST['recipients_cc']  ?? '');
$recipients_bcc_raw = (string) ($_POST['recipients_bcc'] ?? '');

$parsed_to  = PluginTicketmailerRecipients::parseRaw($recipients_to_raw);
$parsed_cc  = PluginTicketmailerRecipients::parseRaw($recipients_cc_raw);
$parsed_bcc = PluginTicketmailerRecipients::parseRaw($recipients_bcc_raw);

$recipients_to  = $parsed_to['valid'];
$recipients_cc  = $parsed_cc['valid'];
$recipients_bcc = $parsed_bcc['valid'];

$errors = [];
$requesttypes_id = (int) ($_POST['requesttypes_id'] ?? 0);
if ($requesttypes_id !== 0) {
    $requesttype = new RequestType();
    if (!$requesttype->getFromDB($requesttypes_id)
        || empty($requesttype->fields['is_active'])
        || empty($requesttype->fields['is_itilfollowup'])
    ) {
        $errors[] = __('Selected follow-up source is unavailable.', 'ticketmailer');
    }
}
if ($subject === '') {
    $errors[] = __('Subject is required.', 'ticketmailer');
}
if (trim(strip_tags($body_html)) === '') {
    $errors[] = __('Body is required.', 'ticketmailer');
}
if (!PluginTicketmailerRecipients::hasAny($recipients_to, $recipients_cc, $recipients_bcc)) {
    $errors[] = __('At least one recipient is required (To, CC, or BCC).', 'ticketmailer');
}
foreach ([
    'to'  => $parsed_to['invalid'],
    'cc'  => $parsed_cc['invalid'],
    'bcc' => $parsed_bcc['invalid'],
] as $field => $invalid) {
    if ($invalid !== []) {
        $errors[] = sprintf(
            __('Field "%1$s" contains an invalid address: %2$s', 'ticketmailer'),
            strtoupper($field),
            implode(', ', $invalid),
        );
    }
}

$all_recipients = array_values(array_unique(array_merge(
    $recipients_to,
    $recipients_cc,
    $recipients_bcc,
)));
$mailbox_matches = PluginTicketmailerMailboxGuard::findMatches($all_recipients);
$mailbox_override = !empty($_POST['mailbox_override']);
if ($mailbox_matches !== [] && !$mailbox_override) {
    $errors[] = sprintf(
        __('Recipient(s) match an active incoming mailbox login: %s. Confirm the override to send anyway. Aliases, forwarding, and non-email logins are not detected.', 'ticketmailer'),
        implode(', ', $mailbox_matches),
    );
}

if ($selected_history_attachments !== []) {
    try {
        PluginTicketmailerHistory::validateSelectedAttachments($ticket, $selected_history_attachments);
    } catch (InvalidArgumentException) {
        $errors[] = __('A selected history attachment is no longer available.', 'ticketmailer');
    }
}

if ($errors !== []) {
    Html::header(
        __('E-Mail antworten', 'ticketmailer'),
        $_SERVER['PHP_SELF'],
        'helpdesk',
        'ticket',
    );
    $body_editor = Html::textarea([
        'name'              => 'body_html',
        'value'             => $body_html,
        'editor_id'         => 'body_html',
        'enable_richtext'   => true,
        'enable_images'     => true,
        'enable_fileupload' => false,
        'rows'              => 14,
        'display'           => false,
    ]);
    $editor_id = 'body_html';
    $entities_id = (int) $ticket->getField('entities_id');
    $profiles_id = isset($_SESSION['glpiactiveprofile']['id'])
        ? (int) $_SESSION['glpiactiveprofile']['id']
        : null;
    $twig = TemplateRenderer::getInstance();
    echo $twig->render('@ticketmailer/compose.html.twig', [
        'tickets_id'         => $tickets_id,
        'ticket'             => $ticket,
        'recipients_to'      => $recipients_to,
        'recipients_cc'      => $recipients_cc,
        'recipients_bcc'     => $recipients_bcc,
        'recipients_to_raw'  => $recipients_to_raw,
        'recipients_cc_raw'  => $recipients_cc_raw,
        'recipients_bcc_raw' => $recipients_bcc_raw,
        'subject'            => $subject,
        'body_editor'        => $body_editor,
        'editor_id'          => $editor_id,
        'followup_template_dropdown' => PluginTicketmailerTimelineAction::followupTemplateDropdown(),
        'followup_source_dropdown' => PluginTicketmailerTimelineAction::followupSourceDropdown(),
        'followup_template_url' => PluginTicketmailerTimelineAction::followupTemplateUrl(),
        'errors'             => $errors,
        'csrf_token'         => Session::getNewCSRFToken(),
        'ajax_csrf'          => Session::getNewCSRFToken(true),
        'send_url'           => $web . '/front/send.php',
        'upload_url'         => $web . '/ajax/upload.php',
        'image_url'          => $web . '/ajax/upload_image.php',
        'validate_url'       => $web . '/ajax/validate_recipients.php',
        'attachment_max'     => PluginTicketmailerConfig::uploadMaxSizeLabel(),
        'effectivePolicy'    => PluginTicketmailerReplyPolicy::effectivePolicy($entities_id, $profiles_id),
        'mailbox_override'   => $mailbox_override,
        'mailbox_matches'    => $mailbox_matches,
        'include_history'    => $include_history,
        'history_attachments' => PluginTicketmailerHistory::availableAttachments($ticket),
        'selected_history_attachments' => $selected_history_attachments,
    ]);
    Html::footer();
    exit;
}

$attachments = [];
$inline_images = [];
$files_root = GLPI_PLUGIN_DOC_DIR . '/ticketmailer/' . $tickets_id;
if (!is_dir($files_root)) {
    mkdir($files_root, 0o755, true);
}
foreach ((array) ($_POST['attachments'] ?? []) as $a) {
    if (!is_array($a)) {
        continue;
    }
    $stored = (string) ($a['stored'] ?? $a['path'] ?? '');
    $real = PluginTicketmailerHook::safeResolveUnder($files_root, $stored);
    if ($real === null || !is_file($real)) {
        continue;
    }
    $id = (string) ($a['id'] ?? bin2hex(random_bytes(8)));
    $attachments[] = [
        'id'       => $id,
        'stored'   => basename($real),
        'path'     => $real,
        'filename' => (string) ($a['filename'] ?? basename($real)),
        'mime'     => PluginTicketmailerHook::trustedMime($real),
    ];
}
foreach ((array) ($_POST['inline_images'] ?? []) as $i) {
    if (!is_array($i)) {
        continue;
    }
    $stored = (string) ($i['stored'] ?? $i['path'] ?? '');
    $real = PluginTicketmailerHook::safeResolveUnder($files_root, $stored);
    if ($real === null || !is_file($real)) {
        continue;
    }
    $inline_images[] = [
        'id'     => (string) ($i['id'] ?? bin2hex(random_bytes(8))),
        'stored' => basename($real),
        'path'   => $real,
        'cid'    => (string) ($i['cid'] ?? ''),
        'mime'   => PluginTicketmailerHook::trustedMime($real),
    ];
}

if ($include_history) {
    $body_html = PluginTicketmailerHistory::appendToMessage(
        $body_html,
        PluginTicketmailerHistory::render($ticket),
    );
}
if ($selected_history_attachments !== []) {
    $attachments = array_merge(
        $attachments,
        PluginTicketmailerHistory::copySelectedAttachments($ticket, $selected_history_attachments),
    );
}

// Audit descriptors omit absolute paths.
$audit_attachments = [];
foreach ($attachments as $a) {
    $audit_attachments[] = [
        'id'       => $a['id'],
        'stored'   => $a['stored'],
        'filename' => $a['filename'],
        'mime'     => $a['mime'],
    ];
}
$audit_inline = [];
foreach ($inline_images as $i) {
    $audit_inline[] = [
        'id'     => $i['id'],
        'stored' => $i['stored'],
        'cid'    => $i['cid'],
        'mime'   => $i['mime'],
    ];
}

$payload = PluginTicketmailerComposer::build(
    $tickets_id,
    (int) Session::getLoginUserID(),
    $recipients_to,
    $recipients_cc,
    $recipients_bcc,
    $subject,
    $body_html,
    $attachments,
    $inline_images,
);

$log_id = PluginTicketmailerAudit::createIntent(
    $tickets_id,
    (int) Session::getLoginUserID(),
    $subject,
    $payload['body_html'],
    $payload['body_text'],
    $recipients_to,
    $recipients_cc,
    $recipients_bcc,
    $audit_attachments,
    $audit_inline,
    $mailbox_override && $mailbox_matches !== [],
    $mailbox_matches,
);

$result = PluginTicketmailerMailer::send($payload);
PluginTicketmailerAudit::markSmtpResult(
    $log_id,
    $result['status'],
    $result['error'],
    $result['msg_id'],
);

$timeline_recorded = false;
if ($result['status'] === 'sent') {
    $timeline = PluginTicketmailerTimeline::recordOutbound([
        'tickets_id'     => $tickets_id,
        'users_id'       => (int) Session::getLoginUserID(),
        'subject'        => $subject,
        'body_html'      => (string) $payload['body_html'],
        'body_text'      => (string) $payload['body_text'],
        'recipients_to'  => $recipients_to,
        'recipients_cc'  => $recipients_cc,
        'recipients_bcc' => $recipients_bcc,
        'attachments'    => $audit_attachments,
        'log_id'         => $log_id,
        'sent_at'        => date('Y-m-d H:i:s'),
        'from'           => (string) ($payload['from'] ?? ''),
        'requesttypes_id' => $requesttypes_id,
    ]);
    if ($timeline['ok']) {
        PluginTicketmailerAudit::markTimelineResult(
            $log_id,
            'recorded',
            $timeline['followups_id'],
            null,
        );
        $timeline_recorded = true;
        if (PluginTicketmailerConfig::setWaitingAfterSend($ticket)) {
            $ticket->update([
                'id'            => $tickets_id,
                'status'        => Ticket::WAITING,
                '_disablenotif' => 1,
            ]);
        }
    } else {
        PluginTicketmailerAudit::markTimelineResult(
            $log_id,
            'failed',
            null,
            $timeline['error'],
        );
    }
} else {
    // A7: SMTP failed — no successful-send followup; timeline_status stays pending.
}

Html::redirect(
    $timeline_recorded
        ? Ticket::getFormURLWithID($tickets_id)
        : $web . '/front/log_entry.php?id=' . $log_id,
);
