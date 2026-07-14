<?php
/**
 * Direct compose URL fallback. The ticket timeline uses
 * PluginTicketemailclientTimelineAction for the normal inline UI.
 */

include_once __DIR__ . '/../../../inc/includes.php';

$plugin = new Plugin();
if (!$plugin->isInstalled('ticketemailclient') || !$plugin->isActivated('ticketemailclient')) {
    Html::displayNotFoundError();
}
Session::checkLoginUser();

$tickets_id = (int) ($_GET['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id)) {
    Html::displayNotFoundError();
}
if (!PluginTicketemailclientTimelineAction::canUse($ticket)) {
    Html::displayRightError();
}

Html::header(
    __('E-Mail antworten', 'ticketemailclient'),
    $_SERVER['PHP_SELF'],
    'helpdesk',
    'ticket',
);
echo PluginTicketemailclientTimelineAction::renderReply($ticket, false);


Html::footer();
