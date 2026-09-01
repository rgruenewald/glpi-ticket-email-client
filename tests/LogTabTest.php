<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!function_exists('_sn')) {
    function _sn(string $singular, string $plural, int $number = 1, string $domain = ''): string
    {
        return $number === 1 ? $singular : $plural;
    }
}

if (!function_exists('__')) {
    function __(string $message, string $domain = ''): string
    {
        return $message;
    }
}

if (!class_exists('CommonGLPI')) {
    class CommonGLPI
    {
        public static function createTabEntry(string $text, int $count = 0, ?string $itemtype = null, ?string $icon = null): array|string
        {
            return $text;
        }
    }
}

if (!class_exists('Ticket')) {
    class Ticket extends CommonGLPI
    {
        public function getFromDB(int $id): bool
        {
            return $id > 0;
        }

        public function canViewItem(): bool
        {
            return true;
        }

        public function getField(string $field): mixed
        {
            return $field === 'id' ? 42 : null;
        }
    }
}

final class DummyNonTicketItem extends CommonGLPI
{
    // Deliberately lacks getField() to simulate PluginPdfTicket
}

require_once __DIR__ . '/../inc/logtab.class.php';

final class LogTabTest extends TestCase
{
    #[Test]
    public function get_tab_name_returns_empty_string_for_non_ticket_item_without_error(): void
    {
        $tab = new PluginTicketmailerLogTab();
        $nonTicket = new DummyNonTicketItem();

        $result = $tab->getTabNameForItem($nonTicket);

        $this->assertSame('', $result);
    }

    #[Test]
    public function display_tab_content_returns_false_for_non_ticket_item_without_error(): void
    {
        $nonTicket = new DummyNonTicketItem();

        $result = PluginTicketmailerLogTab::displayTabContentForItem($nonTicket);

        $this->assertFalse($result);
    }

    #[Test]
    public function get_tab_name_returns_entry_for_valid_ticket(): void
    {
        $tab = new PluginTicketmailerLogTab();
        $ticket = new Ticket();

        $result = $tab->getTabNameForItem($ticket);

        $this->assertSame('Sent emails', $result);
    }
}
