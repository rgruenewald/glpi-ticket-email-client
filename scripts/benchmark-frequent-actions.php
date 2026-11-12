<?php
declare(strict_types=1);

// Standalone, synthetic benchmark. No GLPI bootstrap, network, or persistent writes.
$source = $argv[1] ?? dirname(__DIR__);
require_once $source . '/inc/recipients.class.php';
require_once $source . '/inc/mailboxguard.class.php';
require_once $source . '/inc/history.class.php';
require_once __DIR__ . '/../tests/fixtures/frequent-actions.php';
frequentActionsFile();
class Ticket
{
    public function getField(string $name): int { return 7; }
}

function measure(string $name, callable $action, int $iterations): void
{
    global $DB;
    $expected = $action();
    for ($i = 0; $i < 5; ++$i) { $action(); }
    $samples = [];
    for ($sample = 0; $sample < 9; ++$sample) {
        Document::$loads = 0;
        $DB->queries = 0;
        $DB->requests = [];
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; ++$i) {
            $actual = $action();
        }
        $samples[] = (hrtime(true) - $start) / $iterations / 1000;
        if ($actual !== $expected) { throw new RuntimeException('Unstable result'); }
        $queries = $DB->queries / $iterations;
        $loads = Document::$loads / $iterations;
    }
    sort($samples);
    printf("%s median_us=%.3f min_us=%.3f max_us=%.3f metadata_queries=%.0f document_loads=%.0f hash=%s
",
        $name, $samples[4], $samples[0], $samples[8], $queries, $loads,
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)));
}

printf("PHP=%s samples=9 warmup=5 opcache=%s jit=%s
", PHP_VERSION,
    ini_get('opcache.enable_cli'), ini_get('opcache.jit'));
foreach ([10, 1000] as $size) {
    $recipients = $collectors = $followups = $links = [];
    Document::$records = [];
    for ($i = 1; $i <= $size; ++$i) {
        $recipients[] = 'Person' . $i . '@example.test';
        $collectors[] = ['is_active' => 1, 'login' => 'Person' . $i . '@example.test'];
        $followups[] = ['id' => $i, 'itemtype' => 'Ticket', 'items_id' => 7, 'is_private' => 0];
        $links[] = ['itemtype' => 'ITILFollowup', 'items_id' => $i, 'documents_id' => $i];
        Document::$records[$i] = ['filename' => $i . '.txt', 'filepath' => 'fixture.txt'];
    }
    $DB = new FrequentActionsDatabase([
        'glpi_mailcollectors' => $collectors,
        'glpi_itilfollowups' => $followups,
        'glpi_documents_items' => $links,
    ]);
    $raw = implode(';', $recipients);
    measure('parse/' . $size, fn() => PluginTicketmailerRecipients::parseRaw($raw), 30);
    measure('mailboxes/' . $size, fn() => PluginTicketmailerMailboxGuard::findMatches($recipients), 20);
    measure('attachments/' . $size, fn() => PluginTicketmailerHistory::availableAttachments(new Ticket()), 3);
}
