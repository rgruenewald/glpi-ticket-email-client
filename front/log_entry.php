<?php
/**
 * front/log_entry.php — full audit detail under ticket-read ACL.
 */

use Glpi\Application\View\TemplateRenderer;

require_once __DIR__ . '/../inc/bootstrap.php';

Session::checkLoginUser();

$id = (int) ($_GET['id'] ?? 0);
$entry = $id > 0 ? PluginTicketmailerAudit::find($id) : null;
if ($entry === null) {
    Html::displayNotFoundError();
}

$ticket = new Ticket();
if (!$ticket->getFromDB((int) $entry['tickets_id']) || !$ticket->canViewItem()) {
    Html::displayRightError();
}

$recipients_to  = PluginTicketmailerAudit::decodeJson((string) $entry['recipients_to']);
$recipients_cc  = PluginTicketmailerAudit::decodeJson((string) $entry['recipients_cc']);
$recipients_bcc = PluginTicketmailerAudit::decodeJson((string) $entry['recipients_bcc']);
$attachments    = PluginTicketmailerAudit::decodeJson((string) $entry['attachments']);
$inline_images  = PluginTicketmailerAudit::decodeJson((string) $entry['inline_images']);
$mailbox_matches = PluginTicketmailerAudit::decodeJson((string) ($entry['mailbox_matches'] ?? ''));

Html::header(
    __('Sent email', 'ticketmailer'),
    '',
    'ticket',
    'ticketmailerlog',
);

$web = Plugin::getWebDir('ticketmailer');
$twig = TemplateRenderer::getInstance();
echo $twig->render('@ticketmailer/log_entry.html.twig', [
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
