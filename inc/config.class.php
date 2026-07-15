<?php
/**
 * inc/config.class.php — reader for the small subset of GLPI core
 * configuration needed outside GLPIMailer. Outbound transport itself
 * always uses GLPI's configured mailer and has no plugin-side settings.
 */
class PluginTicketmailerConfig
{

    public static function smtpUsername(): string
    {
        global $CFG_GLPI;
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
     *     subject_prefix:string,
     *     signature_html:string,
     *     set_waiting:bool,
     *     timeline_newest_first:bool,
     *     open_reply_on_ticket:bool,
     *     recipient_autocomplete_show_email:bool
     * }
     */
    public static function forEntity(int $entities_id): array
    {
        global $DB;
        $settings = [
            'subject_prefix'                    => '[##ticket.id##]',
            'signature_html'                    => '',
            'set_waiting'                       => true,
            'timeline_newest_first'             => true,
            'open_reply_on_ticket'              => true,
            'recipient_autocomplete_show_email' => true,
        ];
        if (!$DB->tableExists('glpi_plugin_ticketmailer_configs')) {
            return $settings;
        }
        $global = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_configs',
            'WHERE' => ['entities_id' => 0],
        ])->current();
        if ($global) {
            $settings['subject_prefix'] = (string) $global['subject_prefix'];
            $settings['set_waiting'] = (bool) $global['set_waiting'];
            $settings['timeline_newest_first'] = !isset($global['timeline_newest_first'])
                || (bool) $global['timeline_newest_first'];
            $settings['open_reply_on_ticket'] = !isset($global['open_reply_on_ticket'])
                || (bool) $global['open_reply_on_ticket'];
            $settings['recipient_autocomplete_show_email'] = !isset($global['recipient_autocomplete_show_email'])
                || (bool) $global['recipient_autocomplete_show_email'];
        }
        $entity = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_configs',
            'WHERE' => ['entities_id' => $entities_id],
        ])->current();
        $settings['signature_html'] = (string) (($entity ?: $global)['signature_html'] ?? '');
        return $settings;
    }

    public static function saveEntity(
        int $entities_id,
        string $subject_prefix,
        string $signature_html,
        bool $set_waiting,
        bool $timeline_newest_first,
        bool $open_reply_on_ticket,
        bool $recipient_autocomplete_show_email,
    ): void {
        global $DB;
        $global = self::forEntity(0);
        $DB->updateOrInsert(
            'glpi_plugin_ticketmailer_configs',
            [
                'subject_prefix'                    => substr(trim($subject_prefix), 0, 255),
                'signature_html'                    => $global['signature_html'],
                'set_waiting'                       => $set_waiting ? 1 : 0,
                'timeline_newest_first'             => $timeline_newest_first ? 1 : 0,
                'open_reply_on_ticket'              => $open_reply_on_ticket ? 1 : 0,
                'recipient_autocomplete_show_email' => $recipient_autocomplete_show_email ? 1 : 0,
            ],
            ['entities_id' => 0],
        );
        if ($entities_id === 0) {
            $DB->update(
                'glpi_plugin_ticketmailer_configs',
                ['signature_html' => PluginTicketmailerTimeline::sanitizeHtml($signature_html)],
                ['entities_id' => 0],
            );
            return;
        }
        $DB->updateOrInsert(
            'glpi_plugin_ticketmailer_configs',
            [
                'subject_prefix'                    => '[##ticket.id##]',
                'signature_html'                    => PluginTicketmailerTimeline::sanitizeHtml($signature_html),
                'set_waiting'                       => 1,
                'timeline_newest_first'             => 1,
                'open_reply_on_ticket'              => 1,
                'recipient_autocomplete_show_email' => 1,
            ],
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

    public static function subjectForTicket(Ticket $ticket): string
    {
        $settings = self::forEntity((int) $ticket->getField('entities_id'));
        $template = $settings['subject_prefix'] . ' ##ticket.title##';
        return trim(strip_tags(self::expandTicketVariables($template, $ticket, false)));
    }

    public static function signatureForTicket(Ticket $ticket): string
    {
        return self::expandTicketVariables(
            self::forEntity((int) $ticket->getField('entities_id'))['signature_html'],
            $ticket,
            true,
        );
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
