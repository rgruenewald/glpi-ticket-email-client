<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../setup.php';
require_once __DIR__ . '/../inc/hook.class.php';

final class Migration
{
    public static array $changes = [];
    public static bool $current = false;

    public function __construct(string $version) {}
    public function changeField(string $table, string $field, string $new, string $type, array $options): void
    {
        self::$changes[] = $table . '.' . $field;
    }
    public function addField(string $table, string $field, string $type, array $options = []): bool
    {
        if (self::$current) {
            return false;
        }
        self::$changes[] = $table . '.' . $field;
        return true;
    }
    public function addKey(string $table, string $field, string $key): void {}
    public function executeMigration(): void {}
}

final class SchemaMigrationDatabase
{
    public array $updates = [];
    public function fieldExists(string $table, string $field): bool { return Migration::$current; }
    public function update(string $table, array $values, array $where): bool
    {
        $this->updates[] = [$table, $values, $where];
        return true;
    }
}

final class SchemaMigrationTest extends TestCase
{
    #[Test]
    public function upgrades_a_v1_schema_and_only_backfills_existing_terminal_mail(): void
    {
        $this->verifyMigration(false);
    }

    #[Test]
    public function does_not_backfill_again_when_the_current_schema_is_present(): void
    {
        $this->verifyMigration(true);
    }

    private function verifyMigration(bool $current): void
    {
        global $DB;
        $previous = $DB ?? null;
        $DB = new SchemaMigrationDatabase();
        Migration::$current = $current;
        Migration::$changes = [];
        try {
            self::assertTrue(PluginTicketmailerHook::migrateSchema());
            if ($current) {
                self::assertSame([], Migration::$changes);
                self::assertSame([], $DB->updates);
            } else {
                self::assertContains('glpi_plugin_ticketmailer_logs.status', Migration::$changes);
                self::assertContains('glpi_plugin_ticketmailer_logs.timeline_status', Migration::$changes);
                self::assertContains('glpi_plugin_ticketmailer_configs.hide_native_answer', Migration::$changes);
                self::assertSame([['glpi_plugin_ticketmailer_logs', ['timeline_status' => 'recorded'], ['status' => ['sent', 'failed']]]], $DB->updates);
            }
        } finally {
            $DB = $previous;
        }
    }
}
