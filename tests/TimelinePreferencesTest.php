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

if (!function_exists('_x')) {
    function _x(string $context, string $message): string
    {
        return $context . ':' . $message;
    }
}

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
        public static bool $authorized = true;
        /** @var array<string, mixed> */
        public array $fields = ['id' => 42];

        public function getFromDB(int $id): bool
        {
            return $id > 0;
        }

        public function getField(string $field): int
        {
            return $field === 'entities_id' ? self::$entityId : (int) ($this->fields[$field] ?? 0);
        }

        public function canViewItem(): bool
        {
            return self::$authorized;
        }

        public function canUpdateItem(): bool
        {
            return self::$authorized;
        }
    }
}

class TimelineActionTicket extends Ticket
{
    public static bool $authorized = true;
    /** @var array<string, mixed> */
    public array $fields = ['id' => 42];

    public function canViewItem(): bool
    {
        return self::$authorized;
    }

    public function canUpdateItem(): bool
    {
        return self::$authorized;
    }

    public function getField(string $field): int
    {
        return $field === 'entities_id' ? 7 : (int) ($this->fields[$field] ?? 0);
    }
}

if (!class_exists('ITILFollowup')) {
    class ITILFollowup
    {
        /** @var array<string, mixed> */
        public array $fields = [];
        public bool $initialized = false;

        public function getEmpty(): void
        {
            $this->initialized = true;
            $this->fields = ['is_private' => 0];
        }
        public static function getIcon(): string
        {
            return 'ti ti-message-reply';
        }
    }
}

if (!class_exists('PluginTicketmailerReplyPolicy')) {
    class PluginTicketmailerReplyPolicy
    {
        public static function isEmailReplyAvailable(int $entities_id, ?int $profiles_id): bool
        {
            return true;
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
        return $table === 'glpi_plugin_ticketmailer_configs';
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

    /** @param array<string, mixed> $values @param array{entities_id:int} $where */
    public function updateOrInsert(string $table, array $values, array $where): void
    {
        $id = $where['entities_id'];
        $this->rows[$id] = array_merge($this->rows[$id] ?? [], $values);
    }
}

require_once __DIR__ . '/../inc/config.class.php';
require_once __DIR__ . '/../inc/timelineaction.class.php';

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
        TimelineActionTicket::$authorized = true;
    }

    #[Test]
    public function defaults_show_newest_entries(): void
    {
        self::assertSame([
            'notificationtemplates_id' => 0,
            'signature_html' => '',
            'set_waiting' => true,
            'timeline_newest_first' => true,
            'hide_native_answer' => false,
        ], PluginTicketmailerConfig::forEntity(7));

        PluginTicketmailerConfig::applyTimelineOrderForCurrentTicket();
        self::assertSame(CommonITILObject::TIMELINE_ORDER_REVERSE, $_SESSION['glpitimeline_order']);
        self::assertSame(CommonITILObject::TIMELINE_ORDER_REVERSE, $GLOBALS['CFG_GLPI']['timeline_order']);
    }

    #[Test]
    public function global_setting_persists_checked_and_unchecked_without_resetting_other_fields(): void
    {
        $this->database->rows[0] = [
            'notificationtemplates_id' => 0,
            'signature_html' => '<p>Keep</p>',
            'set_waiting' => 1,
            'timeline_newest_first' => 1,
            'hide_native_answer' => 0,
            'subject_prefix' => '[keep]',
        ];

        PluginTicketmailerConfig::saveEntity(0, 0, true, true, true);
        self::assertTrue(PluginTicketmailerConfig::forEntity(7)['hide_native_answer']);
        self::assertSame('<p>Keep</p>', $this->database->rows[0]['signature_html']);
        self::assertSame('[keep]', $this->database->rows[0]['subject_prefix']);

        PluginTicketmailerConfig::saveEntity(0, 0, true, true, false);
        self::assertFalse(PluginTicketmailerConfig::forEntity(7)['hide_native_answer']);
    }

    #[Test]
    public function answer_hook_replaces_only_native_answer_when_enabled(): void
    {
        $ticket = new TimelineActionTicket();
        self::assertSame(
            ['ticketmailer_email_reply'],
            array_keys(PluginTicketmailerTimelineAction::getAnswerActions(['item' => $ticket])),
            'disabled setting leaves native answer unchanged',
        );

        $this->database->rows[0] = [
            'set_waiting' => 1,
            'timeline_newest_first' => 1,
            'hide_native_answer' => 1,
        ];
        $enabled = PluginTicketmailerTimelineAction::getAnswerActions(['item' => $ticket]);
        self::assertSame(['ticketmailer_email_reply', 'answer'], array_keys($enabled));
        self::assertSame(ITILFollowup::class, $enabled['answer']['type']);
        self::assertSame(ITILFollowup::class, $enabled['answer']['class']);
        self::assertSame(ITILFollowup::getIcon(), $enabled['answer']['icon']);
        self::assertSame('button:Answer', $enabled['answer']['label']);
        self::assertSame('button:Answer', $enabled['answer']['short_label']);
        self::assertSame('components/itilobject/timeline/form_followup.html.twig', $enabled['answer']['template']);
        self::assertTrue($enabled['answer']['hide_in_menu']);
        self::assertTrue($enabled['answer']['item']->initialized);
        self::assertSame(0, $enabled['answer']['item']->fields['is_private']);
        self::assertSame(Ticket::class, $enabled['answer']['item']->fields['itemtype']);
        self::assertSame(42, $enabled['answer']['item']->fields['items_id']);

        self::assertSame(
            [],
            PluginTicketmailerTimelineAction::getAnswerActions(['item' => new stdClass()]),
            'non_ticket items are unchanged',
        );
        TimelineActionTicket::$authorized = false;
        self::assertSame([], PluginTicketmailerTimelineAction::getAnswerActions(['item' => $ticket]));
    }

    #[Test]
    public function configured_order_controls_glpi_timeline_before_rendering(): void
    {
        $this->database->rows[0] = [
            'signature_html' => '',
            'set_waiting' => 1,
            'timeline_newest_first' => 0,
        ];

        PluginTicketmailerConfig::applyTimelineOrderForCurrentTicket();

        self::assertSame(CommonITILObject::TIMELINE_ORDER_NATURAL, $_SESSION['glpitimeline_order']);
        self::assertSame(CommonITILObject::TIMELINE_ORDER_NATURAL, $GLOBALS['CFG_GLPI']['timeline_order']);
    }

}
