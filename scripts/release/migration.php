<?php
// Disposable integration fixture. GLPI owns the connection and migration API.
require '/var/www/html/vendor/autoload.php';
(new Glpi\Kernel\Kernel())->boot();
global $DB;
require_once GLPI_ROOT . '/plugins/ticketmailer/inc/hook.class.php';
$tables = ['glpi_plugin_ticketmailer_logs', 'glpi_plugin_ticketmailer_configs'];
function schemaSnapshot($DB, array $tables): array {
    $result = [];
    foreach ($tables as $table) {
        $columns = $DB->doQuery('SHOW COLUMNS FROM ' . $table);
        while ($row = $DB->fetchAssoc($columns)) {
            $result[$table][$row['Field']] = [$row['Type'], $row['Null'], $row['Default'], $row['Extra']];
        }
        ksort($result[$table]);
    }
    return $result;
}
$baseline = '/tmp/ticketmailer-schema.json';
if (in_array('--snapshot', $argv, true)) {
    file_put_contents($baseline, json_encode(schemaSnapshot($DB, $tables), JSON_THROW_ON_ERROR));
    exit(0);
}
if (!PluginTicketmailerHook::migrateSchema('/var/www/html/plugins/ticketmailer/sql')) {
    throw new RuntimeException('Migration failed');
}
$expected = json_decode(file_get_contents($baseline), true, 512, JSON_THROW_ON_ERROR);
if ($expected !== schemaSnapshot($DB, $tables)) {
    throw new RuntimeException('Migrated schema differs from fresh installation');
}
echo "Migration and idempotent re-run match fresh schema.\n";
