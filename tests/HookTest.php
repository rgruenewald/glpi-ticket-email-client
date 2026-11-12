<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../inc/hook.class.php';

final class HookTest extends TestCase
{
    #[Test]
    public function trusted_mime_uses_the_server_controlled_file_not_a_client_value(): void
    {
        if (!function_exists('mime_content_type')) {
            self::markTestSkipped('mime_content_type is unavailable.');
        }
        $path = tempnam(sys_get_temp_dir(), 'ticketmailer-mime-');
        self::assertNotFalse($path);
        file_put_contents($path, "plain text\n");

        try {
            $mime = PluginTicketmailerHook::trustedMime($path, 'application/x-client-supplied');

            self::assertNotSame('application/x-client-supplied', $mime);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function trusted_mime_uses_the_explicit_fallback_for_a_missing_file(): void
    {
        self::assertSame(
            'application/x-fallback',
            PluginTicketmailerHook::trustedMime('/missing/ticketmailer-file', 'application/x-fallback'),
        );
    }
}
