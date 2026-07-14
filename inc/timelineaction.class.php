<?php
/**
 * Ticket-timeline action for composing email inline.
 *
 * GLPI 10 renders TIMELINE_ANSWER_ACTIONS in the same collapsible answer
 * region as its native form. TIMELINE_ACTIONS supplies an always-visible
 * button beside the native Answer control.
 */

use Glpi\Application\View\TemplateRenderer;

class PluginTicketemailclientTimelineAction
{
    private const REPLY = 'reply';

    public function __construct()
    {
    }

    public static function renderReply(Ticket $ticket, bool $inline = true): string
    {
        return (new self())->renderForm($ticket, $inline);
    }

    /**
     * @param array{item?: mixed} $params
     * @return array<string, array<string, mixed>>
     */
    public static function getAnswerActions(array $params): array
    {
        $ticket = $params['item'] ?? null;
        if (!$ticket instanceof Ticket || !self::canUse($ticket)) {
            return [];
        }

        return [
            'ticketemailclient_email_reply' => self::action(),
        ];
    }

    /**
     * @param array{item?: mixed} $params
     */
    public static function displayActions(array $params): void
    {
        $ticket = $params['item'] ?? null;
        if (!$ticket instanceof Ticket || !self::canUse($ticket)) {
            return;
        }

        $settings = PluginTicketemailclientConfig::forEntity(
            (int) $ticket->getField('entities_id'),
        );

        $class = self::actionClass();
        $label = self::label();
        $auto_open = $settings['open_reply_on_ticket']
            ? ' data-ticketemailclient-auto-open="1"'
            : '';
        echo '<li><button type="button" class="btn btn-primary mb-2 ticketemailclient-timeline-action"'
            . ' aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" title="'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="collapse" data-bs-target="#new-'
            . htmlspecialchars($class, ENT_QUOTES, 'UTF-8')
            . '-block" aria-controls="new-'
            . htmlspecialchars($class, ENT_QUOTES, 'UTF-8')
            . '-block" aria-expanded="false"' . $auto_open . '><i class="ti ti-mail'
            . '"></i><span>'
            . htmlspecialchars(__('Reply', 'ticketemailclient'), ENT_QUOTES, 'UTF-8')
            . '</span></button></li>';
    }

    public static function canUse(Ticket $ticket): bool
    {
        if (!$ticket->canViewItem()
            || !($ticket->canUpdateItem()
                || (method_exists($ticket, 'canAddFollowups') && $ticket->canAddFollowups()))
        ) {
            return false;
        }

        $profiles_id = isset($_SESSION['glpiactiveprofile']['id'])
            ? (int) $_SESSION['glpiactiveprofile']['id']
            : null;
        return PluginTicketemailclientReplyPolicy::isEmailReplyAvailable(
            (int) $ticket->getField('entities_id'),
            $profiles_id,
        );
    }

    public function renderForm(Ticket $ticket, bool $inline = true): string
    {
        if (!self::canUse($ticket)) {
            return '';
        }

        return $this->renderReplyForm($ticket, $inline);
    }

    /**
     * @return array<string, mixed>
     */
    private static function action(): array
    {
        return [
            'type' => self::actionClass(),
            'class' => self::actionClass(),
            'icon' => 'ti ti-mail',
            'label' => self::label(),
            'short_label' => __('Reply', 'ticketemailclient'),
            'template' => '@ticketemailclient/timeline_action.html.twig',
            'item' => new self(),
            // The direct legacy action below keeps this control next to
            // Answer even when GLPI uses its merged action-button layout.
            'hide_in_menu' => true,
        ];
    }

