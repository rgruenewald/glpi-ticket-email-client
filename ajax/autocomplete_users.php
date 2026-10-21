<?php
/**
 * ajax/autocomplete_users.php — ticket-scoped GLPI user recipient suggestions.
 */
require_once __DIR__ . '/../inc/bootstrap.php';


Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
$context = PluginTicketmailerProactive::context((string) ($_POST['compose_token'] ?? ''), (int) Session::getLoginUserID());
if ($context !== null && PluginTicketmailerProactive::canUse((int) $context['entities_id'])) {
    $entities_id = (int) $context['entities_id'];
} else {
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id) || !PluginTicketmailerTimelineAction::canUse($ticket)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$entities_id = (int) $ticket->getField('entities_id');
}

$query = trim((string) ($_POST['query'] ?? ''));
if (mb_strlen($query) < 2) {
    echo json_encode(['results' => [], 'csrf' => Session::getNewCSRFToken(true)]);
    exit;
}

global $DB;
$results = [];
// GLPI escapes request values; escape LIKE wildcards, not SQL literals.
$needle = '%' . addcslashes(mb_substr($query, 0, 100), '%_\\') . '%';
$entities = array_values(array_map('intval', $_SESSION['glpiactiveentities'] ?? []));
if ($entities === []) {
    echo json_encode(['results' => [], 'csrf' => Session::getNewCSRFToken(true)]);
    exit;
}
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
        'AND' => [getEntitiesRestrictCriteria('glpi_profiles_users', '', $entities, true)],
        'OR' => [
            ['glpi_users.name' => ['LIKE', $needle]],
            ['glpi_users.realname' => ['LIKE', $needle]],
            ['glpi_users.firstname' => ['LIKE', $needle]],
            ['glpi_useremails.email' => ['LIKE', $needle]],
        ],
    ],
    'ORDERBY' => ['glpi_users.realname', 'glpi_users.firstname', 'glpi_users.name', 'glpi_useremails.email'],
    'LIMIT' => 10,
]);
foreach ($userSearch as $row) {
    $email = trim((string) ($row['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    $label = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['realname'] ?? ''));
    $results[] = ['label' => $label !== '' ? $label : (string) $row['name'], 'email' => $email];
}

echo json_encode(['results' => $results, 'csrf' => Session::getNewCSRFToken(true)]);
