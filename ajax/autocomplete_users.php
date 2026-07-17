<?php
/**
 * ajax/autocomplete_users.php — ticket-scoped GLPI user recipient suggestions.
 */
require_once __DIR__ . '/../inc/bootstrap.php';


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

global $DB;
$results = [];
$needle = '%' . $DB->escape($query) . '%';
$entities = getSonsOf('glpi_entities', (int) $ticket->getField('entities_id'));
$userSearch = $DB->request([
    'SELECT' => ['glpi_users.name', 'glpi_users.realname', 'glpi_users.firstname', 'glpi_useremails.email'],
    'DISTINCT' => true,
    'FROM' => 'glpi_users',
    'INNER JOIN' => [
        'glpi_profiles_users' => ['FKEY' => ['glpi_users' => 'id', 'glpi_profiles_users' => 'users_id']],
    ],
    'LEFT JOIN' => [
        'glpi_useremails' => [
            'FKEY' => ['glpi_users' => 'id', 'glpi_useremails' => 'users_id'],
            'AND' => ['glpi_useremails.is_default' => 1],
        ],
    ],
    'WHERE' => [
        'glpi_users.is_active' => 1,
        'glpi_users.is_deleted' => 0,
        'glpi_profiles_users.entities_id' => $entities,
        'OR' => [
            ['glpi_users.name' => ['LIKE', $needle]],
            ['glpi_users.realname' => ['LIKE', $needle]],
            ['glpi_users.firstname' => ['LIKE', $needle]],
            ['glpi_useremails.email' => ['LIKE', $needle]],
        ],
    ],
    'LIMIT' => 10,
]);
foreach ($userSearch as $row) {
    $email = trim((string) ($row['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    $label = trim((string) ($row['realname'] ?? '') . ' ' . (string) ($row['firstname'] ?? ''));
    $results[] = ['label' => $label !== '' ? $label : (string) $row['name'], 'email' => $email];
}

echo json_encode(['results' => $results]);
