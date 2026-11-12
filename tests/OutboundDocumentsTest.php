<?php
use PHPUnit\Framework\TestCase;

final class OutboundDocumentsTest extends TestCase
{
    private function probe(string $scenario): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/outbound-documents-probe.php', $scenario],
            [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $errors);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testUploadsBecomeEntityScopedTicketDocumentsWithoutNotifications(): void
    {
        $r = $this->probe('success');
        self::assertTrue($r['result']['ok']);
        self::assertCount(1, $r['documents']);
        self::assertSame(9, $r['documents'][0]['entities_id']);
        self::assertSame(0, $r['documents'][0]['is_recursive']);
        self::assertArrayNotHasKey('itemtype', $r['documents'][0]);
        self::assertSame(['documents_id'=>101, 'itemtype'=>'Ticket', 'items_id'=>42,
            '_do_notif'=>false, '_disablenotif'=>1], $r['links'][0]);
        self::assertSame(1, $r['followups'][0]['_disablenotif']);
        self::assertSame([], $r['temporary']);
        self::assertSame('sent bytes', $r['audit_bytes']);
    }

    public function testSelectedPublicDocumentsAndEmptyMessagesCreateNoDuplicates(): void
    {
        foreach (['selected', 'empty'] as $scenario) {
            $r = $this->probe($scenario);
            self::assertTrue($r['result']['ok']);
            self::assertSame([], $r['documents']);
            self::assertSame([], $r['links']);
        }
    }

    public function testFailuresKeepExistingFollowupAndCleanStaging(): void
    {
        foreach (['document-failure', 'link-failure', 'missing-file', 'outside-storage', 'invalid-selected'] as $scenario) {
            $r = $this->probe($scenario);
            self::assertFalse($r['result']['ok'], $scenario);
            self::assertSame(77, $r['result']['followups_id']);
            self::assertNotEmpty($r['result']['error']);
            self::assertSame([], $r['temporary']);
            self::assertSame('sent bytes', $r['audit_bytes']);
        }
        $r = $this->probe('followup-failure');
        self::assertFalse($r['result']['ok']);
        self::assertNull($r['result']['followups_id']);
        self::assertSame([], $r['documents']);
    }

    public function testSameNamedUploadsUseSeparateTemporaryFiles(): void
    {
        $r = $this->probe('same-name');
        self::assertTrue($r['result']['ok']);
        self::assertCount(2, $r['documents']);
        self::assertNotSame($r['documents'][0]['_filename'], $r['documents'][1]['_filename']);
        self::assertSame([], $r['temporary']);
    }
}
