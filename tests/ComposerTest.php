<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!class_exists('User')) {
    class User
    {
        public function getFromDB(int $id): bool
        {
            return true;
        }

        public function getDefaultEmail(): string
        {
            return 'sender@example.test';
        }

        public function getFriendlyName(): string
        {
            return 'Sender';
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
}
