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
$proactive_context = null;
if (!empty($_POST['compose_token'])) {
    $proactive_context = PluginTicketmailerProactive::context((string) $_POST['compose_token'], (int) Session::getLoginUserID());
    if ($proactive_context === null || !PluginTicketmailerProactive::canUse((int) $proactive_context['entities_id'])) {
        Html::displayRightError();
    }
    $proactive_lock = PluginTicketmailerProactive::lock($proactive_context);
    $proactive_state = PluginTicketmailerProactive::state($proactive_context);
    if ($proactive_state['phase'] !== 'open') {
        Session::addMessageAfterRedirect(__('This message has already been submitted. Check the ticket before sending again.', 'ticketmailer'), true, WARNING);
        Html::redirect($proactive_state['tickets_id'] > 0 ? Ticket::getFormURLWithID((int) $proactive_state['tickets_id']) : Ticket::getSearchURL());
        exit;
    }
    $tickets_id = 0;
} elseif ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id) || !($ticket->canUpdateItem() || $ticket->canAddFollowups())) {
    Html::displayRightError();
}
$web = Plugin::getWebDir('ticketmailer');
$delivery_mode = (string) ($_POST['delivery_mode'] ?? 'email');
if ($delivery_mode !== 'email') {
    $error = new \Glpi\Exception\Http\BadRequestHttpException();
    $error->setMessageToDisplay(__('Unsupported delivery mode.', 'ticketmailer'));
    throw $error;
}
$human_subject = trim((string) ($_POST['subject'] ?? ''));
$new_conversation = $proactive_context === null && !empty($_POST['new_conversation']);
$subject_has_unsafe_header_characters = preg_match('/[\x00\r\n]/', $human_subject) === 1;
$normalized_human_subject = PluginTicketmailerConfig::assembleSubject($human_subject, $tickets_id, true);
$subject = PluginTicketmailerConfig::assembleSubject($human_subject, $tickets_id, $new_conversation);
$body_html = (string) ($_POST['body_html'] ?? '');
$body_text = (string) ($_POST['body_text'] ?? '');
$include_history = $proactive_context === null && !empty($_POST['include_history']);
$set_waiting = $proactive_context === null && !empty($_POST['set_waiting']);
$set_solved = $proactive_context === null && !empty($_POST['set_solved']);
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
if ($set_waiting && $set_solved) {
    $errors[] = __('Choose either waiting or solved, not both.', 'ticketmailer');
    $set_waiting = false;
    $set_solved = false;
}
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
if ($normalized_human_subject === '') {
    $errors[] = __('Subject is required.', 'ticketmailer');
} elseif ($subject_has_unsafe_header_characters) {
    $errors[] = __('Subject contains invalid characters.', 'ticketmailer');
}
if (trim(strip_tags($body_html)) === '') {
    $errors[] = __('Body is required.', 'ticketmailer');
}
if ($proactive_context !== null && $recipients_to === []) {
    $errors[] = __('At least one To recipient is required.', 'ticketmailer');
} elseif (!PluginTicketmailerRecipients::hasAny($recipients_to, $recipients_cc, $recipients_bcc)) {
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

if ($proactive_context === null && $selected_history_attachments !== []) {
    try {
        PluginTicketmailerHistory::validateSelectedAttachments($ticket, $selected_history_attachments);
    } catch (InvalidArgumentException) {
        $errors[] = __('A selected history attachment is no longer available.', 'ticketmailer');
    }
}

$uploaded_attachments = [];
$uploaded_images = [];
if ($proactive_context !== null) {
    $sender = Config::getEmailSender((int) $proactive_context['entities_id']);
    if (!filter_var($sender['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('No valid sender is configured for the ticket entity.', 'ticketmailer');
    }
    foreach (['attachments'=>'uploaded_attachments', 'inline_images'=>'uploaded_images'] as $field=>$variable) {
        foreach ((array) ($_POST[$field] ?? []) as $raw) {
            $file = is_array($raw) ? $raw : null;
            $stored = is_array($file) ? (string) ($file['stored'] ?? '') : '';
            $path = PluginTicketmailerHook::safeResolveUnder(PluginTicketmailerProactive::directory($proactive_context) . '/uploads', $stored);
            if ($stored === '' || $path === null || !is_file($path) || is_link($path)) {
                $errors[] = __('An uploaded file is unavailable. Please attach it again.', 'ticketmailer');
                continue;
            }
            $$variable[] = $file;
        }
    }
}

if ($errors !== [] && $proactive_context !== null) {
    $editor_id = 'ticketmailer-new-body';
    $body_editor = Html::textarea(['name'=>'body_html', 'value'=>$body_html, 'editor_id'=>$editor_id,
        'enable_richtext'=>true, 'enable_images'=>true, 'enable_fileupload'=>false, 'rows'=>14, 'display'=>false]);
    Html::header(__('New email', 'ticketmailer'), $_SERVER['PHP_SELF'], 'helpdesk', 'PluginTicketmailerProactive');
    echo TemplateRenderer::getInstance()->render('@ticketmailer/compose.html.twig', [
        'proactive'=>true, 'compose_token'=>$proactive_context['token'],
        'uploaded_attachments'=>$uploaded_attachments, 'uploaded_images'=>$uploaded_images,
        'entity_name'=>Dropdown::getDropdownName('glpi_entities', (int) $proactive_context['entities_id']),
        'subject'=>$human_subject, 'new_conversation'=>true, 'body_editor'=>$body_editor, 'editor_id'=>$editor_id,
        'recipients_to_raw'=>$recipients_to_raw, 'recipients_cc_raw'=>$recipients_cc_raw, 'recipients_bcc_raw'=>$recipients_bcc_raw,
        'csrf_token'=>Session::getNewCSRFToken(), 'ajax_csrf'=>Session::getNewCSRFToken(true),
        'errors'=>$errors, 'mailbox_matches'=>$mailbox_matches, 'mailbox_override'=>$mailbox_override,
        'send_url'=>$web . '/front/send.php', 'upload_url'=>$web . '/ajax/upload.php', 'image_url'=>$web . '/ajax/upload_image.php',
        'validate_url'=>$web . '/ajax/validate_recipients.php', 'user_autocomplete_url'=>$web . '/ajax/autocomplete_users.php',
        'user_autocomplete_show_email'=>false,
        'upload_max_size_label'=>PluginTicketmailerConfig::uploadMaxSizeLabel(),
    ]);
    Html::footer();
    exit;
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
        'subject'            => $human_subject,
        'new_conversation'   => $new_conversation,
        'body_editor'        => $body_editor,
        'editor_id'          => $editor_id,
        'followup_template_dropdown' => PluginTicketmailerTimelineAction::followupTemplateDropdown(),
        'solution_template_dropdown' => PluginTicketmailerTimelineAction::solutionTemplateDropdown(),
        'followup_source_dropdown' => PluginTicketmailerTimelineAction::followupSourceDropdown(),
        'followup_template_url' => PluginTicketmailerTimelineAction::followupTemplateUrl(),
        'solution_template_url' => PluginTicketmailerTimelineAction::solutionTemplateUrl(),
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
        'set_waiting'        => $set_waiting,
        'set_solved'         => $set_solved,
        'history_attachments' => PluginTicketmailerHistory::availableAttachments($ticket),
        'selected_history_attachments' => $selected_history_attachments,
        'native_followup_form' => PluginTicketmailerTimelineAction::nativeFollowupForm($ticket),
    ]);
    Html::footer();
    exit;
}

// The durable "creating" state prevents a repeated POST from creating another ticket after a crash.
if ($proactive_context !== null) {
    PluginTicketmailerProactive::saveState($proactive_context, ['phase'=>'creating', 'tickets_id'=>0]);
    $tickets_id = (int) $ticket->add(PluginTicketmailerProactive::ticketInput(
        (int) $proactive_context['entities_id'], $normalized_human_subject, $parsed_to['valid'], $parsed_cc['valid'],
    ));
    if ($tickets_id <= 0) {
        PluginTicketmailerProactive::saveState($proactive_context, ['phase'=>'open', 'tickets_id'=>0]);
        Session::addMessageAfterRedirect(__('Could not create the ticket. No email was sent.', 'ticketmailer'), true, ERROR);
        Html::redirect($web . '/front/new.php');
        exit;
    }
    PluginTicketmailerProactive::saveState($proactive_context, ['phase'=>'created', 'tickets_id'=>$tickets_id]);
    if (!$ticket->getFromDB($tickets_id) || !$ticket->canViewItem() || !$ticket->canUpdateItem()
        || (int) $ticket->getField('entities_id') !== (int) $proactive_context['entities_id']) {
        Session::addMessageAfterRedirect(__('Ticket created, but its entity or permissions changed. No email was sent.', 'ticketmailer'), true, ERROR);
        Html::redirect(Ticket::getSearchURL());
        exit;
    }
    PluginTicketmailerProactive::adoptUploads($proactive_context, $tickets_id);
    $subject = PluginTicketmailerConfig::assembleSubject($human_subject, $tickets_id, false);
    $signature = PluginTicketmailerConfig::contentForTicket($ticket)['signature'];
    if ($signature !== '') {
        $body_html .= '<div class="ticketmailer-signature">' . $signature . '</div>';
    }
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
if ($proactive_context === null && $selected_history_attachments !== []) {
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

try {
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
} catch (InvalidArgumentException $error) {
    Html::addErrorMessage($error->getMessage());
    Html::back();
    exit;
}

$delivery = PluginTicketmailerSendservice::deliver($ticket, $payload, $audit_attachments, $audit_inline,
    $mailbox_override, $mailbox_matches, $new_conversation, $requesttypes_id, $set_waiting, $set_solved, $proactive_context);
$log_id = $delivery['log_id'];
$timeline_recorded = $delivery['timeline_recorded'];

Html::redirect(
    $timeline_recorded
        ? Ticket::getFormURLWithID($tickets_id)
        : $web . '/front/log_entry.php?id=' . $log_id,
);
