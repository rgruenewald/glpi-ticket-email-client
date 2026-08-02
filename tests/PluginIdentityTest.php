<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PluginIdentityTest extends TestCase
{
    private const REPO_ROOT = __DIR__ . '/..';

    #[Test]
    public function ticketmailer_is_the_only_active_bootstrap_identity(): void
    {
        $setup = (string) file_get_contents(self::REPO_ROOT . '/setup.php');
        $hooks = (string) file_get_contents(self::REPO_ROOT . '/hook.php');

        $this->assertStringContainsString("PLUGIN_TICKETMAILER_VERSION', '2.3.0'", $setup);
        $this->assertStringContainsString('function plugin_version_ticketmailer()', $setup);
        $this->assertStringContainsString('GLPI Ticket Email Client', $setup);
        $this->assertStringNotContainsString('ticketemailclient', $setup);
        $this->assertStringContainsString('function plugin_ticketmailer_install()', $hooks);
        $this->assertStringNotContainsString('function plugin_ticketemailclient_', $hooks);
    }

    #[Test]
    public function durable_documentation_reports_the_canonical_plugin_version(): void
    {
        foreach (['CONTEXT.md', 'README.md'] as $path) {
            $documentation = (string) file_get_contents(self::REPO_ROOT . '/' . $path);

            $this->assertStringContainsString('2.3.0', $documentation, $path);
            $this->assertStringNotContainsString('2.0.0', $documentation, $path);
        }
    }

    #[Test]
    public function compatibility_methods_keep_their_public_static_signatures(): void
    {
        require_once self::REPO_ROOT . '/inc/audit.class.php';
        require_once self::REPO_ROOT . '/inc/recipients.class.php';
        require_once self::REPO_ROOT . '/inc/config.class.php';

        $expected = [
            'PluginTicketmailerAudit::record' => 'int $tickets_id, int $users_id, string $subject, ?string $body_html, ?string $body_text, array $recipients_to, array $recipients_cc, array $recipients_bcc, array $attachments, array $inline_images, string $status, ?string $error_message, ?string $remote_msg_id, string $timeline_status = pending, ?int $followups_id = null, ?string $timeline_error = null, bool $mailbox_override = false, array $mailbox_matches = [], bool $new_conversation = false: int',
            'PluginTicketmailerRecipients::normalise' => 'string $raw: array',
            'PluginTicketmailerRecipients::hasInvalid' => 'array $addresses: bool',
            'PluginTicketmailerConfig::smtpUsername' => ': string',
        ];

        foreach ($expected as $callable => $signature) {
            [$class, $methodName] = explode('::', $callable);
            $method = new ReflectionMethod($class, $methodName);

            $this->assertTrue($method->isPublic(), $callable);
            $this->assertTrue($method->isStatic(), $callable);
            $this->assertSame($signature, self::methodSignature($method), $callable);
        }
    }

    #[Test]
    public function greenfield_schema_uses_the_stable_ticketmailer_tables(): void
    {
        $install = (string) file_get_contents(self::REPO_ROOT . '/sql/install.sql');

        foreach ([
            'glpi_plugin_ticketmailer_logs',
            'glpi_plugin_ticketmailer_reply_policies',
            'glpi_plugin_ticketmailer_configs',
        ] as $table) {
            $this->assertStringContainsString($table, $install);
        }
        $this->assertStringNotContainsString('glpi_plugin_ticketemailclient_', $install);
    }

    private static function methodSignature(ReflectionMethod $method): string
    {
        $parameters = array_map(static function (ReflectionParameter $parameter): string {
            $signature = (string) $parameter->getType() . ' $' . $parameter->getName();
            if (!$parameter->isDefaultValueAvailable()) {
                return $signature;
            }

            $default = $parameter->getDefaultValue();
            return $signature . ' = ' . match (true) {
                $default === null => 'null',
                $default === true => 'true',
                $default === false => 'false',
                $default === [] => '[]',
                default => (string) $default,
            };
        }, $method->getParameters());

        return implode(', ', $parameters) . ': ' . (string) $method->getReturnType();
    }
}
