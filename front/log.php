<?php
/**
 * front/log.php — audit log list view, optionally
 * filtered by ticket. Most of the time the user comes
 * here from the ticket's "Ticket Email Client" tab, in
 * which case `tickets_id` is in the query string.
 */
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
Html::header(
    __('Outbound email log', 'ticketmailer'),
    '',
    'ticket',
    'ticketmailerlog',
);

$tickets_id = (int) ($_GET['tickets_id'] ?? 0);
$entries = $tickets_id > 0
    ? PluginTicketmailerAudit::listForTicket($tickets_id)
    : [];

$web = Plugin::getWebDir('ticketmailer');
echo '<table class="tab_cadre_fixe">';
echo '<tr><th>' . htmlspecialchars(__('Sent at', 'ticketmailer'), ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th>' . htmlspecialchars(__('Ticket', 'ticketmailer'), ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th>' . htmlspecialchars(__('Subject', 'ticketmailer'), ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th>' . htmlspecialchars(__('Status', 'ticketmailer'), ENT_QUOTES, 'UTF-8') . '</th></tr>';
foreach ($entries as $e) {
    $id = (int) $e['id'];
    echo '<tr>';
    echo '<td>' . htmlspecialchars((string) $e['sent_at'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $e['tickets_id'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td><a href="'
        . htmlspecialchars($web . '/front/log_entry.php?id=' . $id, ENT_QUOTES, 'UTF-8')
        . '">'
        . htmlspecialchars((string) $e['subject'], ENT_QUOTES, 'UTF-8')
        . '</a></td>';
    echo '<td>' . htmlspecialchars((string) $e['status'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}
echo '</table>';

Html::footer();
