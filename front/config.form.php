<?php
/**
 * Per-entity compose preferences. SMTP remains GLPI core configuration.
 */
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$entities_id = (int) ($_GET['entities_id'] ?? $_POST['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0));
if (!Session::haveAccessToEntity($entities_id)) {
    Html::displayRightError();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    PluginTicketmailerConfig::saveEntity(
        $entities_id,
        (string) ($_POST['subject_prefix'] ?? ''),
        (string) ($_POST['signature_html'] ?? ''),
        !empty($_POST['set_waiting']),
        !empty($_POST['timeline_newest_first']),
        !empty($_POST['open_reply_on_ticket']),
        !empty($_POST['recipient_autocomplete_show_email']),
    );
    Html::redirect($_SERVER['PHP_SELF'] . '?entities_id=' . $entities_id);
}

$settings = PluginTicketmailerConfig::forEntity($entities_id);
$entity_dropdown = (string) Dropdown::show('Entity', [
    'name'    => 'entities_id',
    'value'   => $entities_id,
    'display' => false,
]);
$signature_editor = Html::textarea([
    'name'            => 'signature_html',
    'value'           => $settings['signature_html'],
    'enable_richtext' => true,
    'rows'            => 10,
    'display'         => false,
]);

Html::header(__('Outbound email', 'ticketmailer'), $_SERVER['PHP_SELF'], 'config', 'plugins');
echo '<form method="get" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" class="mb-3">';
echo '<label class="me-2" for="dropdown_entities_id">' . __('Entity') . '</label>';
echo $entity_dropdown;
echo '<button type="submit" class="btn btn-secondary ms-2">' . __('Show') . '</button>';
echo '</form>';

echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('entities_id', ['value' => $entities_id]);
echo '<div class="card"><div class="card-body"><div class="row g-3">';
echo '<div class="col-12"><label class="form-label" for="ticketmailer-subject-prefix">'
    . __('Ticket subject prefix', 'ticketmailer') . '</label>';
echo '<input class="form-control" id="ticketmailer-subject-prefix" name="subject_prefix" maxlength="255" value="'
    . htmlspecialchars($settings['subject_prefix'], ENT_QUOTES, 'UTF-8') . '">';
echo '<div class="form-text">' . __('Use %d for the ticket ID.', 'ticketmailer') . '</div></div>';
echo '<div class="col-12"><label class="form-label">' . __('E-mail signature', 'ticketmailer') . '</label>';
echo $signature_editor;
echo '<div class="form-text">' . __('The plain-text signature is generated automatically from this HTML.', 'ticketmailer') . '</div></div>';
echo '<div class="col-12 form-check"><input class="form-check-input" id="ticketmailer-set-waiting" type="checkbox" name="set_waiting" value="1"'
    . ($settings['set_waiting'] ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="ticketmailer-set-waiting">'
    . __('Set ticket status to waiting after a successful e-mail send.', 'ticketmailer') . '</label></div>';
echo '<div class="col-12 form-check"><input class="form-check-input" id="ticketmailer-timeline-newest-first" type="checkbox" name="timeline_newest_first" value="1"'
    . ($settings['timeline_newest_first'] ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="ticketmailer-timeline-newest-first">'
    . __('Show newest timeline entries first.', 'ticketmailer') . '</label></div>';
echo '<div class="col-12 form-check"><input class="form-check-input" id="ticketmailer-open-reply-on-ticket" type="checkbox" name="open_reply_on_ticket" value="1"'
    . ($settings['open_reply_on_ticket'] ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="ticketmailer-open-reply-on-ticket">'
    . __('Open the E-Mail reply form when a ticket is opened.', 'ticketmailer') . '</label></div>';
echo '<div class="col-12 form-check"><input class="form-check-input" id="ticketmailer-recipient-autocomplete-show-email" type="checkbox" name="recipient_autocomplete_show_email" value="1"'
    . ($settings['recipient_autocomplete_show_email'] ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="ticketmailer-recipient-autocomplete-show-email">'
    . __('Show email addresses in recipient autocomplete.', 'ticketmailer') . '</label></div>';
echo '<div class="col-12"><button type="submit" class="btn btn-primary">' . __('Save') . '</button></div>';
echo '</div></div></div></form>';
Html::footer();
