<?php
/**
 * inc/log_tab.class.php — the "Ticket Email Client" tab that
 * lists audit log entries for the current ticket,
 * newest first (spec § A12). Links to the full
 * read-only detail view (front/log_entry.php).
 */
class PluginTicketemailclientLogTab extends CommonGLPI
{
    public static $rightname = 'ticket';

    public static function getTypeName($nb = 0): string
    {
        return _sn('Sent email', 'Sent emails', $nb, 'ticketemailclient');
    }

    public static function canView(): bool
    {
        $ticket = self::getCurrentTicket();
        return $ticket instanceof Ticket && $ticket->canViewItem();
    }

    public static function isVisible(string $itemtype, int $items_id): bool
    {
        if ($itemtype !== 'Ticket') {
            return false;
        }
        $ticket = new Ticket();
        return $ticket->getFromDB($items_id) && $ticket->canViewItem();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!self::isVisible($item::class, (int) $item->getField('id'))) {
            return '';
        }
        return self::getTypeName(2);
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0,
    ): bool {
        if (!self::isVisible($item::class, (int) $item->getField('id'))) {
            return false;
        }
        $tickets_id = (int) $item->getField('id');
        $entries = PluginTicketemailclientAudit::listForTicket($tickets_id);
        $web = Plugin::getWebDir('ticketemailclient');
        echo '<table class="tab_cadre_fixe">';
        echo '<tr><th>' . htmlspecialchars(__('Sent at', 'ticketemailclient'), ENT_QUOTES, 'UTF-8') . '</th>';
        echo '<th>' . htmlspecialchars(__('Subject', 'ticketemailclient'), ENT_QUOTES, 'UTF-8') . '</th>';
        echo '<th>' . htmlspecialchars(__('Recipients', 'ticketemailclient'), ENT_QUOTES, 'UTF-8') . '</th>';
        echo '<th>' . htmlspecialchars(__('Status', 'ticketemailclient'), ENT_QUOTES, 'UTF-8') . '</th></tr>';
        foreach ($entries as $e) {
            $id = (int) $e['id'];
            $to_count = count(PluginTicketemailclientAudit::decodeJson((string) $e['recipients_to']));
            $cc_count = count(PluginTicketemailclientAudit::decodeJson((string) $e['recipients_cc']));
            $bcc_count = count(PluginTicketemailclientAudit::decodeJson((string) $e['recipients_bcc']));
            $recipients_label = sprintf(
                '%1$d / %2$d / %3$d',
                $to_count,
                $cc_count,
                $bcc_count,
            );
            $status_class = $e['status'] === 'sent' ? 'status-sent' : 'status-failed';
            $status_label = $e['status'] === 'sent'
                ? __('Sent', 'ticketemailclient')
                : __('Failed', 'ticketemailclient');
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string) $e['sent_at'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td><a href="'
                . htmlspecialchars($web . '/front/log_entry.php?id=' . $id, ENT_QUOTES, 'UTF-8')
                . '">'
                . htmlspecialchars((string) $e['subject'], ENT_QUOTES, 'UTF-8')
                . '</a></td>';
            echo '<td>' . htmlspecialchars($recipients_label, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="' . htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        return true;
    }

    private static function getCurrentTicket(): ?Ticket
    {
        $items_id = (int) ($_GET['id'] ?? 0);
        if ($items_id <= 0) {
            return null;
        }
        $ticket = new Ticket();
        return $ticket->getFromDB($items_id) ? $ticket : null;
    }
}