    private function renderReplyForm(Ticket $ticket, bool $inline): string
    {
        $recipients_to = self::actorEmails($ticket, CommonITILActor::REQUESTER);
        $recipients_cc = self::actorEmails($ticket, CommonITILActor::OBSERVER);
        $tickets_id = (int) $ticket->getField('id');
        $web = Plugin::getWebDir('ticketemailclient');
        $editor_id = 'ticketemailclient-body-html-' . self::REPLY;

        return TemplateRenderer::getInstance()->render('@ticketemailclient/compose.html.twig', [
            'tickets_id' => $tickets_id,
            'ticket' => $ticket,
            'recipients_to' => $recipients_to,
            'recipients_cc' => $recipients_cc,
            'recipients_bcc' => [],
            'recipients_to_raw' => implode(', ', $recipients_to),
            'recipients_cc_raw' => implode(', ', $recipients_cc),
            'recipients_bcc_raw' => '',
            'subject' => PluginTicketemailclientConfig::subjectForTicket($ticket),
            'body_editor' => $this->editor(self::entitySignature($ticket), self::REPLY, 14),
            'editor_id' => $editor_id,
            'followup_template_dropdown' => self::followupTemplateDropdown(),
            'followup_source_dropdown' => self::followupSourceDropdown(),
            'followup_template_url' => self::followupTemplateUrl(),
            'csrf_token' => Session::getNewCSRFToken(),
            'ajax_csrf' => Session::getNewCSRFToken(true),
            'send_url' => $web . '/front/send.php',
            'upload_url' => $web . '/ajax/upload.php',
            'image_url' => $web . '/ajax/upload_image.php',
            'validate_url' => $web . '/ajax/validate_recipients.php',
            'user_autocomplete_url' => $web . '/ajax/autocomplete_users.php',
            'user_autocomplete_show_email' => PluginTicketemailclientConfig::forEntity(
                (int) $ticket->getField('entities_id'),
            )['recipient_autocomplete_show_email'],
            'attachment_max' => PluginTicketemailclientConfig::uploadMaxSizeLabel(),
            'mailbox_override' => false,
            'mailbox_matches' => [],
            'errors' => [],
            'history_attachments' => PluginTicketemailclientHistory::availableAttachments($ticket),
            'include_history' => false,
            'selected_history_attachments' => [],
            'inline' => $inline,
            'form_id' => 'ticketemailclient-email-reply',
            'close_target' => $inline ? self::collapseTarget() : '',
        ]);
    }


    private function editor(string $value, string $mode, int $rows): string
    {
        return Html::textarea([
            'name' => 'body_html',
            'value' => $value,
            'editor_id' => 'ticketemailclient-body-html-' . $mode,
            'enable_richtext' => true,
            'enable_images' => true,
            'enable_fileupload' => false,
            'rows' => $rows,
            'display' => false,
        ]);
    }

    public static function followupTemplateDropdown(): string
    {
        return (string) Dropdown::show('ITILFollowupTemplate', [
            'name' => 'itilfollowuptemplates_id',
            'display' => false,
            'addicon' => true,
        ]);
    }

    public static function followupSourceDropdown(): string
    {
        ob_start();
        RequestType::dropdown([
            'name'      => 'requesttypes_id',
            'value'     => RequestType::getDefault('followup'),
            'condition' => ['is_active' => 1, 'is_itilfollowup' => 1],
        ]);
        return (string) ob_get_clean();
    }

    public static function followupTemplateUrl(): string
    {
        global $CFG_GLPI;
        return (string) $CFG_GLPI['root_doc'] . '/ajax/itilfollowup.php';
    }

    /** @return list<string> */
    private static function actorEmails(Ticket $ticket, int $actorType): array
    {
        $emails = [];
        foreach ($ticket->getUsers($actorType) as $link) {
            $email = trim((string) ($link['alternative_email'] ?? ''));
            if ($email === '' && !empty($link['users_id'])) {
                $user = new User();
                if ($user->getFromDB((int) $link['users_id'])) {
                    $email = (string) $user->getDefaultEmail();
                }
            }
            if ($email !== '') {
                $emails[] = $email;
            }
        }
        return $emails;
    }

    /**
     * Return the ticket entity's email signature as HTML,
     * wrapped in a separated block. Shown by default in
     * the compose body so the sender sees the unit signature.
     */
    private static function entitySignature(Ticket $ticket): string
    {
        $sig = trim(PluginTicketemailclientConfig::signatureForTicket($ticket));
        if ($sig === '') {
            $entity = new Entity();
            if (!$entity->getFromDB((int) $ticket->getField('entities_id'))) {
                return '';
            }
            $sig = trim((string) $entity->getField('mailing_signature'));
            if ($sig === '') {
                return '';
            }
        }
        // ponytail: nl2br for plain text; HTML passes through as-is
        $html = preg_match('/<[a-z][\s\S]*>/i', $sig) ? $sig : nl2br(htmlspecialchars($sig, ENT_QUOTES, 'UTF-8'));

        return '<hr class="ticketemailclient-signature-sep"><div class="ticketemailclient-signature">'
            . $html . '</div>';
    }

    private static function actionClass(): string
    {
        return 'PluginTicketemailclientTimelineReply';
    }

    private static function collapseTarget(): string
    {
        return '#new-' . self::actionClass() . '-block';
    }

    private static function label(): string
    {
        return __('E-Mail antworten', 'ticketemailclient');
    }
}
