<?php
/** Public ticket-history preview using the same authorization as composition. */
require_once __DIR__ . '/../inc/bootstrap.php';
Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');
$tickets_id = (int) ($_POST['tickets_id'] ?? $_GET['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id) || !PluginTicketmailerTimelineAction::canUse($ticket)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
try {
    PluginTicketmailerHistory::validateSelectedAttachments($ticket, (array) ($_POST['history_attachments'] ?? []));
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()]);
    exit;
}
$body = PluginTicketmailerHistory::render($ticket);
echo json_encode([
    'mode' => 'full_history',
    'subject' => (string) $ticket->getField('name'),
    'body_html' => $body,
    'body_text' => trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
]);
