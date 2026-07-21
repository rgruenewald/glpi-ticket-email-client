<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!function_exists('__')) {
    function __(string $message, string $domain = ''): string
    {
        return $message;
    }
}
if (!class_exists('Ticket')) {
    class Ticket
    {
        public static bool $loadable = true;
        public static int $entityId = 27;

        public function getFromDB(int $id): bool
        {
            return self::$loadable;
        }

        public function getField(string $field): mixed
        {
            return $field === 'entities_id' ? self::$entityId : null;
        }
    }
}

if (!class_exists('Config')) {
    class Config
    {
        public static ?int $requestedEntity = null;
        public static array $sender = [
            'email' => 'entity-sender@example.test',
            'name' => 'Ticket Entity',
        ];

        public static function getEmailSender(?int $entities_id = null): array
        {
            self::$requestedEntity = $entities_id;
            return self::$sender;
        }
    }
}

require_once __DIR__ . '/../inc/composer.class.php';

final class ComposerTest extends TestCase
{
    #[Test]
    public function normalizes_escaped_editor_html_before_building_mime_parts(): void
    {
        $payload = PluginTicketmailerComposer::build(
            1,
            1,
            ['recipient@example.test'],
            [],
            [],
            'Subject',
            "&#60;p&#62;test&#60;/p&#62;\\r\\n<p>\u{00C2}\u{00A0}</p>\\r\\n<p>123</p>",
        );

        $this->assertSame(
            "<p>test</p>\n<p>\u{00A0}</p>\n<p>123</p>",
            $payload['body_html'],
        );
        $this->assertSame("test\n\n\u{00A0}\n\n123", $payload['body_text']);
    }

    #[Test]
    public function uses_the_ticket_entity_configured_sender_without_agent_fallback(): void
    {
        Ticket::$loadable = true;
        Ticket::$entityId = 27;
        Config::$sender = ['email' => 'entity-sender@example.test', 'name' => 'Ticket Entity'];
        // Production must call Config::getEmailSender() with this ticket entity.

        $payload = PluginTicketmailerComposer::build(
            91,
            4,
            ['recipient@example.test'],
            [],
            [],
            'Subject',
            '<p>Body</p>',
        );

        $this->assertSame(27, Config::$requestedEntity);
        $this->assertSame('entity-sender@example.test', $payload['from']);
        $this->assertSame('Ticket Entity', $payload['from_name']);
        $this->assertNotSame('sender@example.test', $payload['from']);
    }

    #[Test]
    public function accepts_the_sender_result_resolved_by_glpi_fallback(): void
    {
        Config::$sender = ['email' => 'global-sender@example.test', 'name' => 'Global Sender'];

        $payload = PluginTicketmailerComposer::build(
            91,
            4,
            ['recipient@example.test'],
            [],
            [],
            'Subject',
            '<p>Body</p>',
        );

        $this->assertSame('global-sender@example.test', $payload['from']);
        $this->assertSame('Global Sender', $payload['from_name']);
    }

    #[Test]
    public function rejects_a_missing_ticket_or_valid_sender(): void
    {
        Ticket::$loadable = false;
        try {
            PluginTicketmailerComposer::build(91, 4, [], [], [], 'Subject', '<p>Body</p>');
            $this->fail('Missing ticket must be rejected.');
        } catch (InvalidArgumentException) {
        }

        Ticket::$loadable = true;
        Config::$sender = ['email' => null, 'name' => null];
        $this->expectException(InvalidArgumentException::class);
        PluginTicketmailerComposer::build(91, 4, [], [], [], 'Subject', '<p>Body</p>');
    }
}
