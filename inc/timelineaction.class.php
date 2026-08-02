<?php
/**
 * Ticket-timeline action for composing email inline.
 *
 * GLPI 10 renders TIMELINE_ANSWER_ACTIONS in the same collapsible answer
 * region as its native form. TIMELINE_ACTIONS supplies an always-visible
 * button beside the native Answer control.
 */

use Glpi\Application\View\TemplateRenderer;

class PluginTicketmailerTimelineAction
{
    private const REPLY = 'reply';

    public function __construct()
    {
    }

    public static function renderReply(Ticket $ticket, bool $inline = true): string
    {
        return (new self())->renderForm($ticket, $inline);
    }

    public static function modal(Ticket $ticket): string
    {
        $name = self::modalName($ticket);
        return (string) Ajax::createModalWindow(
            $name,
            Plugin::getWebDir('ticketmailer') . '/front/compose.php',
            [
                'display' => false,
                'title' => self::label(),
                'modal_class' => 'modal-xl',
                'extraparams' => ['tickets_id' => (int) $ticket->getField('id')],
            ],
        );
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

        $actions = [
            'ticketmailer_email_reply' => self::action(),
        ];
        if (PluginTicketmailerConfig::forEntity(0)['hide_native_answer']) {
            $actions['answer'] = self::nativeAnswerAction($ticket);
        }
        return $actions;
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

        $label = self::label();
        $modal = self::modalName($ticket);
        $open = $modal . '.show();';
        $ready = 'window.' . $modal . ' ? 1 : 0';
        echo '<li><button type="button" class="btn btn-primary mb-2 ticketmailer-timeline-action"'
            . ' aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" title="'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" onclick="' . $open . '"'
            . ' data-ticketmailer-modal-ready="' . $ready . '"><i class="ti ti-mail"></i><span>'
            . htmlspecialchars(__('Reply', 'ticketmailer'), ENT_QUOTES, 'UTF-8')
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
        return PluginTicketmailerReplyPolicy::isEmailReplyAvailable(
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
            'short_label' => __('Reply', 'ticketmailer'),
            'template' => '@ticketmailer/timeline_action.html.twig',
            'item' => new self(),
            // The direct legacy action below keeps this control next to
            // Answer even when GLPI uses its merged action-button layout.
            'hide_in_menu' => true,
        ];
    }

    /**
     * GLPI descriptor keys: x27answerx27 x27typex27 x27classx27 x27labelx27
     * x27short_labelx27 x27itemx27.
     *
     * @return array<string, mixed>
     */
    private static function nativeAnswerAction(Ticket $ticket): array
    {
        $followup = new ITILFollowup();
        $followup->getEmpty();
        $followup->fields['itemtype'] = Ticket::class;
        $followup->fields['items_id'] = (int) $ticket->getField('id');

        return [
            'type' => ITILFollowup::class,
            'class' => ITILFollowup::class,
            'icon' => ITILFollowup::getIcon(),
            'label' => _x('button', 'Answer'),
            'short_label' => _x('button', 'Answer'),
            'template' => 'components/itilobject/timeline/form_followup.html.twig',
            'item' => $followup,
            'hide_in_menu' => true,
        ];
    }

    private function renderReplyForm(Ticket $ticket, bool $inline): string
    {
        $recipients_to = self::actorEmails($ticket, CommonITILActor::REQUESTER);
        $recipients_cc = self::actorEmails($ticket, CommonITILActor::OBSERVER);
        $tickets_id = (int) $ticket->getField('id');
        $web = Plugin::getWebDir('ticketmailer');
        $editor_id = 'ticketmailer-body-html-' . self::REPLY . '-' . $tickets_id . '-' . ($inline ? 'inline' : 'modal');
        $content = PluginTicketmailerConfig::contentForTicket($ticket);
        $native_followup_form = self::nativeFollowupForm($ticket);

        return TemplateRenderer::getInstance()->render('@ticketmailer/compose.html.twig', [
            'tickets_id' => $tickets_id,
            'ticket' => $ticket,
            'recipients_to' => $recipients_to,
            'recipients_cc' => $recipients_cc,
            'recipients_bcc' => [],
            'recipients_to_raw' => implode(', ', $recipients_to),
            'recipients_cc_raw' => implode(', ', $recipients_cc),
            'recipients_bcc_raw' => '',
            'subject' => $content['subject'],
            'body_editor' => $this->editor(self::entitySignature($ticket, $content), $editor_id, 14),
            'editor_id' => $editor_id,
            'followup_template_dropdown' => self::followupTemplateDropdown(),
            'solution_template_dropdown' => self::solutionTemplateDropdown(),
            'followup_source_dropdown' => self::followupSourceDropdown(),
            'followup_template_url' => self::followupTemplateUrl(),
            'solution_template_url' => self::solutionTemplateUrl(),
            'csrf_token' => Session::getNewCSRFToken(),
            'ajax_csrf' => Session::getNewCSRFToken(true),
            'send_url' => $web . '/front/send.php',
            'upload_url' => $web . '/ajax/upload.php',
            'image_url' => $web . '/ajax/upload_image.php',
            'validate_url' => $web . '/ajax/validate_recipients.php',
            'user_autocomplete_url' => $web . '/ajax/autocomplete_users.php',
            'user_autocomplete_show_email' => true,
            'set_waiting' => PluginTicketmailerConfig::setWaitingAfterSend($ticket),
            'attachment_max' => PluginTicketmailerConfig::uploadMaxSizeLabel(),
            'mailbox_override' => false,
            'mailbox_matches' => [],
            'errors' => [],
            'history_attachments' => PluginTicketmailerHistory::availableAttachments($ticket),
            'include_history' => false,
            'selected_history_attachments' => [],
            'inline' => $inline,
            'form_id' => 'ticketmailer-email-reply-' . $tickets_id . '-' . ($inline ? 'inline' : 'modal'),
            'native_followup_form' => $native_followup_form,
            'close_target' => $inline ? self::collapseTarget() : '',
        ]);
    }

