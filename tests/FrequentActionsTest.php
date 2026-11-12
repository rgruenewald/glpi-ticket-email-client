<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../inc/history.class.php';
require_once __DIR__ . '/../inc/mailboxguard.class.php';
require_once __DIR__ . '/fixtures/frequent-actions.php';

final class FrequentActionsTest extends TestCase
{
    private mixed $previousDatabase;
    private bool $hadDatabase;

    protected function setUp(): void
    {
        static $file;
        $file ??= frequentActionsFile();
        $this->hadDatabase = array_key_exists('DB', $GLOBALS);
        $this->previousDatabase = $GLOBALS['DB'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadDatabase) {
            $GLOBALS['DB'] = $this->previousDatabase;
        } else {
            unset($GLOBALS['DB']);
        }
        Document::$records = [];
    }

    #[Test]
    public function mailbox_matches_keep_database_order_and_normalize_both_sides(): void
    {
        $GLOBALS['DB'] = new FrequentActionsDatabase(['glpi_mailcollectors' => [
            ['is_active' => 1, 'login' => ' SECOND@EXAMPLE.TEST '],
            ['is_active' => 1, 'login' => 'invalid'],
            ['is_active' => 0, 'login' => 'hidden@example.test'],
            ['is_active' => 1, 'login' => 'First@example.test'],
            ['is_active' => 1, 'login' => 'SECOND@example.test'],
            ['is_active' => 1, 'login' => 'other@example.test'],
        ]]);
        self::assertSame(['SECOND@example.test', 'First@example.test'],
            PluginTicketmailerMailboxGuard::findMatches([
                ' First@Example.Test ', 'second@example.test', 'hidden@example.test',
            ]));
        self::assertSame([], PluginTicketmailerMailboxGuard::findMatches([]));
        self::assertSame(1, $GLOBALS['DB']->queries);
    }

    #[Test]
    public function attachment_selection_preserves_order_deduplication_and_public_boundary(): void
    {
        $GLOBALS['DB'] = new FrequentActionsDatabase([
            'glpi_itilfollowups' => [
                ['id' => 20, 'itemtype' => 'Ticket', 'items_id' => 7, 'is_private' => 0],
                ['id' => 21, 'itemtype' => 'Ticket', 'items_id' => 7, 'is_private' => 1],
                ['id' => 22, 'itemtype' => 'Ticket', 'items_id' => 7, 'is_private' => 0],
                ['id' => 23, 'itemtype' => 'Ticket', 'items_id' => 8, 'is_private' => 0],
            ],
            'glpi_documents_items' => [
                ['itemtype' => 'ITILFollowup', 'items_id' => 22, 'documents_id' => 3],
                ['itemtype' => 'Ticket', 'items_id' => 7, 'documents_id' => 1],
                ['itemtype' => 'ITILFollowup', 'items_id' => 20, 'documents_id' => 2],
                ['itemtype' => 'ITILFollowup', 'items_id' => 20, 'documents_id' => 1],
                ['itemtype' => 'ITILFollowup', 'items_id' => 21, 'documents_id' => 4],
                ['itemtype' => 'ITILFollowup', 'items_id' => 23, 'documents_id' => 5],
                ['itemtype' => 'Ticket', 'items_id' => 7, 'documents_id' => 6],
                ['itemtype' => 'Ticket', 'items_id' => 7, 'documents_id' => 7],
            ],
        ]);
        foreach ([1, 2, 3, 4, 5] as $id) {
            Document::$records[$id] = ['id' => $id, 'filename' => $id . '.txt', 'filepath' => 'fixture.txt'];
        }
        Document::$records[7] = ['filename' => '', 'filepath' => '../outside.txt'];
        $attachments = PluginTicketmailerHistory::availableAttachments(new Ticket(['id' => 7]));
        self::assertSame([1, 2, 3], array_column($attachments, 'id'));
        self::assertSame(['1.txt', '2.txt', '3.txt'], array_column($attachments, 'filename'));
        self::assertSame('application/octet-stream', $attachments[0]['mime']);
        self::assertSame(3, $GLOBALS['DB']->queries);
    }

    #[Test]
    public function tickets_without_public_followups_still_return_direct_attachments(): void
    {
        $GLOBALS['DB'] = new FrequentActionsDatabase([
            'glpi_documents_items' => [
                ['itemtype' => 'Ticket', 'items_id' => 7, 'documents_id' => 1],
            ],
        ]);
        Document::$records[1] = ['filename' => 'direct.txt', 'filepath' => 'fixture.txt'];
        self::assertSame([1], array_column(
            PluginTicketmailerHistory::availableAttachments(new Ticket(['id' => 7])), 'id'));
        self::assertSame(2, $GLOBALS['DB']->queries);
    }
    #[Test]
    public function equal_filenames_preserve_source_order_and_selected_document_metadata(): void
    {
        $GLOBALS['DB'] = new FrequentActionsDatabase([
            'glpi_itilfollowups' => [
                ['id' => 20, 'itemtype' => 'Ticket', 'items_id' => 7, 'is_private' => 0],
                ['id' => 22, 'itemtype' => 'Ticket', 'items_id' => 7, 'is_private' => 0],
            ],
            'glpi_documents_items' => [
                ['itemtype' => 'ITILFollowup', 'items_id' => 22, 'documents_id' => 3],
                ['itemtype' => 'ITILFollowup', 'items_id' => 20, 'documents_id' => 2],
                ['itemtype' => 'Ticket', 'items_id' => 7, 'documents_id' => 1],
            ],
        ]);
        foreach ([1, 2, 3] as $id) {
            Document::$records[$id] = [
                'filename' => 'same.txt', 'filepath' => 'fixture.txt', 'mime' => 'text/plain',
            ];
        }
        $ticket = new Ticket(['id' => 7]);
        $attachments = PluginTicketmailerHistory::availableAttachments($ticket);
        self::assertSame([1, 2, 3], array_column($attachments, 'id'));
        self::assertSame('text/plain', $attachments[0]['mime']);
        self::assertSame('/plugins/ticketmailer/front/history_attachment.php?tickets_id=7&documents_id=1',
            $attachments[0]['preview_url']);
        self::assertNull(PluginTicketmailerHistory::resolveAttachment($ticket, 999));
        self::assertSame('same.txt', PluginTicketmailerHistory::resolveAttachment($ticket, 2)['filename']);
    }

}
