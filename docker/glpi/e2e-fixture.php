<?php
// Synthetic local fixtures. Never expose this entry point through HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/vendor/autoload.php';
(new Glpi\Kernel\Kernel())->boot();
if (getenv('TICKETMAILER_E2E') !== '1' || getenv('GLPI_SMTP_HOST') !== 'mailpit') {
    throw new RuntimeException('E2E fixtures require the local Mailpit stack');
}
global $DB;
// Use GLPI's cron context for trusted CLI fixture creation, not an anonymous web session.
$_SESSION['glpicronuserrunning'] = 'e2e-fixtures';
Config::setConfigurationValues('core', [
    'admin_email' => 'glpi@example.test',
    'admin_email_name' => 'GLPI E2E',
    'replyto_email' => 'glpi@example.test',
]);
function addTicket(int $entity = 0): int {
    $id = (new Ticket())->add([
        'name' => 'Ticketmailer E2E ' . date('c'),
        'content' => 'Synthetic end-to-end test ticket.',
        'entities_id' => $entity,
        'status' => Ticket::INCOMING,
        '_users_id_requester' => 2,
        '_disablenotif' => true,
    ]);
    if (!$id) {
        throw new RuntimeException('Could not create E2E ticket');
    }
    return (int) $id;
}
function addDocument(int $item, string $type, string $marker): int {
    global $DB;
    $relative = '_tmp/e2e-' . bin2hex(random_bytes(12)) . '.txt';
    file_put_contents(GLPI_DOC_DIR . '/' . $relative, $marker);
    $DB->insertOrDie('glpi_documents', [
        'name' => $marker, 'filename' => $marker . '.txt', 'filepath' => $relative,
        'mime' => 'text/plain', 'entities_id' => 0,
    ]);
    $id = (int) $DB->insertId();
    $DB->insertOrDie('glpi_documents_items', ['documents_id' => $id, 'itemtype' => $type, 'items_id' => $item]);
    return $id;
}
if (($argv[1] ?? '') === 'policy') {
    echo json_encode([
        'stored' => PluginTicketmailerReplyPolicy::resolveStored((int) $argv[2], (int) $argv[3]),
        'effective' => PluginTicketmailerReplyPolicy::effectivePolicy((int) $argv[2], (int) $argv[3]),
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit;
}
$ticket = addTicket();
$result = ['ticket' => $ticket];
$scenario = $argv[1] ?? 'basic';
if ($scenario === 'history') {
    $public = (new ITILFollowup())->add(['itemtype' => 'Ticket', 'items_id' => $ticket, 'content' => 'E2E_PUBLIC_CONTEXT', 'is_private' => 0, '_disablenotif' => true]);
    $private = (new ITILFollowup())->add(['itemtype' => 'Ticket', 'items_id' => $ticket, 'content' => 'E2E_PRIVATE_CONTEXT', 'is_private' => 1, '_disablenotif' => true]);
    if (!$public || !$private) {
        throw new RuntimeException('Could not create history');
    }
    $result['selected'] = addDocument($ticket, 'Ticket', 'E2E_SELECTED_FILE');
    $result['unselected'] = addDocument((int) $public, 'ITILFollowup', 'E2E_UNSELECTED_FILE');
    $result['private'] = addDocument((int) $private, 'ITILFollowup', 'E2E_PRIVATE_FILE');
    $result['foreign'] = addDocument(addTicket(), 'Ticket', 'E2E_FOREIGN_FILE');
}
if ($scenario === 'acl') {
    $suffix = bin2hex(random_bytes(5));
    $entity = (new Entity())->add(['name' => 'E2E own ' . $suffix, 'entities_id' => 0]);
    $foreign = (new Entity())->add(['name' => 'E2E foreign ' . $suffix, 'entities_id' => 0]);
    $child = (new Entity())->add(['name' => 'E2E child ' . $suffix, 'entities_id' => $entity]);
    if (!$entity || !$foreign || !$child) {
        throw new RuntimeException('Could not create entities');
    }
    $result['ownTicket'] = addTicket((int) $entity);
    $result['foreignTicket'] = addTicket((int) $foreign);
    $result['childTicket'] = addTicket((int) $child);
    $result['entity'] = (int) $entity;
    $result['foreignDocument'] = addDocument($result['foreignTicket'], 'Ticket', 'E2E_FOREIGN_ENTITY_FILE');
    $result['users'] = [];
    foreach (['writer', 'reader'] as $role) {
        $profile = (new Profile())->add(['name' => 'E2E ' . $role . ' ' . $suffix, 'interface' => 'central']);
        if (!$profile) {
            throw new RuntimeException('Could not create profile');
        }
        // Central UI needs basic ticket-read rights; only writer gets followup creation.
        foreach (['ticket' => Ticket::READALL | READ, 'followup' => $role === 'writer' ? ITILFollowup::ADDALLITEM : 0] as $name => $rights) {
            $DB->updateOrDie('glpi_profilerights', ['rights' => $rights], ['profiles_id' => $profile, 'name' => $name]);
        }
        $login = 'e2e-' . $role . '-' . $suffix;
        $user = (new User())->add(['name' => $login, 'password' => 'E2E-local-only-123!', 'password2' => 'E2E-local-only-123!', 'is_active' => 1]);
        if (!$user) {
            throw new RuntimeException('Could not create user');
        }
        // User::add may assign a default profile; replace only this new fixture user's grants.
        $DB->deleteOrDie('glpi_profiles_users', ['users_id' => $user]);
        (new Profile_User())->add(['users_id' => $user, 'profiles_id' => $profile, 'entities_id' => $entity, 'is_recursive' => 1, 'is_default_profile' => 1]);
        $result['users'][$role] = ['login' => $login, 'profile' => (int) $profile];
    }
    $DB->insertOrDie('glpi_plugin_ticketmailer_reply_policies', ['entities_id' => $entity, 'profiles_id' => null, 'mode' => 'available']);
}
if ($scenario === 'mailbox') {
    $address = 'collector-' . bin2hex(random_bytes(8)) . '@example.test';
    $DB->insertOrDie('glpi_mailcollectors', ['name' => 'E2E collector', 'login' => $address, 'is_active' => 1]);
    $result['mailbox'] = $address;
    $result['collector'] = (int) $DB->insertId();
}
echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
