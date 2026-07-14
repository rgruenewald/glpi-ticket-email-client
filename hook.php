<?php
/**
 * hook.php — lifecycle hooks for ticketemailclient v2.
 */

/**
 * Install / upgrade. Fresh install uses install.sql.
 * Existing v1 DBs gain v2 columns via update-1.1.0.sql when needed.
 */
function plugin_ticketemailclient_install(): bool
{
    $sql_file = __DIR__ . '/sql/install.sql';
    if (!is_file($sql_file)
        || !PluginTicketemailclientHook::runSqlScript((string) file_get_contents($sql_file))
    ) {
        return false;
    }

    return PluginTicketemailclientHook::migrateLegacy(
        GLPI_PLUGIN_DOC_DIR . '/ticketmailer',
        GLPI_PLUGIN_DOC_DIR . '/ticketemailclient',
    );
}

function plugin_ticketemailclient_uninstall(): bool
{
    $sql_file = __DIR__ . '/sql/uninstall.sql';
    if (is_file($sql_file)) {
        PluginTicketemailclientHook::runSqlScript((string) file_get_contents($sql_file));
    }
    $files_root = GLPI_PLUGIN_DOC_DIR . '/ticketemailclient';
    if (is_dir($files_root)) {
        PluginTicketemailclientHook::rmdirRecursive($files_root);
    }
    return true;
}

function plugin_ticketemailclient_post_init(): void
{
    Plugin::registerClass(
        'PluginTicketemailclientLogTab',
        ['addtabon' => ['Ticket']],
    );
}

function plugin_ticketemailclient_item_purge(Ticket $ticket): void
{
    global $DB;
    $tickets_id = (int) $ticket->getField('id');
    $DB->delete(
        'glpi_plugin_ticketemailclient_logs',
        ['tickets_id' => $tickets_id],
    );
}
