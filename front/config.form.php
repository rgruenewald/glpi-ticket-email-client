<?php
/**
 * Global preferences and per-entity notification-template subject/signature.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$entities_id = (int) ($_GET['entities_id'] ?? $_POST['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0));
if (!Session::haveAccessToEntity($entities_id)) {
    Html::displayRightError();
}
global $CFG_GLPI;
$config_url = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/ticketmailer/front/config.form.php';
$settings = PluginTicketmailerConfig::forEntity($entities_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_template_assignment'])) {
        $assignment_entities_id = (int) ($_POST['assignment_entities_id'] ?? -1);
        if (!Session::haveAccessToEntity($assignment_entities_id)) {
            Html::displayRightError();
        }
        PluginTicketmailerConfig::saveNotificationTemplateAssignment(
            $assignment_entities_id,
            (int) ($_POST['notificationtemplates_id'] ?? 0),
        );
    } else {
        PluginTicketmailerConfig::saveEntity(
            $entities_id,
            (int) $settings['notificationtemplates_id'],
            !empty($_POST['set_waiting']),
            !empty($_POST['timeline_newest_first']),
        );
    }
    Html::redirect($config_url . '?entities_id=' . $entities_id);
}

Html::header(__('Outbound email', 'ticketmailer'), $config_url, 'config', 'plugins');

echo '<form method="post" action="' . htmlspecialchars($config_url, ENT_QUOTES, 'UTF-8') . '">';
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('entities_id', ['value' => $entities_id]);
echo '<div class="card mb-3"><div class="card-header"><h3 class="card-title">'
    . __('Global settings', 'ticketmailer') . '</h3></div><div class="card-body"><div class="row g-3">';
echo '<div class="col-12 form-check"><input class="form-check-input" id="ticketmailer-set-waiting" type="checkbox" name="set_waiting" value="1"'
    . ($settings['set_waiting'] ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="ticketmailer-set-waiting">'
    . __('Preselect "Set ticket status to waiting" in the e-mail form.', 'ticketmailer') . '</label></div>';
echo '<div class="col-12 form-check"><input class="form-check-input" id="ticketmailer-timeline-newest-first" type="checkbox" name="timeline_newest_first" value="1"'
    . ($settings['timeline_newest_first'] ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="ticketmailer-timeline-newest-first">'
    . __('Show newest timeline entries first.', 'ticketmailer') . '</label></div>';
echo '<div class="col-12"><button type="submit" class="btn btn-primary">' . __('Save') . '</button></div>';
echo '</div></div></div></form>';

global $DB;
$active_entities = array_values(array_unique(array_map('intval', Session::getActiveEntities())));
$active_entity = (int) ($_SESSION['glpiactive_entity'] ?? 0);
if (!in_array($active_entity, $active_entities, true) && Session::haveAccessToEntity($active_entity)) {
    $active_entities[] = $active_entity;
}
$entities = [];
if ($active_entities !== []) {
    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'completename'],
        'FROM' => Entity::getTable(),
        'WHERE' => ['id' => $active_entities],
        'ORDERBY' => 'completename',
    ]) as $entity) {
        $entities[(int) $entity['id']] = $entity;
    }
}

echo '<div class="card mt-3"><div class="card-header"><h3 class="card-title">'
    . __('Notification template assignments by entity', 'ticketmailer') . '</h3></div><div class="card-body">'
    . '<p class="text-secondary">'
    . __('The selected template provides the initial subject and signature.', 'ticketmailer')
    . '</p></div><div class="table-responsive">';
echo '<table class="table table-vcenter card-table"><thead><tr><th>' . __('Entity') . '</th><th>'
    . __('Assigned template', 'ticketmailer') . '</th><th>' . __('Effective template', 'ticketmailer')
    . '</th></tr></thead><tbody>';
foreach ($entities as $matrix_entities_id => $entity) {
    $assignment = PluginTicketmailerConfig::notificationTemplateAssignmentForEntity($matrix_entities_id);
    $source = $entities[$assignment['source_entities_id']]['completename']
        ?? $entities[$assignment['source_entities_id']]['name']
        ?? (string) $assignment['source_entities_id'];
    $effective = '-----';
    if ($assignment['effective'] > 0) {
        $template = new NotificationTemplate();
        $effective = $template->getFromDB($assignment['effective'])
            ? htmlspecialchars((string) $template->getName(), ENT_QUOTES, 'UTF-8')
            : htmlspecialchars(sprintf(__('Template #%d', 'ticketmailer'), $assignment['effective']), ENT_QUOTES, 'UTF-8');
        if ($assignment['source_entities_id'] !== $matrix_entities_id) {
            $effective .= ' <span class="text-secondary">('
                . sprintf(__('inherited from %s', 'ticketmailer'), htmlspecialchars((string) $source, ENT_QUOTES, 'UTF-8'))
                . ')</span>';
        }
    }
    $form_id = 'ticketmailer-assignment-' . $matrix_entities_id;
    echo '<tr><td>' . htmlspecialchars((string) ($entity['completename'] ?: $entity['name']), ENT_QUOTES, 'UTF-8') . '</td><td>';
    echo '<form id="' . $form_id . '" method="post" action="' . htmlspecialchars($config_url, ENT_QUOTES, 'UTF-8') . '">';
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo Html::hidden('entities_id', ['value' => $entities_id]);
    echo Html::hidden('assignment_entities_id', ['value' => $matrix_entities_id]);
    echo Html::hidden('save_template_assignment', ['value' => 1]);
    NotificationTemplate::dropdown([
        'name'       => 'notificationtemplates_id',
        'value'      => $assignment['direct'],
        'comment'    => 1,
        'condition'  => ['itemtype' => Ticket::class],
        'on_change'  => 'this.form.submit()',
    ]);
    echo '</form></td><td>' . $effective . '</td></tr>';
}
if ($entities === []) {
    echo '<tr><td colspan="3" class="text-secondary">' . __('No accessible entities.', 'ticketmailer') . '</td></tr>';
}
echo '</tbody></table></div></div>';
Html::footer();
