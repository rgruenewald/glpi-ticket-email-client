<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../inc/config.class.php';

final class SubjectRoutingTest extends TestCase
{
    #[Test]
    public function linked_subject_uses_server_ticket_marker(): void
    {
        self::assertSame(
            '[GLPI #42] Updated subject',
            PluginTicketmailerConfig::assembleSubject('[GLPI #99] Updated subject', 42, false),
        );
    }

    #[Test]
    public function current_markers_are_removed_from_editable_subject(): void
    {
        self::assertSame('Updated subject', PluginTicketmailerConfig::humanSubject('[GLPI #42] Updated subject', 42));
        self::assertSame('Updated subject', PluginTicketmailerConfig::humanSubject('[GLPI #0000042] Updated subject', 42));
        self::assertSame('Updated subject', PluginTicketmailerConfig::humanSubject('[42] Updated subject', 42));
    }

    #[Test]
    public function explicit_new_conversation_omits_all_routing_markers(): void
    {
        self::assertSame('Updated subject', PluginTicketmailerConfig::assembleSubject('[GLPI #99] Updated subject', 42, true));
    }

    #[Test]
    public function unsafe_subject_text_is_preserved_for_validation(): void
    {
        self::assertSame("Updated\r\nBcc: forged@example.com", PluginTicketmailerConfig::assembleSubject("Updated\r\nBcc: forged@example.com", 42, false));
    }
}
