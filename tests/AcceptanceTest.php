<?php
/**
 * Acceptance test suite for the ticketmailer plugin.
 *
 * Each test method maps to a single acceptance criterion from
 * `.agent/contracts/ticket-mailer/spec.md` (A1, A2, …) and to
 * the matching structural check encoded in `score.sh`. Tests
 * are deliberately focused on observable, file-system-level
 * behaviour so they survive internal refactors.
 *
 * The canonical binary verifier is `score.sh`; this suite is
 * the long-form regression equivalent.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class AcceptanceTest extends TestCase
{
    private const REPO_ROOT = __DIR__ . '/..';
    private const COMPOSE_PATHS = ['inc', 'front', 'ajax'];

    // ---- A1: setup.php declares plugin metadata and registers hooks ----

    #[Test]
    public function a1_setup_declares_plugin_version_constant(): void
    {
        $this->assertFileExists(self::REPO_ROOT . '/setup.php');
        $contents = (string) file_get_contents(self::REPO_ROOT . '/setup.php');
        $this->assertMatchesRegularExpression(
            "/define\\(\\s*['\"]PLUGIN_TICKETMAILER_VERSION['\"]/",
            $contents,
            'setup.php must define PLUGIN_TICKETMAILER_VERSION (A1)',
        );
    }

    #[Test]
    public function a1_setup_declares_glpi_min_max_constants(): void
    {
        $contents = (string) file_get_contents(self::REPO_ROOT . '/setup.php');
        $this->assertMatchesRegularExpression(
            "/define\\(\\s*['\"]PLUGIN_TICKETMAILER_(MIN|MAX)_GLPI['\"]/",
            $contents,
            'setup.php must define PLUGIN_TICKETMAILER_{MIN,MAX}_GLPI (A1)',
        );
    }

    /** @dataProvider provideRequiredHooks */
    #[DataProvider('provideRequiredHooks')]
    #[Test]
    public function a1_setup_registers_required_hook(string $hook): void
    {
        $contents = (string) file_get_contents(self::REPO_ROOT . '/setup.php');
        $this->assertStringContainsString(
            "'$hook'",
            $contents,
            "setup.php must register the '$hook' hook (A1)",
        );
    }

    public static function provideRequiredHooks(): array
    {
        return [
            ['csrf_compliant'],
            ['post_init'],
            ['item_purge'],
        ];
    }

    // ---- A2: hook.php defines the install/uninstall/post_init functions ----

    /** @dataProvider provideHookFunctions */
    #[DataProvider('provideHookFunctions')]
    #[Test]
    public function a2_hook_function_is_defined(string $function): void
    {
        $haystack = '';
        foreach (['setup.php', 'hook.php'] as $candidate) {
            $path = self::REPO_ROOT . '/' . $candidate;
            if (is_file($path)) {
                $haystack .= (string) file_get_contents($path);
            }
        }
        $this->assertMatchesRegularExpression(
            "/function\\s+{$function}\\s*\\(/",
            $haystack,
            "Required hook function '$function' must be defined (A2)",
        );
    }

    public static function provideHookFunctions(): array
    {
        return [
            ['plugin_ticketmailer_install'],
            ['plugin_ticketmailer_uninstall'],
            ['plugin_ticketmailer_post_init'],
        ];
    }

    // ---- A7/A8/A13: audit log table schema ----

    /** @dataProvider provideAuditLogColumns */
    #[DataProvider('provideAuditLogColumns')]
    #[Test]
    public function a7_audit_log_table_defines_column(string $column): void
    {
        $this->assertDirectoryExists(self::REPO_ROOT . '/sql');
        $hits = self::grepRecursive('sql', '/\b' . preg_quote($column, '/') . '\b/');
        $this->assertNotEmpty(
            $hits,
            "sql/ must reference the required audit-log column '$column' (A7)",
        );
    }

    public static function provideAuditLogColumns(): array
    {
        return [
            ['tickets_id'],
            ['users_id'],
            ['sent_at'],
            ['subject'],
            ['recipients_to'],
            ['recipients_cc'],
            ['recipients_bcc'],
            ['status'],
        ];
    }

    #[Test]
    public function a7_audit_log_install_sql_creates_table(): void
    {
        $hits = self::grepRecursive('sql', '/CREATE\s+TABLE/i');
        $this->assertNotEmpty(
            $hits,
            'sql/ must contain a CREATE TABLE statement for the audit log (A7)',
        );
    }

    // ---- A9: compose path must not call GLPI native notification engine ----

    #[Test]
    public function a9_compose_path_does_not_use_native_notifications(): void
    {
        $pattern = '/Notification::raiseEvent|NotificationEvent::raiseEvent'
                 . '|NotificationTarget::getNotificationTargets'
                 . '|NotificationMailing::send/';
        $hits = '';
        foreach (self::COMPOSE_PATHS as $dir) {
            $hits .= self::grepRecursive($dir, $pattern);
        }
        $this->assertSame(
            '',
            trim($hits),
            "Compose path must not reference GLPI's native notification engine (A9):\n$hits",
        );
    }

    // ---- A8/A9: plugin reuses GLPI's smtp_* configuration ----

    #[Test]
    public function a8_mailer_reuses_glpi_smtp_config(): void
    {
        $pattern = '/CFG_GLPI\[\s*[\'\"](?:smtp_host|smtp_port|smtp_username|smtp_passwd|smtp_mode)'
                 . '|Config::getConfigurationValue\([^,]+,\s*[\'\"]smtp_/';
        $hits = self::grepRecursive('inc', $pattern);
        $this->assertNotEmpty(
            $hits,
            "inc/ must reference GLPI's smtp_* config via \$CFG_GLPI or Config::getConfigurationValue (A8/A9)",
        );
    }

    #[Test]
    public function a8_plugin_does_not_define_own_smtp_config_form(): void
    {
        $pattern = '/addfield.*(?:smtp_host|smtp_port|smtp_username|smtp_passwd)'
                 . '|(?:smtp_host|smtp_port|smtp_username|smtp_passwd)\s*=>/i';
        $hits = '';
        foreach (['inc', 'front'] as $dir) {
            $hits .= self::grepRecursive($dir, $pattern);
        }
        $this->assertSame(
            '',
            trim($hits),
            "Plugin must not define its own SMTP host/port/username/passwd config form (A8)",
        );
    }

    // ---- A4/A7: To/CC/BCC fields are referenced in the compose path ----

    #[Test]
    public function a4_to_cc_bcc_fields_referenced_in_compose_path(): void
    {
        $pattern = '/recipients_(?:to|cc|bcc)|["\'](?:to|cc|bcc)["\']\s*=>/i';
        $hits = '';
        foreach (self::COMPOSE_PATHS as $dir) {
            $hits .= self::grepRecursive($dir, $pattern);
        }
        $this->assertNotEmpty(
            $hits,
            'Compose path must reference To/CC/BCC fields (A4/A7)',
        );
    }

    // ---- A3: public history is opted into from the compose form ----

    #[Test]
    public function a3_compose_offers_independent_public_history_and_attachment_selection(): void
    {
        $compose = (string) file_get_contents(self::REPO_ROOT . '/templates/compose.html.twig');

        $this->assertStringContainsString('name="include_history"', $compose);
        $this->assertStringContainsString('name="history_attachments[]"', $compose);
        $this->assertStringNotContainsString('{% if not include_history %}hidden{% endif %}', $compose);
    }

    #[Test]
    public function a3_public_ticket_attachments_can_be_opened_after_ticket_read_authorization(): void
    {
        $compose = (string) file_get_contents(self::REPO_ROOT . '/templates/compose.html.twig');
        $preview = self::REPO_ROOT . '/front/history_attachment.php';

        $this->assertStringContainsString('attachment.preview_url', $compose);
        $this->assertStringContainsString('ticketmailer-attachment-open', $compose);
        $this->assertStringContainsString('ti ti-external-link', $compose);
        $this->assertStringNotContainsString("__('Open', 'ticketmailer')", $compose);
        $this->assertFileExists($preview);
        $this->assertStringContainsString('canViewItem()', (string) file_get_contents($preview));
        $this->assertStringContainsString('resolveAttachment', (string) file_get_contents($preview));
    }

    #[Test]
    public function a3_separate_forwarding_surface_is_removed(): void
    {
        $this->assertFileDoesNotExist(self::REPO_ROOT . '/front/forward.php');
        $this->assertFileDoesNotExist(self::REPO_ROOT . '/templates/forward.html.twig');
        $this->assertFileDoesNotExist(self::REPO_ROOT . '/js/forward.js');
    }

    // ---- security: no hardcoded secrets in compose path / setup / hook ----

    #[Test]
    public function security_no_hardcoded_secrets(): void
    {
        $pattern = '/(?:password|passwd|secret|api[_-]?token|auth[_-]?token)'
                 . '\s*[:=]\s*[\'\"][^\'\"[:space:]]+[\'\"]/i';
        $hits = '';
        foreach (self::COMPOSE_PATHS as $dir) {
            $hits .= self::grepRecursive($dir, $pattern);
        }
        foreach (['setup.php', 'hook.php'] as $file) {
            $hits .= self::grepFile(self::REPO_ROOT . '/' . $file, $pattern);
        }
        $this->assertSame(
            '',
            trim($hits),
            "Compose path / setup.php / hook.php must not contain hardcoded secrets:\n$hits",
        );
    }

    // ---- A16: i18n locale files + __() usage ----

    #[Test]
    public function a16_locale_files_present(): void
    {
        foreach ([
            'ticketmailer.pot',
            'ticketmailer.en.po',
            'ticketmailer.de.po',
            'en_GB.mo',
            'de_DE.mo',
        ] as $file) {
            $this->assertFileExists(
                self::REPO_ROOT . '/locales/' . $file,
                "locales/$file must exist (A16)",
            );
        }
    }

    #[Test]
    public function a16_translation_function_used_in_user_facing_paths(): void
    {
        $hits = '';
        foreach (['inc', 'front', 'templates'] as $dir) {
            $hits .= self::grepRecursive($dir, '/\b__\s*\(/');
        }
        $this->assertNotEmpty(
            $hits,
            'User-facing paths must wrap strings in GLPI __() (A16)',
        );
    }

    // ---- v2: glpi-ticket-email-client-v2 acceptance surfaces ----

    #[Test]
    public function v2_required_implementation_files_exist(): void
    {
        foreach ([
            'inc/replypolicy.class.php',
            'inc/mailboxguard.class.php',
            'inc/timeline.class.php',
            'inc/timelineaction.class.php',
            'front/download.php',
            'sql/update-1.1.0.sql',
        ] as $rel) {
            $this->assertFileExists(
                self::REPO_ROOT . '/' . $rel,
                "v2 requires $rel",
            );
        }
    }

    #[Test]
    public function v2_compose_defaults_observers_not_assignees_to_cc(): void
    {
        $action = (string) file_get_contents(self::REPO_ROOT . '/inc/timelineaction.class.php');
        $this->assertStringContainsString(
            'CommonITILActor::OBSERVER',
            $action,
            'v2 A2: observers default to CC',
        );
        $this->assertStringNotContainsString(
            'CommonITILActor::ASSIGN',
            $action,
            'v2 A2: assignees must not auto-default to CC',
        );
    }

    #[Test]
    public function v2_ticket_ui_exposes_inline_email_actions(): void
    {
        $action = (string) file_get_contents(self::REPO_ROOT . '/inc/timelineaction.class.php');
        $setup = (string) file_get_contents(self::REPO_ROOT . '/setup.php');
        $this->assertMatchesRegularExpression(
            '/E-Mail antworten|Email reply/',
            $action,
            'v2 A1: ticket UI must expose E-Mail antworten',
        );
        $this->assertStringContainsString(
            'timeline_answer_actions',
            $setup,
            'v2 A1: forms must use GLPI’s native timeline collapse extension point',
        );
        $this->assertStringContainsString(
            'timeline_actions',
            $setup,
            'v2 A1: actions must appear beside GLPI’s native Answer button',
        );
        $this->assertFileDoesNotExist(
            self::REPO_ROOT . '/inc/tickettab.class.php',
            'v2 A1: ticket must not retain a standalone email-reply tab',
        );
    }

    #[Test]
    public function v2_recipient_parser_reports_malformed_raw_tokens(): void
    {
        $src = (string) file_get_contents(self::REPO_ROOT . '/inc/recipients.class.php');
        $this->assertMatchesRegularExpression(
            '/invalid|malformed/',
            $src,
            'v2 A4: recipient parser must report malformed tokens',
        );
        // Structural: parseRaw (or equivalent) must surface invalids, not only drop them.
        $this->assertMatchesRegularExpression(
            '/function\s+(parseRaw|parse|validateRaw)\s*\(/',
            $src,
            'v2 A4: public parse API that returns malformed tokens is required',
        );
    }

    #[Test]
    public function v2_schema_has_timeline_and_mailbox_fields(): void
    {
        $install = (string) file_get_contents(self::REPO_ROOT . '/sql/install.sql');
        foreach (['followups_id', 'timeline_status', 'timeline_error', 'mailbox_override', 'mailbox_matches'] as $field) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($field, '/') . '\b/',
                $install,
                "install.sql must define $field",
            );
        }
        $this->assertMatchesRegularExpression(
            "/pending.*sent.*failed|pending.*failed.*sent/",
            $install,
            'status enum must include pending/sent/failed',
        );
        $this->assertMatchesRegularExpression(
            "/pending.*recorded.*failed|pending.*failed.*recorded/",
            $install,
            'timeline_status enum must include pending/recorded/failed',
        );
        $this->assertMatchesRegularExpression(
            '/reply.*polic|policy/',
            $install,
            'install must define reply policy table',
        );
    }

    #[Test]
    public function v2_timeline_uses_itilfollowup_with_disablenotif(): void
    {
        $src = (string) file_get_contents(self::REPO_ROOT . '/inc/timeline.class.php');
        $this->assertStringContainsString('ITILFollowup', $src);
        $this->assertStringContainsString('_disablenotif', $src);
        $this->assertStringContainsString('recipients_bcc', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/NotificationEvent::raiseEvent|Notification::raiseEvent|NotificationMailing::send/',
            $src,
        );
    }

    #[Test]
    public function v2_download_requires_ticket_read_and_safe_path(): void
    {
        $src = (string) file_get_contents(self::REPO_ROOT . '/front/download.php');
        $this->assertMatchesRegularExpression('/Ticket::canViewItem|canViewItem/', $src);
        $this->assertMatchesRegularExpression('/safeResolveUnder|realpath/', $src);
    }

    #[Test]
    public function v2_compose_omits_bcc_reader_visibility_warning(): void
    {
        $twig = (string) file_get_contents(self::REPO_ROOT . '/templates/compose.html.twig');
        $this->assertStringContainsString('BCC', $twig);
        $this->assertStringNotContainsString(
            'BCC addresses and attachments will be visible to every ticket reader.',
            $twig,
        );
        $this->assertStringContainsString('mailbox_override', $twig);
    }

    #[Test]
    public function v2_compose_supports_followup_templates(): void
    {
        $reply = (string) file_get_contents(self::REPO_ROOT . '/templates/compose.html.twig');
        $action = (string) file_get_contents(self::REPO_ROOT . '/inc/timelineaction.class.php');
        $js = (string) file_get_contents(self::REPO_ROOT . '/js/composer.js');
        $this->assertStringContainsString('followup_template_dropdown', $reply);
        $this->assertStringContainsString('itilfollowuptemplates_id', $action);
        $this->assertStringContainsString('$(document).on(', $js);
        $this->assertStringContainsString('applyFollowupTemplate', $js);
        $this->assertStringContainsString('X-Glpi-Csrf-Token', $js);
        $this->assertStringContainsString('tinymce.get', $js);
    }

    #[Test]
    public function v2_compose_submit_disables_duplicate_sends(): void
    {
        $js = (string) file_get_contents(self::REPO_ROOT . '/js/composer.js');
        $this->assertStringContainsString('ticketmailerSending', $js);
        $this->assertStringContainsString('spinner-border', $js);
        $this->assertStringContainsString('button.disabled = true', $js);
        $this->assertStringContainsString('cancel.disabled = true', $js);
        $this->assertStringContainsString("cancel.classList.add('disabled')", $js);
        $this->assertStringContainsString('showSendingOverlay();', $js);
        $this->assertStringContainsString('child.inert = true', $js);
        $this->assertStringContainsString(
            "['pointerdown', 'click', 'keydown', 'submit']",
            $js,
        );
    }

    #[Test]
    public function v2_send_returns_incomplete_outcomes_to_the_audit_detail(): void
    {
        $send = (string) file_get_contents(self::REPO_ROOT . '/front/send.php');
        $this->assertMatchesRegularExpression(
            '/timeline_recorded.*log_entry\.php/s',
            $send,
        );
    }

    #[Test]
    public function v2_audit_detail_shows_full_bcc_not_count_only(): void
    {
        $twig = (string) file_get_contents(self::REPO_ROOT . '/templates/log_entry.html.twig');
        $this->assertStringContainsString('recipients_bcc', $twig);
        $this->assertDoesNotMatchRegularExpression(
            '/recipients_bcc_count|hidden recipient/',
            $twig,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/href="\{\{\s*a\.path/',
            $twig,
            'detail must not render raw attachment paths',
        );
    }

    #[Test]
    public function v2_reply_policy_modes_and_precedence_surface(): void
    {
        $src = (string) file_get_contents(self::REPO_ROOT . '/inc/replypolicy.class.php');
        $this->assertStringContainsString('entities_id', $src);
        $this->assertStringContainsString('profiles_id', $src);
        $this->assertMatchesRegularExpression(
            '/available.*promoted.*hide_native/',
            $src,
        );
        // hide_native must not be implemented via DOM/CSS-only suppression.
        $this->assertDoesNotMatchRegularExpression(
            '/display\s*:\s*none|querySelector.*reply|\.hide\(/',
            $src,
        );
    }


    #[Test]
    public function v2_compose_preferences_are_entity_scoped_and_safe(): void
    {
        $config = (string) file_get_contents(self::REPO_ROOT . '/inc/config.class.php');
        $reply = (string) file_get_contents(self::REPO_ROOT . '/templates/compose.html.twig');
        $action = (string) file_get_contents(self::REPO_ROOT . '/inc/timelineaction.class.php');
        $send = (string) file_get_contents(self::REPO_ROOT . '/front/send.php');
        $js = (string) file_get_contents(self::REPO_ROOT . '/js/composer.js');
        $this->assertStringContainsString('glpi_plugin_ticketmailer_configs', $config);
        $this->assertStringContainsString('signature_html', $config);
        $this->assertStringContainsString('set_waiting', $config);
        $this->assertStringContainsString('data-recipient-control', $reply);
        $this->assertStringContainsString('data-attachment-drop', $reply);
        $this->assertStringContainsString('addicon', $action);
        $this->assertStringContainsString('requesttypes_id', $send);
        $this->assertStringContainsString('is_itilfollowup', $send);
        $this->assertStringContainsString('Ticket::WAITING', $send);
        $this->assertStringContainsString('initRecipientControl', $js);
        $this->assertStringContainsString('dataTransfer.files', $js);
        $this->assertStringNotContainsString('ticketmailer-signature-sep', $action);
        $entrypoint = (string) file_get_contents(self::REPO_ROOT . '/docker/glpi/docker-entrypoint.sh');
        $this->assertStringContainsString("precedence ::ffff:0:0/96  100", $entrypoint);
    }

    #[Test]
    public function v2_timeline_actions_and_post_controllers_rely_on_glpi_bootstrap_csrf(): void
    {
        $config = (string) file_get_contents(self::REPO_ROOT . '/front/config.form.php');
        $send = (string) file_get_contents(self::REPO_ROOT . '/front/send.php');
        $reply = self::REPO_ROOT . '/inc/timelinereply.class.php';
        $this->assertStringContainsString("Html::hidden('_glpi_csrf_token'", $config);
        $this->assertStringNotContainsString('Session::checkCSRF($_POST)', $config);
        $this->assertStringNotContainsString('Session::checkCSRF($_POST)', $send);
        $this->assertFileExists($reply);
        $this->assertStringContainsString('renderReply', (string) file_get_contents($reply));
    }

    #[Test]
    public function v2_config_form_posts_and_redirects_to_plugin_controller_url(): void
    {
        $config = (string) file_get_contents(self::REPO_ROOT . '/front/config.form.php');

        $this->assertStringContainsString("'root_doc'", $config);
        $this->assertStringContainsString(
            '/plugins/ticketmailer/front/config.form.php',
            $config,
        );
        $this->assertStringNotContainsString('Plugin::getWebDir', $config);
        $this->assertStringContainsString('$config_url', $config);
        $this->assertStringNotContainsString('PHP_SELF', $config);
    }
    // ---- helpers --------------------------------------------------------


    private static function grepRecursive(string $subdir, string $pattern): string
    {
        $root = self::REPO_ROOT . '/' . $subdir;
        if (!is_dir($root)) {
            return '';
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        $out = '';
        foreach ($iter as $file) {
            if (!$file->isFile() || str_ends_with($file->getFilename(), '.swp')) {
                continue;
            }
            $out .= self::grepFile($file->getPathname(), $pattern);
        }
        return $out;
    }

    private static function grepFile(string $path, string $pattern): string
    {
        if (!is_file($path)) {
            return '';
        }
        $contents = (string) file_get_contents($path);
        if (preg_match_all($pattern, $contents, $matches)) {
            return $path . ': ' . implode(' | ', $matches[0]) . "\n";
        }
        return '';
    }
}
