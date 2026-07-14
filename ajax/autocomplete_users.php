<?php
/**
 * ajax/autocomplete_users.php — ticket-scoped GLPI user recipient suggestions.
 */
include_once __DIR__ . '/../../../inc/includes.php';


Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id) || !PluginTicketmailerTimelineAction::canUse($ticket)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$query = trim((string) ($_POST['query'] ?? ''));
if (mb_strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
if ($userSearch = User::getSqlSearchResult(false, 'all', (int) $ticket->getField('entities_id'), 0, [], $query, 0, 10)) {
    foreach ($userSearch as $row) {
        $email = trim((string) ($row['default_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $label = trim((string) ($row['realname'] ?? '') . ' ' . (string) ($row['firstname'] ?? ''));
        if ($label === '') {
            $label = (string) ($row['name'] ?? '');
        }
        if ($label === '') {
            continue;
        }

        $results[] = [
            'label' => $label,
            'email' => $email,
        ];
    }
}

echo json_encode(['results' => $results]);
