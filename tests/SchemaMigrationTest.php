<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../inc/hook.class.php';

final class SchemaMigrationDatabase
{
    /** @var array<string, bool> */
    public array $fields;

    /** @var list<string> */
    public array $queries = [];

    /** @param array<string, bool> $fields */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    public function fieldExists(string $table, string $field): bool
    {
        return $this->fields[$table . '.' . $field] ?? false;
    }

    public function doQuery(string $query): void
    {
        $this->queries[] = $query;
    }
}

final class SchemaMigrationTest extends TestCase
{
    #[Test]
    public function upgrades_a_v1_schema_without_replaying_completed_migrations(): void
    {
        global $DB;

        $previous = $DB ?? null;
        $DB = new SchemaMigrationDatabase([
            'glpi_plugin_ticketmailer_logs.followups_id' => false,
            'glpi_plugin_ticketmailer_configs.timeline_newest_first' => false,
            'glpi_plugin_ticketmailer_configs.open_reply_on_ticket' => false,
            'glpi_plugin_ticketmailer_configs.recipient_autocomplete_show_email' => false,
            'glpi_plugin_ticketmailer_configs.notificationtemplates_id' => false,
        ]);

        try {
            self::assertTrue(PluginTicketmailerHook::migrateSchema(__DIR__ . '/../sql'));
            self::assertCount(8, $DB->queries);
            self::assertStringContainsString('ADD COLUMN followups_id', $DB->queries[1]);
            self::assertStringContainsString('ADD COLUMN timeline_newest_first', $DB->queries[4]);
            self::assertStringContainsString('ADD COLUMN open_reply_on_ticket', $DB->queries[5]);
            self::assertStringNotContainsString('ADD COLUMN timeline_newest_first', $DB->queries[5]);
            self::assertStringContainsString('ADD COLUMN recipient_autocomplete_show_email', $DB->queries[6]);
            self::assertStringContainsString('ADD COLUMN notificationtemplates_id', $DB->queries[7]);
        } finally {
            $DB = $previous;
        }
    }

    #[Test]
    public function skips_all_schema_migrations_when_the_current_schema_is_present(): void
    {
        global $DB;

        $previous = $DB ?? null;
        $DB = new SchemaMigrationDatabase([
            'glpi_plugin_ticketmailer_logs.followups_id' => true,
            'glpi_plugin_ticketmailer_configs.timeline_newest_first' => true,
            'glpi_plugin_ticketmailer_configs.open_reply_on_ticket' => true,
            'glpi_plugin_ticketmailer_configs.recipient_autocomplete_show_email' => true,
            'glpi_plugin_ticketmailer_configs.notificationtemplates_id' => true,
        ]);

        try {
            self::assertTrue(PluginTicketmailerHook::migrateSchema(__DIR__ . '/../sql'));
            self::assertSame([], $DB->queries);
        } finally {
            $DB = $previous;
        }
    }
}
