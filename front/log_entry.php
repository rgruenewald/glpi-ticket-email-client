<?php
/**
 * front/log_entry.php — full audit detail under ticket-read ACL.
 */

use Glpi\Application\View\TemplateRenderer;

include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

$id = (int) ($_GET['id'] ?? 0);
$entry = $id > 0 ? PluginTicketemailclientAudit::find($id) : null;
if ($entry === null) {
    Html::displayNotFoundError();
}

$ticket = new Ticket();
if (!$ticket->getFromDB((int) $entry['tickets_id']) || !$ticket->canViewItem()) {
    Html::displayRightError();
}

$recipients_to  = PluginTicketemailclientAudit::decodeJson((string) $entry['recipients_to']);
$recipients_cc  = PluginTicketemailclientAudit::decodeJson((string) $entry['recipients_cc']);
$recipients_bcc = PluginTicketemailclientAudit::decodeJson((string) $entry['recipients_bcc']);
$attachments    = PluginTicketemailclientAudit::decodeJson((string) $entry['attachments']);
$inline_images  = PluginTicketemailclientAudit::decodeJson((string) $entry['inline_images']);
$mailbox_matches = PluginTicketemailclientAudit::decodeJson((string) ($entry['mailbox_matches'] ?? ''));

Html::header(
    __('Sent email', 'ticketemailclient'),
    '',
    'ticket',
    'ticketemailclientlog',
);

$web = Plugin::getWebDir('ticketemailclient');
$twig = TemplateRenderer::getInstance();
echo $twig->render('@ticketemailclient/log_entry.html.twig', [
    'entry'            => $entry,
    'recipients_to'    => $recipients_to,
    'recipients_cc'    => $recipients_cc,
    'recipients_bcc'   => $recipients_bcc,
    'attachments'      => $attachments,
    'inline_images'    => $inline_images,
    'mailbox_matches'  => $mailbox_matches,
    'path_for_download'=> $web . '/front/download.php',
]);

Html::footer();
