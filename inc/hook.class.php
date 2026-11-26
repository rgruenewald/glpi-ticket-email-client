<?php
/**
 * inc/hook.class.php — static helpers for the lifecycle
 * hooks defined in hook.php. Kept separate so the hook
 * functions stay small and the helpers are unit-testable.
 */
class PluginTicketmailerHook
{
    public static function installSchema(): bool
    {
        global $DB;
        try {
            // GLPI loads the plugin's fixed, idempotent greenfield DDL.
            return $DB->runFile(__DIR__ . '/../sql/install.sql') && self::migrateSchema();
        } catch (Throwable $error) {
            Toolbox::logInFile('php-errors', 'ticketmailer install: ' . $error->getMessage());
            return false;
        }
    }

    public static function migrateSchema(): bool
    {
        global $DB;
        $logs = 'glpi_plugin_ticketmailer_logs';
        $configs = 'glpi_plugin_ticketmailer_configs';
        $migration = new Migration(PLUGIN_TICKETMAILER_VERSION);
        if (!$DB->fieldExists($logs, 'followups_id')) {
            $migration->changeField($logs, 'status', 'status', "ENUM('pending','sent','failed') NOT NULL", ['nodefault' => true]);
        }
        $migration->addField($logs, 'followups_id', 'INT NULL');
        $migration->addField($logs, 'timeline_error', 'TEXT NULL');
        $migration->addField($logs, 'mailbox_override', 'bool');
        $migration->addField($logs, 'mailbox_matches', 'MEDIUMTEXT NULL');
        $migration->addField($logs, 'new_conversation', 'bool');
        $migration->addKey($logs, 'followups_id', 'idx_followup');
        $backfill = $migration->addField($logs, 'timeline_status', "ENUM('pending','recorded','failed') NOT NULL DEFAULT 'pending'");
        $migration->addField($configs, 'timeline_newest_first', 'bool', ['value' => 1]);
        $migration->addField($configs, 'open_reply_on_ticket', 'bool', ['value' => 1]);
        $migration->addField($configs, 'recipient_autocomplete_show_email', 'bool', ['value' => 1]);
        $migration->addField($configs, 'notificationtemplates_id', 'INT NULL');
        $migration->addField($configs, 'hide_native_answer', 'bool');
        $migration->executeMigration();
        if ($backfill) {
            return $DB->update($logs, ['timeline_status' => 'recorded'], ['status' => ['sent', 'failed']]);
        }
        return true;
    }

    public static function uninstallSchema(): bool
    {
        $migration = new Migration(PLUGIN_TICKETMAILER_VERSION);
        try {
            foreach (['logs', 'configs', 'reply_policies'] as $table) {
                $migration->dropTable('glpi_plugin_ticketmailer_' . $table);
            }
            return true;
        } catch (Throwable $error) {
            Toolbox::logInFile('php-errors', 'ticketmailer uninstall: ' . $error->getMessage());
            return false;
        }
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
