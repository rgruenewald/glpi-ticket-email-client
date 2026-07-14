<?php
/**
 * front/download.php — stream an outbound attachment after ticket-read auth.
 * URL carries only log_id + attachment_id; path is resolved server-side.
 */
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

$log_id        = (int) ($_GET['log_id'] ?? 0);
$attachment_id = (string) ($_GET['attachment_id'] ?? '');

if ($log_id <= 0 || $attachment_id === '') {
    Html::displayNotFoundError();
}

$entry = PluginTicketemailclientAudit::find($log_id);
if ($entry === null) {
    Html::displayNotFoundError();
}

$ticket = new Ticket();
if (!$ticket->getFromDB((int) $entry['tickets_id']) || !$ticket->canViewItem()) {
    Html::displayRightError();
}

$attachments = PluginTicketemailclientAudit::decodeJson((string) ($entry['attachments'] ?? ''));
$found = null;
foreach ($attachments as $a) {
    if (!is_array($a)) {
        continue;
    }
    if ((string) ($a['id'] ?? '') === $attachment_id) {
        $found = $a;
        break;
    }
}
if ($found === null) {
    Html::displayNotFoundError();
}

$files_root = GLPI_PLUGIN_DOC_DIR . '/ticketemailclient/' . (int) $entry['tickets_id'];
$stored     = (string) ($found['stored'] ?? $found['path'] ?? '');
$real       = PluginTicketemailclientHook::safeResolveUnder($files_root, $stored);
if ($real === null || !is_file($real)) {
    if ($stored !== '' && str_starts_with($stored, $files_root)) {
        $real = realpath($stored) ?: null;
        $root_real = realpath($files_root) ?: '';
        if ($real === null || $root_real === ''
            || (!str_starts_with($real, $root_real . DIRECTORY_SEPARATOR) && $real !== $root_real)
        ) {
            Html::displayNotFoundError();
        }
    } else {
        Html::displayNotFoundError();
    }
}

$filename = (string) ($found['filename'] ?? basename((string) $real));
$mime = PluginTicketmailerHook::trustedMime($real);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($real));
header(
    'Content-Disposition: attachment; filename="'
    . str_replace(['"', "\r", "\n"], '', $filename) . '"'
);
header('X-Content-Type-Options: nosniff');
readfile($real);
exit;
