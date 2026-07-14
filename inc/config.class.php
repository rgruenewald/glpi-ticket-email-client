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
            'subject_prefix'                    => '[#%d]',
            'signature_html'                    => '',
            'set_waiting'                       => true,
            'timeline_newest_first'             => true,
            'open_reply_on_ticket'              => true,
            'recipient_autocomplete_show_email' => true,
        ];
        if (!$DB->tableExists('glpi_plugin_ticketmailer_configs')) {
            return $settings;
        }
        foreach ([$entities_id, 0] as $id) {
            $row = $DB->request([
                'FROM'  => 'glpi_plugin_ticketmailer_configs',
                'WHERE' => ['entities_id' => $id],
            ])->current();
            if ($row) {
                return [
                    'subject_prefix'                    => (string) $row['subject_prefix'],
                    'signature_html'                    => (string) ($row['signature_html'] ?? ''),
                    'set_waiting'                       => (bool) $row['set_waiting'],
                    'timeline_newest_first'             => !isset($row['timeline_newest_first'])
                        || (bool) $row['timeline_newest_first'],
                    'open_reply_on_ticket'              => !isset($row['open_reply_on_ticket'])
                        || (bool) $row['open_reply_on_ticket'],
                    'recipient_autocomplete_show_email' => !isset($row['recipient_autocomplete_show_email'])
                        || (bool) $row['recipient_autocomplete_show_email'],
                ];
            }
        }
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
        $DB->updateOrInsert(
            'glpi_plugin_ticketmailer_configs',
            [
                'subject_prefix'                    => substr(trim($subject_prefix), 0, 255),
                'signature_html'                    => PluginTicketmailerTimeline::sanitizeHtml($signature_html),
                'set_waiting'                       => $set_waiting ? 1 : 0,
                'timeline_newest_first'             => $timeline_newest_first ? 1 : 0,
                'open_reply_on_ticket'              => $open_reply_on_ticket ? 1 : 0,
                'recipient_autocomplete_show_email' => $recipient_autocomplete_show_email ? 1 : 0,
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

        $_SESSION['glpitimeline_order'] = self::forEntity(
            (int) $ticket->getField('entities_id'),
        )['timeline_newest_first']
            ? CommonITILObject::TIMELINE_ORDER_REVERSE
            : CommonITILObject::TIMELINE_ORDER_NATURAL;
    }

    public static function subjectForTicket(Ticket $ticket): string
    {
        $settings = self::forEntity((int) $ticket->getField('entities_id'));
        $prefix = str_replace('%d', (string) $ticket->getField('id'), $settings['subject_prefix']);
        return trim($prefix . ' ' . (string) $ticket->getField('name'));
    }

    public static function signatureForTicket(Ticket $ticket): string
    {
        return self::forEntity((int) $ticket->getField('entities_id'))['signature_html'];
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
