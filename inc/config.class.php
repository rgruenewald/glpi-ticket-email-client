<?php
/**
 * inc/config.class.php — reader for the small subset of GLPI core
 * configuration needed outside GLPIMailer. Outbound transport itself
 * always uses GLPI's configured mailer and has no plugin-side settings.
 */
class PluginTicketmailerConfig
{

    /** Compatibility accessor retained for external callers. */
    public static function smtpUsername(): string
    {
        global $CFG_GLPI;
        /** @disregard P1013 GLPI provides this method at runtime. */
        $value = Config::getConfigurationValue('core', 'smtp_username');
        if ($value === null || $value === false) {
            return (string) ($CFG_GLPI['smtp_username'] ?? '');
        }
        return (string) $value;
    }


    /**
     * Maximum upload size for attachments (in bytes).
     * Mirrors GLPI's $CFG_GLPI['upload_max_size'] per
     * spec § Invariants.
     */
    public static function uploadMaxSize(): int
    {
        global $CFG_GLPI;
        return (int) ($CFG_GLPI['upload_max_size'] ?? (5 * 1024 * 1024));
    }

    /**
     * @return array{
     *     notificationtemplates_id:int,
     *     signature_html:string,
     *     set_waiting:bool,
     *     timeline_newest_first:bool,
     * }
     */
    public static function forEntity(int $entities_id): array
    {
        global $DB;
        $settings = [
            'notificationtemplates_id'          => 0,
            'signature_html'                    => '',
            'set_waiting'                       => true,
            'timeline_newest_first'             => true,
        ];
        if (!$DB->tableExists('glpi_plugin_ticketmailer_configs')) {
            return $settings;
        }
        $global = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_configs',
            'WHERE' => ['entities_id' => 0],
        ])->current();
        if ($global) {
            $settings['set_waiting'] = (bool) $global['set_waiting'];
            $settings['timeline_newest_first'] = !isset($global['timeline_newest_first'])
                || (bool) $global['timeline_newest_first'];
        }
        $entity = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_configs',
            'WHERE' => ['entities_id' => $entities_id],
        ])->current();
        $settings['notificationtemplates_id'] = (int) ($entity['notificationtemplates_id'] ?? 0);
        $settings['signature_html'] = (string) (($entity ?: $global)['signature_html'] ?? '');
        return $settings;
    }

    public static function saveEntity(
        int $entities_id,
        int $notificationtemplates_id,
        bool $set_waiting,
        bool $timeline_newest_first,
    ): void {
        global $DB;
        $globalSettings = self::forEntity(0);
        $global = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_configs',
            'WHERE' => ['entities_id' => 0],
        ])->current() ?? [];
        $local = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_configs',
            'WHERE' => ['entities_id' => $entities_id],
        ])->current() ?? [];
        $values = [
            'notificationtemplates_id'          => $entities_id === 0
                ? self::validTicketTemplateId($notificationtemplates_id)
                : (int) $globalSettings['notificationtemplates_id'],
            'signature_html'                    => (string) ($globalSettings['signature_html'] ?? ''),
            'set_waiting'                       => $set_waiting ? 1 : 0,
            'timeline_newest_first'             => $timeline_newest_first ? 1 : 0,
        ];
        if (isset($global['subject_prefix'])) {
            $values['subject_prefix'] = (string) $global['subject_prefix'];
        }
        $DB->updateOrInsert(
            'glpi_plugin_ticketmailer_configs',
            $values,
            ['entities_id' => 0],
        );
        if ($entities_id === 0) {
            return;
        }
        $values = [
            'notificationtemplates_id'          => self::validTicketTemplateId($notificationtemplates_id),
            'signature_html'                    => (string) ($local['signature_html'] ?? ''),
            'set_waiting'                       => 1,
            'timeline_newest_first'             => 1,
        ];
        if (isset($local['subject_prefix'])) {
            $values['subject_prefix'] = (string) $local['subject_prefix'];
        }
        $DB->updateOrInsert(
            'glpi_plugin_ticketmailer_configs',
            $values,
            ['entities_id' => $entities_id],
        );
    }

    /**
     * Apply the configured entity order before GLPI obtains timeline entries.
     * This updates only the current session and never writes user preferences.
     */
    public static function applyTimelineOrderForCurrentTicket(): void
    {
        if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'ticket.form.php') {
            return;
        }
        $tickets_id = (int) ($_GET['id'] ?? 0);
        if ($tickets_id <= 0) {
            return;
        }

        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return;
        }

        $timeline_order = self::forEntity(
            (int) $ticket->getField('entities_id'),
        )['timeline_newest_first']
            ? CommonITILObject::TIMELINE_ORDER_REVERSE
            : CommonITILObject::TIMELINE_ORDER_NATURAL;

        $_SESSION['glpitimeline_order'] = $timeline_order;
        $GLOBALS['CFG_GLPI']['timeline_order'] = $timeline_order;
    }

    /**
     * @return array{subject:string, signature:string, native_template_selected:bool}
     */
    public static function contentForTicket(Ticket $ticket): array
    {
        $templateId = self::notificationTemplateForEntity((int) $ticket->getField('entities_id'));
        if ($templateId > 0) {
            $rendered = self::renderNotificationTemplate($templateId, $ticket);
            return [
                'subject' => self::humanSubject(
                    $rendered['subject'] !== '' ? $rendered['subject'] : self::fallbackSubjectForTicket($ticket),
                    (int) $ticket->getField('id'),
                ),
                'signature' => $rendered['signature'],
                'native_template_selected' => true,
            ];
        }

        return [
            'subject' => self::humanSubject(
                self::fallbackSubjectForTicket($ticket),
                (int) $ticket->getField('id'),
            ),
            'signature' => self::expandTicketVariables(
                self::legacySignatureForEntity((int) $ticket->getField('entities_id')),
                $ticket,
                true,
            ),
            'native_template_selected' => false,
        ];
    }

    public static function subjectForTicket(Ticket $ticket): string
    {
        return self::contentForTicket($ticket)['subject'];
    }

    public static function signatureForTicket(Ticket $ticket): string
    {
        return self::contentForTicket($ticket)['signature'];
    }

    /**
     * @return array{direct:int, effective:int, source_entities_id:int}
     */
    public static function notificationTemplateAssignmentForEntity(int $entities_id): array
    {
        global $DB;
        $direct = 0;
        if (!$DB->tableExists('glpi_plugin_ticketmailer_configs')) {
            return ['direct' => 0, 'effective' => 0, 'source_entities_id' => $entities_id];
        }
        $entities = [$entities_id];
        if ($entities_id !== 0) {
            $entities = array_merge($entities, array_values(getAncestorsOf(Entity::getTable(), $entities_id)));
            if (!in_array(0, $entities, true)) {
                $entities[] = 0;
            }
        }
        foreach (array_unique(array_map('intval', $entities)) as $entityId) {
            $row = $DB->request([
                'FROM'  => 'glpi_plugin_ticketmailer_configs',
                'WHERE' => ['entities_id' => $entityId],
            ])->current();
            $templateId = (int) ($row['notificationtemplates_id'] ?? 0);
            if ($entityId === $entities_id) {
                $direct = $templateId;
            }
            if ($templateId > 0 && self::validTicketTemplateId($templateId) > 0) {
                return [
                    'direct' => $direct,
                    'effective' => $templateId,
                    'source_entities_id' => $entityId,
                ];
            }
        }
        return ['direct' => $direct, 'effective' => 0, 'source_entities_id' => $entities_id];
    }

    public static function notificationTemplateForEntity(int $entities_id): int
    {
        return self::notificationTemplateAssignmentForEntity($entities_id)['effective'];
    }

    public static function saveNotificationTemplateAssignment(
        int $entities_id,
        int $notificationtemplates_id,
    ): void {
        global $DB;
        $local = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_configs',
            'WHERE' => ['entities_id' => $entities_id],
        ])->current();
        $values = [
            'notificationtemplates_id'          => self::validTicketTemplateId($notificationtemplates_id),
            'signature_html'                    => (string) ($local['signature_html'] ?? ''),
            'set_waiting'                       => isset($local['set_waiting']) ? (int) $local['set_waiting'] : 1,
            'timeline_newest_first'             => isset($local['timeline_newest_first']) ? (int) $local['timeline_newest_first'] : 1,
        ];
        if (isset($local['subject_prefix'])) {
            $values['subject_prefix'] = (string) $local['subject_prefix'];
        }
        $DB->updateOrInsert(
            'glpi_plugin_ticketmailer_configs',
            $values,
            ['entities_id' => $entities_id],
        );
    }

    private static function validTicketTemplateId(int $notificationtemplates_id): int
    {
        if ($notificationtemplates_id <= 0) {
            return 0;
        }
        $template = new NotificationTemplate();
        return $template->getFromDB($notificationtemplates_id)
            && $template->getField('itemtype') === Ticket::class
            ? $notificationtemplates_id
            : 0;
    }

    /** @return array{subject:string, signature:string} */
    private static function renderNotificationTemplate(int $notificationtemplates_id, Ticket $ticket): array
    {
        $template = new NotificationTemplate();
        $target = NotificationTarget::getInstance($ticket, 'update', [
            'entities_id' => (int) $ticket->getField('entities_id'),
        ]);
        if (
            !$template->getFromDB($notificationtemplates_id)
            || $template->getField('itemtype') !== Ticket::class
            || !$target instanceof NotificationTargetTicket
        ) {
            return ['subject' => '', 'signature' => ''];
        }
        $target->setMode(Notification_NotificationTemplate::MODE_MAIL);
        $target->setAllowResponse(false);
        $template->resetComputedTemplates();
        $template->setSignature('');
        $options = ['item' => $ticket];
        $tid = $template->getTemplateByLanguage(
            $target,
            [
                'language' => (string) ($_SESSION['glpilanguage'] ?? ($GLOBALS['CFG_GLPI']['language'] ?? 'en_GB')),
                'additionnaloption' => ['usertype' => NotificationTarget::GLPI_USER],
            ],
            'update',
            $options,
        );
        if ($tid === false) {
            return ['subject' => '', 'signature' => ''];
        }
        $rendered = $template->templates_by_languages[$tid] ?? [];
        return [
            'subject' => self::cleanSubject((string) ($rendered['subject'] ?? '')),
            'signature' => self::renderedBodyFragment((string) ($rendered['content_html'] ?? '')),
        ];
    }

    private static function fallbackSubjectForTicket(Ticket $ticket): string
    {
        return self::cleanSubject(sprintf(
            '[%d] %s',
            (int) $ticket->getField('id'),
            strip_tags((string) $ticket->getField('name')),
        ));
    }

    public static function humanSubject(string $subject, int $tickets_id): string
    {
        $subject = trim((string) preg_replace(
            '/(?:\[[^\]\r\n]*#0*' . preg_quote((string) $tickets_id, '/') . '\]|\[0*' . preg_quote((string) $tickets_id, '/') . '\])\s*/i',
            '',
            $subject,
        ));
        return $subject;
    }

    public static function assembleSubject(string $subject, int $tickets_id, bool $new_conversation): string
    {
        if (preg_match('/[\x00\r\n]/', $subject) === 1) {
            return $subject;
        }
        $subject = trim((string) preg_replace('/\[[^\]\r\n]*#\d+\]\s*/', '', $subject));
        $subject = self::humanSubject($subject, $tickets_id);
        return $new_conversation ? $subject : '[GLPI #' . $tickets_id . '] ' . $subject;
    }

    private static function cleanSubject(string $subject): string
    {
        return trim((string) preg_replace('/[\x00\r\n]+/', ' ', $subject));
    }

    private static function renderedBodyFragment(string $html): string
    {
        if ($html === '' || !preg_match('/<body[^>]*>([\\s\\S]*?)<\\/body>/i', $html, $match)) {
            return '';
        }
        $body = preg_replace(
            '/<br>' . preg_quote(
                sprintf(__('Automatically generated by %s'), (string) ($GLOBALS['CFG_GLPI']['app_name'] ?? 'GLPI')),
                '/',
            ) . '<br><br>\s*$/s',
            '',
            trim($match[1]),
        );
        return PluginTicketmailerTimeline::sanitizeHtml(trim((string) $body));
    }

    private static function legacySignatureForEntity(int $entities_id): string
    {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_ticketmailer_configs')) {
            return '';
        }
        $entities = [$entities_id];
        if ($entities_id !== 0) {
            $entities = array_merge($entities, array_values(getAncestorsOf(Entity::getTable(), $entities_id)));
            if (!in_array(0, $entities, true)) {
                $entities[] = 0;
            }
        }
        foreach (array_unique(array_map('intval', $entities)) as $entityId) {
            $row = $DB->request([
                'FROM'  => 'glpi_plugin_ticketmailer_configs',
                'WHERE' => ['entities_id' => $entityId],
            ])->current();
            $signature = trim((string) ($row['signature_html'] ?? ''));
            if ($signature !== '') {
                return $signature;
            }
        }
        return '';
    }

    public static function variableHelpHtml(): string
    {
        $groups = [
            __('Ticket', 'ticketmailer') => [
                '##ticket.id##', '##ticket.title##', '##ticket.description##',
                '##ticket.creationdate##', '##ticket.lastupdate##', '##ticket.status##',
                '##ticket.priority##', '##ticket.urgency##', '##ticket.impact##', '##ticket.url##',
            ],
            __('Agent', 'ticketmailer') => [
                '##agent.firstname##', '##agent.lastname##', '##agent.name##',
                '##agent.email##', '##agent.phone##', '##agent.phone2##', '##agent.mobile##',
            ],
            __('Entity', 'ticketmailer') => [
                '##entity.name##', '##entity.fullname##', '##entity.email##',
                '##entity.phone##', '##entity.fax##', '##entity.address##',
                '##entity.postcode##', '##entity.town##', '##entity.state##', '##entity.country##',
            ],
        ];
        $html = '<details class="mt-2"><summary>' . __('Available variables', 'ticketmailer') . '</summary>';
        foreach ($groups as $label => $variables) {
            $html .= '<strong class="d-block mt-2">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong><ul class="mb-0">';
            foreach ($variables as $variable) {
                $html .= '<li><code>' . $variable . '</code></li>';
            }
            $html .= '</ul>';
        }
        return $html . '</details>';
    }

    private static function expandTicketVariables(string $template, Ticket $ticket, bool $html): string
    {
        $escape = static fn(mixed $text): string => $html
            ? htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : (string) $text;
        $value = static fn(string $field): string => $escape($ticket->getField($field));

        $agent = new User();
        $agent->getFromDB((int) Session::getLoginUserID());
        $entity = new Entity();
        $entity->getFromDB((int) $ticket->getField('entities_id'));
        $agentEmail = UserEmail::getDefaultForUser((int) Session::getLoginUserID());

        return strtr($template, [
            '##ticket.id##'           => $value('id'),
            '##ticket.title##'        => $value('name'),
            '##ticket.description##'  => $value('content'),
            '##ticket.creationdate##' => $value('date'),
            '##ticket.lastupdate##'   => $value('date_mod'),
            '##ticket.status##'       => $value('status'),
            '##ticket.priority##'     => $value('priority'),
            '##ticket.urgency##'      => $value('urgency'),
            '##ticket.impact##'       => $value('impact'),
            '##ticket.url##'          => $escape(method_exists($ticket, 'getLinkURL') ? $ticket->getLinkURL() : ''),
            '##agent.firstname##'     => $escape($agent->getField('firstname')),
            '##agent.lastname##'      => $escape($agent->getField('realname')),
            '##agent.name##'          => $escape($agent->getField('name')),
            '##agent.email##'         => $escape($agentEmail),
            '##agent.phone##'         => $escape($agent->getField('phone')),
            '##agent.phone2##'        => $escape($agent->getField('phone2')),
            '##agent.mobile##'        => $escape($agent->getField('mobile')),
            '##entity.name##'         => $escape($entity->getField('name')),
            '##entity.fullname##'     => $escape($entity->getField('completename')),
            '##entity.email##'        => $escape($entity->getField('email')),
            '##entity.phone##'        => $escape($entity->getField('phonenumber')),
            '##entity.fax##'          => $escape($entity->getField('fax')),
            '##entity.address##'      => $escape($entity->getField('address')),
            '##entity.postcode##'     => $escape($entity->getField('postcode')),
            '##entity.town##'         => $escape($entity->getField('town')),
            '##entity.state##'        => $escape($entity->getField('state')),
            '##entity.country##'      => $escape($entity->getField('country')),
        ]);
    }

    public static function setWaitingAfterSend(Ticket $ticket): bool
    {
        return self::forEntity((int) $ticket->getField('entities_id'))['set_waiting'];
    }

    public static function uploadMaxSizeLabel(): string
    {
        $bytes = self::uploadMaxSize();
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, '.', ''), '0'), '.') . ' MB';
        }
        return max(1, (int) ceil($bytes / 1024)) . ' KB';
    }
}
