<?php
// Isolated API doubles exercise the real document/timeline orchestration.
$scenario = $argv[1];
$root = sys_get_temp_dir() . '/ticketmailer-documents-' . bin2hex(random_bytes(8));
define('GLPI_DOC_DIR', $root);
define('GLPI_PLUGIN_DOC_DIR', $root . '/_plugins');
define('GLPI_TMP_DIR', $root . '/_tmp');
mkdir(GLPI_PLUGIN_DOC_DIR . '/ticketmailer/42', 0700, true);
mkdir(GLPI_TMP_DIR);
file_put_contents(GLPI_PLUGIN_DOC_DIR . '/ticketmailer/42/upload', 'sent bytes');
file_put_contents($root . '/existing.txt', 'existing bytes');
function __(string $s, string $domain = ''): string { return $s; }
class Plugin { public static function getWebDir(string $plugin): string { return '/plugins/' . $plugin; } }
class Ticket {
    public function getFromDB(int $id): bool { return true; }
    public function getField(string $name): int { return $name === 'entities_id' ? 9 : 42; }
}
class ITILFollowup {
    public static array $inputs = [];
    public function add(array $input): int|false {
        self::$inputs[] = $input;
        return $GLOBALS['scenario'] === 'followup-failure' ? false : 77;
    }
}
class Document {
    public static array $inputs = [];
    public function getFromDB(int $id): bool { return $id === 5; }
    public function getField(string $name): string {
        return ['filepath'=>'existing.txt', 'filename'=>'existing.txt', 'mime'=>'text/plain'][$name] ?? '';
    }
    public function add(array $input): int|false {
        self::$inputs[] = $input;
        if (!is_file(GLPI_TMP_DIR . '/' . $input['_filename'][0])) throw new RuntimeException('Missing staged file');
        if ($GLOBALS['scenario'] === 'document-failure') return false;
        return 100 + count(self::$inputs);
    }
}
class Document_Item {
    public static array $inputs = [];
    public function add(array $input): int|false {
        self::$inputs[] = $input;
        return $GLOBALS['scenario'] === 'link-failure' ? false : 1;
    }
}
class TestDatabase {
    public function request(string $table, array $where): array {
        if ($table === 'glpi_itilfollowups') return [];
        return [['items_id'=>42, 'documents_id'=>5]];
    }
}
$DB = new TestDatabase();
require __DIR__ . '/../../inc/hook.class.php';
require __DIR__ . '/../../inc/history.class.php';
require __DIR__ . '/../../inc/timeline.class.php';
$attachment = ['id'=>'a', 'stored'=>'upload', 'filename'=>'report.txt', 'mime'=>'text/plain'];
if ($scenario === 'selected') $attachment['source_documents_id'] = 5;
if ($scenario === 'invalid-selected') $attachment['source_documents_id'] = 6;
if ($scenario === 'outside-storage') $attachment['stored'] = '../../../existing.txt';
if ($scenario === 'missing-file') $attachment['stored'] = 'missing';
$attachments = $scenario === 'empty' ? [] : [$attachment];
if ($scenario === 'same-name') $attachments[] = $attachment;
try {
    $result = PluginTicketmailerTimeline::recordOutbound([
        'tickets_id'=>42, 'users_id'=>7, 'attachments'=>$attachments, 'body_html'=>'<p>Sent</p>',
    ]);
    echo json_encode(['result'=>$result, 'documents'=>Document::$inputs, 'links'=>Document_Item::$inputs,
        'followups'=>ITILFollowup::$inputs, 'temporary'=>glob(GLPI_TMP_DIR . '/*'),
        'audit_bytes'=>file_get_contents(GLPI_PLUGIN_DOC_DIR . '/ticketmailer/42/upload')], JSON_THROW_ON_ERROR);
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
    rmdir($root);
}
