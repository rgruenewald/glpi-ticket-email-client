<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NamespaceCutoverTest extends TestCase
{
    private const REPO_ROOT = __DIR__ . '/..';

    #[Test]
    public function new_plugin_identity_is_the_only_active_bootstrap_identity(): void
    {
        $setup = (string) file_get_contents(self::REPO_ROOT . '/setup.php');
        $hooks = (string) file_get_contents(self::REPO_ROOT . '/hook.php');

        $this->assertStringContainsString('PLUGIN_TICKETEMAILCLIENT_VERSION', $setup);
        $this->assertStringContainsString('function plugin_version_ticketemailclient()', $setup);
        $this->assertStringContainsString('GLPI Ticket Email Client', $setup);
        $this->assertStringNotContainsString('ticketmailer', $setup);
        $this->assertStringContainsString('function plugin_ticketemailclient_install()', $hooks);
        $this->assertStringNotContainsString('function plugin_ticketmailer_', $hooks);
    }

    #[Test]
    public function greenfield_schema_contains_only_new_namespace_tables(): void
    {
        $install = (string) file_get_contents(self::REPO_ROOT . '/sql/install.sql');

        foreach ([
            'glpi_plugin_ticketemailclient_logs',
            'glpi_plugin_ticketemailclient_reply_policies',
            'glpi_plugin_ticketemailclient_configs',
        ] as $table) {
            $this->assertStringContainsString($table, $install);
        }
        $this->assertStringNotContainsString('glpi_plugin_ticketmailer_', $install);
    }
}
