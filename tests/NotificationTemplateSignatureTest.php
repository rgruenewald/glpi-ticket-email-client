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
if (!function_exists('getAncestorsOf')) {
    /** @return array<int, int> */
    function getAncestorsOf(string $table, int $id): array
    {
        return SignatureTestState::$ancestors;
    }
}
final class SignatureTestState
{
    /** @var array<int, int> */
    public static array $ancestors = [];
}
if (!class_exists('Entity')) {
    class Entity
    {
        public static function getTable(): string
        {
            return 'glpi_entities';
        }

        public function getFromDB(int $id): bool
        {
            return $id >= 0;
        }

        public function getField(string $field): string
        {
            return '';
        }
    }
}
if (!class_exists('Ticket')) {
    class Ticket
    {
        public static bool $loadable = true;
        public static int $entityId = 0;

        /** @param array<string, mixed> $fields */
        public function __construct(private array $fields = [])
        {
        }

        public function getFromDB(int $id): bool
        {
            return self::$loadable && $id > 0;
        }

        public function getField(string $field): mixed
        {
            if ($field === 'entities_id' && !array_key_exists($field, $this->fields)) {
                return self::$entityId;
            }
            return $this->fields[$field] ?? null;
        }
    }
}
if (!class_exists('User')) {
    class User
    {
        public function getFromDB(int $id): bool
        {
            return true;
        }

        public function getField(string $field): string
        {
            return '';
        }
    }
}
if (!class_exists('Session')) {
    class Session
    {
        public static bool $canReadCosts = false;

        public static function haveRight(string $right, int $access): bool
        {
            return $right === 'ticketcost'
                && $access === (defined('READ') ? READ : 1)
                && self::$canReadCosts;
        }

        public static function getLoginUserID(): int
        {
            return 1;
        }
    }
}
if (!class_exists('UserEmail')) {
    class UserEmail
    {
        public static function getDefaultForUser(int $id): string
        {
            return '';
        }
    }
}
if (!class_exists('PluginTicketmailerTimeline')) {
    class PluginTicketmailerTimeline
    {
        public static function sanitizeHtml(string $html): string
        {
            return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        }
    }
}
if (!class_exists('NotificationTarget')) {
    class NotificationTarget
    {
        public const GLPI_USER = 1;

        public static object|false $instance = false;

        public static function getInstance(object $item, string $event = '', array $options = []): object|false
        {
            return self::$instance;
        }
    }
}
if (!class_exists('Notification_NotificationTemplate')) {
    class Notification_NotificationTemplate
    {
        public const MODE_MAIL = 'mail';
    }
}
if (!class_exists('NotificationTargetTicket')) {
    class NotificationTargetTicket
    {
        /** @var array<string, mixed> */
        public array $data = [];
        public bool $responseAllowed = true;
        public ?string $mode = null;
        public function setMode(string $mode): void
        {
            $this->mode = $mode;
        }

        public function setAllowResponse(bool $allowed): void
        {
            $this->responseAllowed = $allowed;
        }
    }
}
if (!class_exists('NotificationTemplate')) {
    class NotificationTemplate
    {
        /** @var array<int, array<string, mixed>> */
        public static array $templates = [];
        /** @var array<string, array<string, string>> */
        public array $templates_by_languages = [];
        public string $signature = 'not-cleared';
        /** @var array<string, mixed> */
        public array $fields = [];

        public function getFromDB(int $id): bool
        {
            if (!isset(self::$templates[$id])) {
                return false;
            }
            $this->fields = self::$templates[$id];
            return true;
        }

        public function getField(string $field): mixed
        {
            return $this->fields[$field] ?? null;
        }

        public function setSignature(string $signature): void
        {
            $this->signature = $signature;
        }

        public function resetComputedTemplates(): void
        {
            $this->templates_by_languages = [];
        }

        /** @param array<string, mixed> $userInfos */
        public function getTemplateByLanguage(object $target, array $userInfos, string $event, array $options): string|false
        {
            SignatureRenderProbe::$renderCount++;
            SignatureRenderProbe::$language = (string) ($userInfos['language'] ?? '');
            SignatureRenderProbe::$event = $event;
            SignatureRenderProbe::$options = $options;
            SignatureRenderProbe::$target = $target;
            SignatureRenderProbe::$signature = $this->signature;
            if (!isset($this->fields['rendered_html'])) {
                return false;
            }
            $this->templates_by_languages['rendered'] = [
                'subject' => (string) ($this->fields['rendered_subject'] ?? ''),
                'content_html' => (string) $this->fields['rendered_html'],
                'content_text' => 'Ignored text',
            ];
            return 'rendered';
        }
    }
}
final class SignatureRenderProbe
{
    public static int $renderCount = 0;
    public static string $language = '';
    public static string $event = '';
    /** @var array<string, mixed> */
    public static array $options = [];
    public static ?object $target = null;
    public static string $signature = '';
}
final class LegacySignatureProbe
{
    public static string $template = '';
}
final class NotificationTemplateSignatureDatabase
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    /** @var array<string, mixed>|null */
    private ?array $currentRow = null;

    public function tableExists(string $table): bool
    {
        return $table === 'glpi_plugin_ticketmailer_configs';
    }

    /** @param array<string, mixed> $query */
    public function request(array $query): self
    {
        $this->currentRow = $this->rows[(int) $query['WHERE']['entities_id']] ?? null;
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
        $this->rows[$where['entities_id']] = array_merge($this->rows[$where['entities_id']] ?? [], $values);
    }
}

