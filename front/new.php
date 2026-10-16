<?php
use Glpi\Application\View\TemplateRenderer;
require_once __DIR__ . '/../inc/bootstrap.php';
Session::checkLoginUser();
$entity = (int) ($_SESSION['glpiactive_entity'] ?? 0);
if (!PluginTicketmailerProactive::canUse($entity)) {
    Html::displayRightError();
}
PluginTicketmailerProactive::cleanup();
$context = PluginTicketmailerProactive::createContext($entity, (int) Session::getLoginUserID());
$web = Plugin::getWebDir('ticketmailer');
$editor_id = 'ticketmailer-new-body';
ob_start();
Html::textarea(['name'=>'body_html', 'value'=>'', 'enable_richtext'=>true, 'editor_id'=>$editor_id, 'rows'=>14, 'display'=>true, 'enable_images'=>true, 'enable_fileupload'=>false]);
$editor = ob_get_clean();
Html::header(__('New email', 'ticketmailer'), $_SERVER['PHP_SELF'], 'helpdesk', 'PluginTicketmailerProactive');
echo '<h1>' . htmlescape(__('New email', 'ticketmailer')) . '</h1>';
echo TemplateRenderer::getInstance()->render('@ticketmailer/compose.html.twig', [
    'proactive'=>true, 'compose_token'=>$context['token'], 'entity_name'=>Dropdown::getDropdownName('glpi_entities', $entity),
    'subject'=>'', 'new_conversation'=>true, 'body_editor'=>$editor, 'editor_id'=>$editor_id,
    'csrf_token'=>Session::getNewCSRFToken(), 'ajax_csrf'=>Session::getNewCSRFToken(true),
    'send_url'=>$web . '/front/send.php', 'upload_url'=>$web . '/ajax/upload.php',
    'image_url'=>$web . '/ajax/upload_image.php', 'validate_url'=>$web . '/ajax/validate_recipients.php',
    'user_autocomplete_url'=>$web . '/ajax/autocomplete_users.php',
    'user_autocomplete_show_email'=>false,
    'upload_max_size_label'=>PluginTicketmailerConfig::uploadMaxSizeLabel(),
]);
Html::footer();
