<?php
/**
 * inc/hook.class.php — static helpers for the lifecycle
 * hooks defined in hook.php. Kept separate so the hook
 * functions stay small and the helpers are unit-testable.
 */
class PluginTicketmailerHook
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
                $DB->doQuery($stmt);
            } catch (\Throwable $e) {
                Toolbox::logInFile(
                    'php-errors',
                    sprintf('ticketmailer SQL error: %s', $e->getMessage()),
                );
                $ok = false;
            }
        }
        return $ok;
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
