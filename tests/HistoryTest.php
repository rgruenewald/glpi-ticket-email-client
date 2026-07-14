<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../inc/history.class.php';

final class HistoryTest extends TestCase
{
    #[Test]
    public function appends_public_history_after_the_authored_message(): void
    {
        $body = PluginTicketmailerHistory::appendToMessage(
            '<p>My message</p>',
            '<blockquote><p>Public ticket history</p></blockquote>',
        );

        $this->assertSame(
            '<p>My message</p><hr><blockquote><p>Public ticket history</p></blockquote>',
            $body,
        );
    }

    #[Test]
    public function leaves_the_message_unchanged_when_history_is_empty(): void
    {
        $this->assertSame(
            '<p>My message</p>',
            PluginTicketmailerHistory::appendToMessage('<p>My message</p>', ''),
        );
    }
}