    public static function nativeFollowupForm(Ticket $ticket): string
    {
        $had_private_default = array_key_exists('glpifollowup_private', $_SESSION);
        $private_default = $_SESSION['glpifollowup_private'] ?? null;
        $_SESSION['glpifollowup_private'] = 1;

        $buffer_level = ob_get_level();
        try {
            ob_start();
            (new ITILFollowup())->showForm(0, ['parent' => $ticket]);
            $form = (string) ob_get_clean();
            $form = preg_replace_callback(
                '/<input\b[^>]*>/i',
                static function (array $match): string {
                    if (preg_match('/\bname=["\']is_private["\']/i', $match[0]) !== 1) {
                        return $match[0];
                    }
                    if (preg_match('/\btype=["\']hidden["\']/i', $match[0]) === 1) {
                        return (string) preg_replace(
                            '/\bvalue=["\'][^"\']*["\']/i',
                            'value="1"',
                            $match[0],
                            1,
                        );
                    }
                    if (preg_match('/\btype=["\']checkbox["\']/i', $match[0]) === 1
                        && preg_match('/\bdisabled\b/i', $match[0]) !== 1) {
                        return (string) preg_replace('/\s*\/?>$/', ' disabled$0', $match[0], 1);
                    }
                    return $match[0];
                },
                $form,
            );
            $form = preg_replace(
                '/(<form\b[^>]*>)/',
                '$1<input type="hidden" name="_ticketmailer_internal_note" value="1">',
                (string) $form,
                1,
                $form_count,
            );
            if ($form_count !== 1) {
                throw new UnexpectedValueException('Native private followup form is incompatible.');
            }
            return (string) $form;
        } catch (Throwable $error) {
            if (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
            throw $error;
        } finally {
            if ($had_private_default) {
                $_SESSION['glpifollowup_private'] = $private_default;
            } else {
                unset($_SESSION['glpifollowup_private']);
            }
        }
    }

    private function editor(string $value, string $editor_id, int $rows): string
    {
        return Html::textarea([
            'name' => 'body_html',
            'value' => $value,
            'editor_id' => $editor_id,
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
            'emptylabel' => __('Answer templates', 'ticketmailer'),
        ]);
    }

    public static function solutionTemplateDropdown(): string
    {
        return (string) Dropdown::show('SolutionTemplate', [
            'name' => 'solutiontemplates_id',
            'display' => false,
            'addicon' => true,
            'emptylabel' => __('Solution templates', 'ticketmailer'),
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

    public static function solutionTemplateUrl(): string
    {
        global $CFG_GLPI;
        return (string) $CFG_GLPI['root_doc'] . '/ajax/solution.php';
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
     * Return the ticket entity's rendered notification-template signature
     * as the editable HTML block shown in the compose body.
     *
     * @param array{subject:string, signature:string, native_template_selected:bool}|null $content
     */
    private static function entitySignature(Ticket $ticket, ?array $content = null): string
    {
        $content ??= PluginTicketmailerConfig::contentForTicket($ticket);
        $sig = trim($content['signature']);
        if ($content['native_template_selected'] && $sig === '') {
            return '';
        }
        if ($sig === '') {
            $sig = trim((string) Notification::getMailingSignature(
                (int) $ticket->getField('entities_id'),
            ));
            if ($sig === '') {
                return '';
            }
        }
        $html = preg_match('/<[a-z][\s\S]*>/i', $sig)
            ? PluginTicketmailerTimeline::sanitizeHtml($sig)
            : nl2br(htmlspecialchars($sig, ENT_QUOTES, 'UTF-8'));

        return '<div class="ticketmailer-signature">' . $html . '</div>';
    }

    private static function modalName(Ticket $ticket): string
    {
        return 'ticketmailerEmailReply' . (int) $ticket->getField('id');
    }

    private static function actionClass(): string
    {
        return 'PluginTicketmailerTimelineReply';
    }

    private static function collapseTarget(): string
    {
        return '#new-' . self::actionClass() . '-block';
    }

    private static function label(): string
    {
        return __('E-Mail antworten', 'ticketmailer');
    }
}
