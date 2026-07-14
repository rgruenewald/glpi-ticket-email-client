<?php
/**
 * Streams a public ticket attachment for inspection after ticket-read authorization.
 * The document path is never accepted from the request.
 */
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

$tickets_id = (int) ($_GET['tickets_id'] ?? 0);
$documents_id = (int) ($_GET['documents_id'] ?? 0);
if ($tickets_id <= 0 || $documents_id <= 0) {
    Html::displayNotFoundError();
}

$ticket = new Ticket();
if (!$ticket->getFromDB($tickets_id) || !$ticket->canViewItem()) {
    Html::displayRightError();
}

$attachment = PluginTicketemailclientHistory::resolveAttachment($ticket, $documents_id);
if ($attachment === null) {
    Html::displayNotFoundError();
}

// MIME metadata originates in a user-uploaded document record; accept only a
// header-safe media type, then prefer the server's detected value.
$mime = 'application/octet-stream';
if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+\/[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $attachment['mime'])) {
    $mime = $attachment['mime'];
}
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($attachment['path']);
    if (is_string($detected)
        && preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+\/[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $detected)
    ) {
        $mime = $detected;
    }
}
$filename = str_replace(['"', "\r", "\n"], '', $attachment['filename']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($attachment['path']));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Security-Policy: sandbox; default-src \'none\';');
header('X-Content-Type-Options: nosniff');
readfile($attachment['path']);
exit;
