<?php
/**
 * Direct compose URL fallback. The ticket timeline uses
 * PluginTicketmailerTimelineAction for the normal inline UI.
 */

include_once __DIR__ . '/../../../inc/includes.php';

$plugin = new Plugin();
if (!$plugin->isInstalled('ticketmailer') || !$plugin->isActivated('ticketmailer')) {
    Html::displayNotFoundError();
}
Session::checkLoginUser();

$tickets_id = (int) ($_GET['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id)) {
    Html::displayNotFoundError();
}
if (!PluginTicketmailerTimelineAction::canUse($ticket)) {
    Html::displayRightError();
}

Html::header(
    __('E-Mail antworten', 'ticketmailer'),
    $_SERVER['PHP_SELF'],
    'helpdesk',
    'ticket',
);
echo PluginTicketmailerTimelineAction::renderReply($ticket, false);


Html::footer();
