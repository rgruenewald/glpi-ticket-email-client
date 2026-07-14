<?php
/**
 * hook.php — lifecycle hooks for ticketmailer v2.
 */

/**
 * Install / upgrade. Fresh install uses install.sql.
 * Existing v1 DBs gain v2 columns via update-1.1.0.sql when needed.
 */
function plugin_ticketmailer_install(): bool
{
    $sql_file = __DIR__ . '/sql/install.sql';
    if (!is_file($sql_file)
        || !PluginTicketmailerHook::runSqlScript((string) file_get_contents($sql_file))
    ) {
        return false;
    }

    return PluginTicketmailerHook::migrateSchema(__DIR__ . '/sql');
}

function plugin_ticketmailer_uninstall(): bool
{
    $sql_file = __DIR__ . '/sql/uninstall.sql';
    if (is_file($sql_file)) {
        PluginTicketmailerHook::runSqlScript((string) file_get_contents($sql_file));
    }
    $files_root = GLPI_PLUGIN_DOC_DIR . '/ticketmailer';
    if (is_dir($files_root)) {
        PluginTicketmailerHook::rmdirRecursive($files_root);
    }
    return true;
}

function plugin_ticketmailer_post_init(): void
{
    Plugin::registerClass(
        'PluginTicketmailerLogTab',
        ['addtabon' => ['Ticket']],
    );
}

function plugin_ticketmailer_item_purge(Ticket $ticket): void
{
    global $DB;
    $tickets_id = (int) $ticket->getField('id');
    $DB->delete(
        'glpi_plugin_ticketmailer_logs',
        ['tickets_id' => $tickets_id],
    );
}
