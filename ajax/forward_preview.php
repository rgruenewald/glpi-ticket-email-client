<?php
/**
 * ajax/forward_preview.php — preview of a forward
 * payload. The forward form posts the ticket id here
 * and gets back the rendered subject + body, so the
 * user can review before sending.
 *
 * The single mode is `full_history` (description +
 * every followup). There is no `last_message` mode
 * (spec § A11, OQ1).
 */
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$tickets_id = (int) ($_POST['tickets_id'] ?? $_GET['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id) || !$ticket->canUpdateItem()) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
$forward = PluginTicketmailerForwarder::build($ticket);
echo json_encode([
    'mode'    => 'full_history',
    'subject' => $forward['subject'],
    'body_html' => $forward['body_html'],
    'body_text' => $forward['body_text'],
]);
