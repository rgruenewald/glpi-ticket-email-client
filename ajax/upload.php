<?php
/**
 * ajax/upload.php — store attachment under generated id (v2 A6).
 */
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

$tickets_id = (int) ($_POST['tickets_id'] ?? $_GET['tickets_id'] ?? 0);
if ($tickets_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => __('A ticket is required.', 'ticketemailclient')]);
    exit;
}
$ticket = new Ticket();
if (!$ticket->getFromDB($tickets_id) || !( $ticket->canUpdateItem() || (method_exists($ticket, 'canAddFollowups') && $ticket->canAddFollowups()) )) {
    http_response_code(403);
    echo json_encode(['error' => __('You are not allowed to add attachments to this ticket.', 'ticketemailclient')]);
    exit;
}

if (empty($_FILES['file']) || (int) $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => __('File upload failed.', 'ticketemailclient')]);
    exit;
}

$max = PluginTicketemailclientConfig::uploadMaxSize();
if ((int) $_FILES['file']['size'] > $max) {
    http_response_code(413);
    echo json_encode(['error' => __('File is too large.', 'ticketemailclient')]);
    exit;
}

$dest_dir = GLPI_PLUGIN_DOC_DIR . '/ticketemailclient/' . $tickets_id;
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0o755, true);
}
$id = bin2hex(random_bytes(16));
$orig = basename((string) $_FILES['file']['name']);
$ext = pathinfo($orig, PATHINFO_EXTENSION);
$stored = $id . ($ext !== '' ? ('.' . preg_replace('/[^A-Za-z0-9]+/', '', $ext)) : '');
$path = $dest_dir . '/' . $stored;
if (!move_uploaded_file((string) $_FILES['file']['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['error' => __('Could not store uploaded file.', 'ticketemailclient')]);
    exit;
}

$mime = PluginTicketmailerHook::trustedMime($path);

echo json_encode([
    'id'       => $id,
    'stored'   => $stored,
    'path'     => $stored,
    'filename' => $orig,
    'mime'     => $mime,
    'size'     => (int) $_FILES['file']['size'],
    'csrf'     => Session::getNewCSRFToken(true),
]);