require_once __DIR__ . '/../inc/config.class.php';

final class NotificationTemplateSignatureTest extends TestCase
{
    private NotificationTemplateSignatureDatabase $database;

    protected function setUp(): void
    {
        $this->database = new NotificationTemplateSignatureDatabase();
        $GLOBALS['DB'] = $this->database;
        SignatureTestState::$ancestors = [];
        NotificationTemplate::$templates = [];
        NotificationTarget::$instance = new NotificationTargetTicket();
        $_SESSION['glpilanguage'] = 'de_DE';
        SignatureRenderProbe::$renderCount = 0;
        SignatureRenderProbe::$language = '';
        SignatureRenderProbe::$event = '';
        SignatureRenderProbe::$options = [];
        SignatureRenderProbe::$target = null;
        SignatureRenderProbe::$signature = '';
        LegacySignatureProbe::$template = '';
    }

    #[Test]
    public function nearest_valid_ticket_template_is_inherited_from_entity_ancestors(): void
    {
        SignatureTestState::$ancestors = [4 => 4, 0 => 0];
        $this->database->rows = [
            0 => ['notificationtemplates_id' => 10],
            4 => ['notificationtemplates_id' => 20],
            7 => ['notificationtemplates_id' => 0],
        ];
        NotificationTemplate::$templates = [
            10 => ['id' => 10, 'itemtype' => Ticket::class],
            20 => ['id' => 20, 'itemtype' => Ticket::class],
        ];

        self::assertSame(20, PluginTicketmailerConfig::notificationTemplateForEntity(7));
    }

    #[Test]
    public function assignment_reports_direct_effective_and_source_entity(): void
    {
        SignatureTestState::$ancestors = [4 => 4, 0 => 0];
        $this->database->rows = [
            0 => ['notificationtemplates_id' => 10],
            4 => ['notificationtemplates_id' => 20],
            7 => ['notificationtemplates_id' => 0],
        ];
        NotificationTemplate::$templates = [
            10 => ['id' => 10, 'itemtype' => Ticket::class],
            20 => ['id' => 20, 'itemtype' => Ticket::class],
        ];

        self::assertSame(
            ['direct' => 0, 'effective' => 20, 'source_entities_id' => 4],
            PluginTicketmailerConfig::notificationTemplateAssignmentForEntity(7),
        );
    }
    #[Test]
    public function invalid_local_template_is_skipped_for_valid_ancestor(): void
    {
        SignatureTestState::$ancestors = [4 => 4, 0 => 0];
        $this->database->rows = [
            0 => ['notificationtemplates_id' => 10],
            4 => ['notificationtemplates_id' => 20],
            7 => ['notificationtemplates_id' => 30],
        ];
        NotificationTemplate::$templates = [
            10 => ['id' => 10, 'itemtype' => Ticket::class],
            20 => ['id' => 20, 'itemtype' => Ticket::class],
            30 => ['id' => 30, 'itemtype' => 'Problem'],
        ];

        self::assertSame(
            ['direct' => 30, 'effective' => 20, 'source_entities_id' => 4],
            PluginTicketmailerConfig::notificationTemplateAssignmentForEntity(7),
        );
    }

    #[Test]
    public function native_renderer_returns_current_language_subject_and_sanitized_body_fragment(): void
    {
        $this->database->rows[7] = ['notificationtemplates_id' => 20];
        NotificationTemplate::$templates[20] = [
            'id' => 20,
            'itemtype' => Ticket::class,
            'rendered_subject' => ' Ticket 42 — Root > Child ',
            'rendered_html' => "<!DOCTYPE html><html><head><title>Ticket 42</title></head><body>\n<p>Hallo Ticket 42</p><script>alert(1)</script><br>Automatically generated by GLPI<br><br>\n</body></html>",
        ];
        $ticket = new Ticket(['id' => 42, 'entities_id' => 7]);
        $GLOBALS['CFG_GLPI']['app_name'] = 'GLPI';

        $content = PluginTicketmailerConfig::contentForTicket($ticket);

        self::assertSame('Ticket 42 — Root > Child', $content['subject']);
        self::assertSame('<p>Hallo Ticket 42</p>', $content['signature']);
        self::assertTrue($content['native_template_selected']);
        self::assertSame(1, SignatureRenderProbe::$renderCount);
        self::assertSame('de_DE', SignatureRenderProbe::$language);
        self::assertSame('update', SignatureRenderProbe::$event);
        self::assertSame($ticket, SignatureRenderProbe::$options['item']);
        self::assertInstanceOf(NotificationTargetTicket::class, SignatureRenderProbe::$target);
        self::assertSame(Notification_NotificationTemplate::MODE_MAIL, SignatureRenderProbe::$target->mode);
        self::assertFalse(SignatureRenderProbe::$target->responseAllowed);
        self::assertSame('', SignatureRenderProbe::$signature);
    }

