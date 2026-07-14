<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!class_exists('CommonITILObject')) {
    class CommonITILObject
    {
        public const TIMELINE_ORDER_NATURAL = 'natural';
        public const TIMELINE_ORDER_REVERSE = 'reverse';
    }
}

if (!class_exists('Ticket')) {
    class Ticket
    {
        public static int $entityId = 0;

        public function getFromDB(int $id): bool
        {
            return $id > 0;
        }

        public function getField(string $field): int
        {
            return $field === 'entities_id' ? self::$entityId : 0;
        }
    }
}

final class TimelinePreferencesDatabase
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<string, mixed>|null */
    private ?array $currentRow = null;

    public function tableExists(string $table): bool
    {
        return $table === 'glpi_plugin_ticketemailclient_configs';
    }

    /** @param array{WHERE: array{entities_id: int}} $query */
    public function request(array $query): self
    {
        $this->currentRow = $this->rows[$query['WHERE']['entities_id']] ?? null;
        return $this;
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        return $this->currentRow;
    }
}

require_once __DIR__ . '/../inc/config.class.php';

final class TimelinePreferencesTest extends TestCase
{
    private TimelinePreferencesDatabase $database;

    protected function setUp(): void
    {
        $this->database = new TimelinePreferencesDatabase();
        $GLOBALS['DB'] = $this->database;
        $_SERVER['SCRIPT_NAME'] = '/front/ticket.form.php';
        $_GET = ['id' => 42];
        $_SESSION = [];
        Ticket::$entityId = 7;
    }

    #[Test]
    public function defaults_show_newest_entries_and_open_reply(): void
    {
        self::assertSame([
            'subject_prefix' => '[#%d]',
            'signature_html' => '',
            'set_waiting' => true,
            'timeline_newest_first' => true,
            'open_reply_on_ticket' => true,
            'recipient_autocomplete_show_email' => true,
        ], PluginTicketemailclientConfig::forEntity(7));

        PluginTicketemailclientConfig::applyTimelineOrderForCurrentTicket();
        self::assertSame(CommonITILObject::TIMELINE_ORDER_REVERSE, $_SESSION['glpitimeline_order']);
    }

    #[Test]
    public function configured_order_controls_glpi_timeline_before_rendering(): void
    {
        $this->database->rows[7] = [
            'subject_prefix' => '[#%d]',
            'signature_html' => '',
            'set_waiting' => 1,
            'timeline_newest_first' => 0,
            'open_reply_on_ticket' => 1,
        ];

        PluginTicketemailclientConfig::applyTimelineOrderForCurrentTicket();

        self::assertSame(CommonITILObject::TIMELINE_ORDER_NATURAL, $_SESSION['glpitimeline_order']);
    }

    #[Test]
    public function missing_privacy_setting_defaults_to_showing_email_addresses(): void
    {
        $this->database->rows[7] = [
            'subject_prefix' => '[#%d]',
            'signature_html' => '',
            'set_waiting' => 1,
            'timeline_newest_first' => 1,
            'open_reply_on_ticket' => 1,
        ];

        self::assertTrue(PluginTicketemailclientConfig::forEntity(7)['recipient_autocomplete_show_email']);
    }

    #[Test]
    public function configured_privacy_setting_hides_email_addresses(): void
    {
        $this->database->rows[7] = [
            'subject_prefix' => '[#%d]',
            'signature_html' => '',
            'set_waiting' => 1,
            'timeline_newest_first' => 1,
            'open_reply_on_ticket' => 1,
            'recipient_autocomplete_show_email' => 0,
        ];

        self::assertFalse(PluginTicketemailclientConfig::forEntity(7)['recipient_autocomplete_show_email']);
    }
}
