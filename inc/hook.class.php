<?php
/**
 * inc/hook.class.php — static helpers for the lifecycle
 * hooks defined in hook.php. Kept separate so the hook
 * functions stay small and the helpers are unit-testable.
 */
class PluginTicketemailclientHook
{
    /**
     * Execute a multi-statement SQL script, ignoring
     * empty / comment-only statements. Used by the
     * install and uninstall hooks.
     */
    public static function runSqlScript(string $sql): bool
    {
        global $DB;
        $ok = true;
        foreach (self::splitStatements($sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            try {
                $DB->queryOrDie($stmt, $DB->error());
            } catch (\Throwable $e) {
                Toolbox::logError(
                    sprintf('ticketemailclient SQL error: %s', $e->getMessage()),
                );
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * Migrate the complete legacy namespace once, or fail without mutation
     * when a partial/interrupted deployment would make the outcome ambiguous.
     */
    public static function migrateLegacy(string $legacyRoot, string $targetRoot): bool
    {
        global $DB;

        $tables = [
            'glpi_plugin_ticketmailer_logs' => [
                'id', 'tickets_id', 'users_id', 'sent_at', 'subject', 'body_html',
                'body_text', 'recipients_to', 'recipients_cc', 'recipients_bcc',
                'attachments', 'inline_images', 'status', 'error_message',
                'remote_msg_id', 'followups_id', 'timeline_status', 'timeline_error',
                'mailbox_override', 'mailbox_matches',
            ],
            'glpi_plugin_ticketmailer_reply_policies' => [
                'id', 'entities_id', 'profiles_id', 'mode',
            ],
            'glpi_plugin_ticketmailer_configs' => [
                'entities_id', 'subject_prefix', 'signature_html', 'set_waiting',
                'timeline_newest_first', 'open_reply_on_ticket',
                'recipient_autocomplete_show_email',
            ],
        ];
        $targets = [
            'glpi_plugin_ticketmailer_logs' => 'glpi_plugin_ticketemailclient_logs',
            'glpi_plugin_ticketmailer_reply_policies' => 'glpi_plugin_ticketemailclient_reply_policies',
            'glpi_plugin_ticketmailer_configs' => 'glpi_plugin_ticketemailclient_configs',
        ];
        $present = array_filter(array_keys($tables), static fn (string $table): bool => $DB->tableExists($table));
        if ($present === []) {
            return !is_dir($legacyRoot);
        }
        if (count($present) !== count($tables) || is_dir($targetRoot)) {
            self::logMigrationFailure('legacy namespace is partial or destination exists');
            return false;
        }

        foreach ($targets as $source => $target) {
            if (count($DB->request(['FROM' => $target])) !== 0) {
                self::logMigrationFailure('legacy and target database data conflict');
                return false;
            }
        }
        foreach ($tables as $source => $columns) {
            foreach ($DB->request(['FROM' => $source]) as $row) {
                $copy = array_intersect_key($row, array_flip($columns));
                if (count($copy) !== count($columns) || !$DB->insert($targets[$source], $copy)) {
                    self::logMigrationFailure('database copy failed');
                    return false;
                }
            }
            if (count($DB->request(['FROM' => $source])) !== count($DB->request(['FROM' => $targets[$source]]))) {
                self::logMigrationFailure('database copy verification failed');
                return false;
            }
        }
        if (is_dir($legacyRoot) && !@rename($legacyRoot, $targetRoot)) {
            self::logMigrationFailure('document root move failed');
            return false;
        }
        foreach (array_keys($tables) as $source) {
            if (!$DB->dropTable($source)) {
                self::logMigrationFailure('legacy table cleanup failed');
                return false;
            }
        }
        return true;
    }

    private static function logMigrationFailure(string $reason): void
    {
        Toolbox::logError('ticketemailclient namespace migration failed: ' . $reason);
    }

    /**
     * Recursive removal of a directory and everything
     * below it. Best-effort: failures are swallowed
     * because uninstall must succeed even if some
     * uploaded files are locked.
     */
    public static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        return array_values(array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $s): bool => $s !== '',
        ));
    }

    /**
     * Resolve a user-supplied path under a trusted root
     * and reject anything that escapes the root via `..`
     * segments or symlinks. Returns the canonical path
     * or null if the path is unsafe / missing.
     *
     * Centralised so the security check is auditable
     * in one place (review-advice: HIGH).
     */
    public static function safeResolveUnder(string $root, string $relative): ?string
    {
        $root_real = realpath($root);
        if ($root_real === false) {
            return null;
        }
        $candidate = $root . '/' . ltrim($relative, '/');
        $resolved  = realpath($candidate);
        if ($resolved === false) {
            return null;
        }
        if (!str_starts_with($resolved, $root_real . DIRECTORY_SEPARATOR)
            && $resolved !== $root_real
        ) {
            return null;
        }
        return $resolved;
    }

    /**
     * Determine a MIME type from a server-controlled file path.
     *
     * Client-provided MIME values are never trusted for attachment metadata
     * or outbound message parts.
     */
    public static function trustedMime(string $path, string $fallback = 'application/octet-stream'): string
    {
        if (!is_file($path) || !function_exists('mime_content_type')) {
            return $fallback;
        }

        $mime = @mime_content_type($path);
        return is_string($mime) && $mime !== '' ? $mime : $fallback;
    }

}
