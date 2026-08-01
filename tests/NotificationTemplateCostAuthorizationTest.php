<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!defined('READ')) {
    define('READ', 1);
}

if (!class_exists('NotificationTargetTicket')) {
    class NotificationTargetTicket
    {
        /** @var array<string, mixed> */
        public array $data = [];
        public ?string $mode = null;
        public bool $responseAllowed = true;

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

if (!class_exists('Session')) {
    class Session
    {
        public static bool $canReadCosts = false;

        public static function haveRight(string $right, int $access): bool
        {
            return $right === 'ticketcost' && $access === READ && self::$canReadCosts;
        }

        public static function getLoginUserID(): int
        {
            return 1;
        }
    }
}

require_once __DIR__ . '/../hook.php';

final class NotificationTemplateCostAuthorizationTest extends TestCase
{
    #[Test]
    public function cost_data_is_removed_without_ticket_cost_permission(): void
    {
        Session::$canReadCosts = false;
        $target = new NotificationTargetTicket();
        $target->data = [
            '##ticket.title##' => 'Printer offline',
            '##ticket.costfixed##' => '100.00',
            '##ticket.costmaterial##' => '20.00',
            '##ticket.costtime##' => '30.00',
            '##ticket.totalcost##' => '150.00',
            '##ticket.numberofcosts##' => 1,
            'costs' => [['##cost.totalcost##' => '150.00']],
        ];

        plugin_ticketmailer_filter_notification_template_data($target);

        self::assertSame(['##ticket.title##' => 'Printer offline'], $target->data);
    }

    #[Test]
    public function cost_data_is_preserved_with_ticket_cost_permission(): void
    {
        Session::$canReadCosts = true;
        $target = new NotificationTargetTicket();
        $target->data = [
            '##ticket.totalcost##' => '150.00',
            'costs' => [['##cost.totalcost##' => '150.00']],
        ];

        plugin_ticketmailer_filter_notification_template_data($target);

        self::assertArrayHasKey('##ticket.totalcost##', $target->data);
        self::assertArrayHasKey('costs', $target->data);
    }
}