    #[Test]
    public function unavailable_native_subject_uses_deterministic_fallback_not_historical_prefix(): void
    {
        $this->database->rows[7] = [
            'subject_prefix' => '[legacy custom]',
            'notificationtemplates_id' => 20,
        ];
        NotificationTemplate::$templates[20] = [
            'id' => 20,
            'itemtype' => Ticket::class,
            'rendered_subject' => '   ',
            'rendered_html' => '<html><body><p>Signature</p></body></html>',
        ];

        self::assertSame(
            'Printer offline',
            PluginTicketmailerConfig::subjectForTicket(new Ticket([
                'id' => 42,
                'name' => 'Printer offline',
                'entities_id' => 7,
            ])),
        );
    }

    #[Test]
    public function no_selected_template_uses_deterministic_subject_fallback(): void
    {
        $this->database->rows[7] = [
            'subject_prefix' => '[legacy custom]',
            'notificationtemplates_id' => 0,
        ];

        self::assertSame(
            'Printer offline',
            PluginTicketmailerConfig::subjectForTicket(new Ticket([
                'id' => 42,
                'name' => 'Printer offline',
                'entities_id' => 7,
            ])),
        );
    }
    #[Test]
    public function configured_template_without_rendered_html_does_not_mask_failure_with_legacy_content(): void
    {
        $this->database->rows[7] = [
            'notificationtemplates_id' => 20,
            'signature_html' => '<p>Legacy fallback</p>',
        ];
        NotificationTemplate::$templates[20] = [
            'id' => 20,
            'itemtype' => Ticket::class,
        ];

        self::assertSame('', PluginTicketmailerConfig::signatureForTicket(new Ticket(['entities_id' => 7])));
    }

    #[Test]
    public function no_selected_template_preserves_inherited_legacy_plugin_signature(): void
    {
        SignatureTestState::$ancestors = [4 => 4, 0 => 0];
        $this->database->rows = [
            0 => ['notificationtemplates_id' => 0, 'signature_html' => '<p>Root legacy</p>'],
            4 => ['notificationtemplates_id' => 0, 'signature_html' => '<p>Parent ##ticket.id##</p>'],
            7 => ['notificationtemplates_id' => 0, 'signature_html' => ''],
        ];

        self::assertSame(
            '<p>Parent 42</p>',
            PluginTicketmailerConfig::signatureForTicket(new Ticket(['id' => 42, 'entities_id' => 7])),
        );
    }

    #[Test]
    public function saving_matrix_assignment_only_changes_the_selected_entity_template(): void
    {
        $this->database->rows[7] = [
            'notificationtemplates_id' => 0,
            'signature_html' => '<p>Legacy</p>',
            'set_waiting' => 0,
        ];
        NotificationTemplate::$templates[20] = ['id' => 20, 'itemtype' => Ticket::class];

        PluginTicketmailerConfig::saveNotificationTemplateAssignment(7, 20);

        self::assertSame(20, $this->database->rows[7]['notificationtemplates_id']);
        self::assertSame('<p>Legacy</p>', $this->database->rows[7]['signature_html']);
        self::assertSame(0, $this->database->rows[7]['set_waiting']);
    }
    #[Test]
    public function saving_child_template_preserves_legacy_signature_and_global_preferences(): void
    {
        $this->database->rows = [
            0 => [
                'subject_prefix' => '[old]',
                'notificationtemplates_id' => 10,
                'signature_html' => '<p>Root legacy</p>',
                'set_waiting' => 0,
                'timeline_newest_first' => 0,
            ],
            7 => [
                'subject_prefix' => '[child old]',
                'notificationtemplates_id' => 0,
                'signature_html' => '<p>Child legacy</p>',
            ],
        ];
        NotificationTemplate::$templates[10] = ['id' => 10, 'itemtype' => Ticket::class];
        NotificationTemplate::$templates[20] = ['id' => 20, 'itemtype' => Ticket::class];

        PluginTicketmailerConfig::saveEntity(7, 20, true, true);

        self::assertSame(20, $this->database->rows[7]['notificationtemplates_id']);
        self::assertSame(10, $this->database->rows[0]['notificationtemplates_id']);
        self::assertSame('<p>Root legacy</p>', $this->database->rows[0]['signature_html']);
        self::assertSame('[old]', $this->database->rows[0]['subject_prefix']);
        self::assertSame('[child old]', $this->database->rows[7]['subject_prefix']);
        self::assertSame(1, $this->database->rows[0]['set_waiting']);
    }
}