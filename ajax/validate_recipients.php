<?php
/**
 * ajax/validate_recipients.php — live validation + mailbox warning.
 */
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$tickets_id = (int) ($_POST['tickets_id'] ?? $_GET['tickets_id'] ?? 0);
if ($tickets_id > 0) {
    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id) || !( $ticket->canUpdateItem() || (method_exists($ticket, 'canAddFollowups') && $ticket->canAddFollowups()) )) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
}

$raw = (string) ($_POST['value'] ?? $_GET['value'] ?? '');
// Also accept combined fields for mailbox scan.
$raw_to  = (string) ($_POST['recipients_to']  ?? $raw);
$raw_cc  = (string) ($_POST['recipients_cc']  ?? '');
$raw_bcc = (string) ($_POST['recipients_bcc'] ?? '');

$parsed = PluginTicketemailclientRecipients::parseRaw($raw !== '' ? $raw : $raw_to);
$all = array_merge(
    PluginTicketemailclientRecipients::parseRaw($raw_to)['valid'],
    PluginTicketemailclientRecipients::parseRaw($raw_cc)['valid'],
    PluginTicketemailclientRecipients::parseRaw($raw_bcc)['valid'],
);
if ($raw !== '' && $raw_to === $raw && $raw_cc === '' && $raw_bcc === '') {
    $all = $parsed['valid'];
}
$mailbox_matches = PluginTicketemailclientMailboxGuard::findMatches($all);

echo json_encode([
    'ok'              => $parsed['invalid'] === [],
    'count'           => count($parsed['valid']),
    'invalid'         => $parsed['invalid'],
    'mailbox'         => $mailbox_matches !== [],
    'mailbox_matches' => $mailbox_matches,
    'mailbox_note'    => __(
        'Match is best-effort against active collector logins that look like emails. Aliases, forwarding, and non-email logins are not detected.',
        'ticketemailclient',
    ),
]);
