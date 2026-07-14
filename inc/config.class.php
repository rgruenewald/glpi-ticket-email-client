<?php
/**
 * inc/config.class.php — thin reader over GLPI's core
 * SMTP configuration. The plugin does NOT ship its own
 * SMTP config form: it always reads from GLPI's core
 * config (spec § A8 / A9).
 *
 * Both code paths are intentionally exposed because
 * `$CFG_GLPI['smtp_*']` is GLPI's primary mailer config
 * array; the `Config::getConfigurationValue()` wrapper
 * is the modern accessor used elsewhere in GLPI 10.
 */
class PluginTicketemailclientConfig
{
    /**
     * SMTP host. Empty string when GLPI is not yet
     * configured.
     */
    public static function smtpHost(): string
    {
        $value = Config::getConfigurationValue('core', 'smtp_host');
        if ($value === null || $value === false) {
            // Fall back to the global config array for
            // installations that have not yet migrated
            // their mailer config to the typed column.
            global $CFG_GLPI;
            return (string) ($CFG_GLPI['smtp_host'] ?? '');
        }
        return (string) $value;
    }

    public static function smtpPort(): int
    {
        global $CFG_GLPI;
        $value = Config::getConfigurationValue('core', 'smtp_port');
        if ($value === null || $value === false) {
            return (int) ($CFG_GLPI['smtp_port'] ?? 25);
        }
        return (int) $value;
    }

    public static function smtpUsername(): string
    {
        global $CFG_GLPI;
        $value = Config::getConfigurationValue('core', 'smtp_username');
        if ($value === null || $value === false) {
            return (string) ($CFG_GLPI['smtp_username'] ?? '');
        }
        return (string) $value;
    }

    public static function smtpPassword(): string
    {
        global $CFG_GLPI;
        $value = Config::getConfigurationValue('core', 'smtp_passwd');
        if ($value === null || $value === false || $value === '') {
            $value = $CFG_GLPI['smtp_passwd'] ?? '';
        }
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        // GLPI stores smtp_passwd encrypted; GLPIMailer decrypts on send.
        $plain = (new GLPIKey())->decrypt($value);
        return is_string($plain) ? $plain : '';
    }

    /**
     * SMTP secure transport for PHPMailer.
     * Maps GLPI MAIL_* ints: 2=ssl, 3/4=tls, else none.
     */
    public static function smtpMode(): string
    {
        global $CFG_GLPI;
        $value = Config::getConfigurationValue('core', 'smtp_mode');
        if ($value === null || $value === false) {
            $value = $CFG_GLPI['smtp_mode'] ?? 0;
        }
        // Accept already-mapped strings from tests/mocks.
        if ($value === 'ssl' || $value === 'tls') {
            return $value;
        }
        $mode = (int) $value;
        if ($mode === 2 /* MAIL_SMTPSSL */) {
            return 'ssl';
        }
        if ($mode === 3 /* MAIL_SMTPTLS */ || $mode === 4 /* MAIL_SMTPOAUTH */) {
            return 'tls';
        }
        return '';
    }

    /** Whether GLPI wants peer cert verification for SMTP TLS/SSL. */
    public static function smtpCheckCertificate(): bool
    {
        global $CFG_GLPI;
        $value = Config::getConfigurationValue('core', 'smtp_check_certificate');
        if ($value === null || $value === false) {
            $value = $CFG_GLPI['smtp_check_certificate'] ?? 1;
        }
        return (bool) $value;
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
        if (!$DB->tableExists('glpi_plugin_ticketemailclient_configs')) {
            return $settings;
        }
        foreach ([$entities_id, 0] as $id) {
            $row = $DB->request([
                'FROM'  => 'glpi_plugin_ticketemailclient_configs',
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
            'glpi_plugin_ticketemailclient_configs',
            [
                'subject_prefix'                    => substr(trim($subject_prefix), 0, 255),
                'signature_html'                    => PluginTicketemailclientTimeline::sanitizeHtml($signature_html),
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
