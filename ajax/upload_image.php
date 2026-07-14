<?php
/**
 * ajax/upload_image.php — inline image under generated id.
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

if (empty($_FILES['image']) || (int) $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => __('File upload failed.', 'ticketemailclient')]);
    exit;
}

$max = PluginTicketemailclientConfig::uploadMaxSize();
if ((int) $_FILES['image']['size'] > $max) {
    http_response_code(413);
    echo json_encode(['error' => __('File is too large.', 'ticketemailclient')]);
    exit;
}

$dest_dir = GLPI_PLUGIN_DOC_DIR . '/ticketemailclient/' . $tickets_id;
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0o755, true);
}
$id = bin2hex(random_bytes(16));
$stored = $id . '.img';
$path = $dest_dir . '/' . $stored;
if (!move_uploaded_file((string) $_FILES['image']['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['error' => __('Could not store uploaded file.', 'ticketemailclient')]);
    exit;
}

$mime = PluginTicketmailerHook::trustedMime($path);
if (!str_starts_with($mime, 'image/')) {
    @unlink($path);
    http_response_code(415);
    echo json_encode(['error' => __('Only image files can be embedded inline.', 'ticketmailer')]);
    exit;
}
$cid = 'img-' . $id . '@ticketemailclient';

echo json_encode([
    'id'     => $id,
    'stored' => $stored,
    'path'   => $stored,
    'cid'    => $cid,
    'mime'   => $mime,
    'size'   => (int) $_FILES['image']['size'],
    'csrf'   => Session::getNewCSRFToken(true),
]);
