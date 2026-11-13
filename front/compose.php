<?php
/**
 * Direct compose URL fallback. The ticket timeline uses
 * PluginTicketmailerTimelineAction for the normal inline UI.
 */

require_once __DIR__ . '/../inc/bootstrap.php';

$plugin = new Plugin();
if (!$plugin->isInstalled('ticketmailer') || !$plugin->isActivated('ticketmailer')) {
    PluginTicketmailerConfig::displayNotFoundError();
}
Session::checkLoginUser();

$tickets_id = (int) ($_POST['tickets_id'] ?? $_GET['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id)) {
    PluginTicketmailerConfig::displayNotFoundError();
}
if (!PluginTicketmailerTimelineAction::canUse($ticket)) {
    PluginTicketmailerConfig::displayRightError();
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    Html::header(
        __('E-Mail antworten', 'ticketmailer'),
        $_SERVER['PHP_SELF'],
        'helpdesk',
        'ticket',
    );
}
echo PluginTicketmailerTimelineAction::renderReply($ticket, false);
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    Html::footer();
}
