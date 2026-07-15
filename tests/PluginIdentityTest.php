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

        $this->assertStringContainsString('PLUGIN_TICKETMAILER_VERSION', $setup);
        $this->assertStringContainsString('function plugin_version_ticketmailer()', $setup);
        $this->assertStringContainsString('GLPI Ticket Email Client', $setup);
        $this->assertStringNotContainsString('ticketemailclient', $setup);
        $this->assertStringContainsString('function plugin_ticketmailer_install()', $hooks);
        $this->assertStringNotContainsString('function plugin_ticketemailclient_', $hooks);
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
}
