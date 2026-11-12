<?php
/** Standalone probes: lifecycle uses doubles; template mode uses the host's real Twig. */
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (($argv[1] ?? '') === 'template') {
    require $argv[2];
    $source = (string) file_get_contents(__DIR__ . '/../templates/compose.html.twig');
    preg_match_all('/data-show-(?:answer-templates|followup-source)="[^"\n]*"/', $source, $matches);
    check(count($matches[0]) === 2, 'Both setting attributes must be tested');
    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(), ['strict_variables' => true]);
    $template = $twig->createTemplate(implode(' ', $matches[0]));
    foreach ([false, true, null] as $setting) {
        $context = $setting === null ? [] : ['show_answer_templates' => $setting, 'show_followup_source' => $setting];
        $value = $setting === false ? '0' : '1';
        check($template->render($context) === 'data-show-answer-templates="' . $value . '" data-show-followup-source="' . $value . '"',
            'False must remain disabled; true and undefined must remain enabled');
    }
    echo "PASS real Twig boolean switches\n";
    exit;
}

if (!function_exists('__')) {
    function __(string $message, string $domain = ''): string { return $message; }
}
class Config
{
    public static array $values = [];
    public static function getConfigurationValues(string $context, array $keys): array
    {
        return array_intersect_key(self::$values[$context] ?? [], array_flip($keys));
    }
    public static function setConfigurationValues(string $context, array $values): void
    {
        self::$values[$context] = $values + (self::$values[$context] ?? []);
    }
    public static function deleteConfigurationValues(string $context, array $keys): void
    {
        self::$values[$context] = array_diff_key(self::$values[$context] ?? [], array_flip($keys));
    }
}
class PluginTicketmailerHook
{
    public static function runSqlScript(string $sql): bool { return true; }
    public static function migrateSchema(string $path): bool { return true; }
    public static function rmdirRecursive(string $path): void { throw new RuntimeException('Unexpected file deletion'); }
}
define('GLPI_PLUGIN_DOC_DIR', sys_get_temp_dir() . '/ticketmailer-nonexistent-' . bin2hex(random_bytes(8)));
require __DIR__ . '/../inc/config.class.php';
require __DIR__ . '/../hook.php';
Config::$values = ['plugin:ticketmailer' => ['unrelated' => 'keep'], 'core' => ['unrelated' => 'keep']];
PluginTicketmailerConfig::saveInterfaceSettings([]);
check(!in_array(true, PluginTicketmailerConfig::interfaceSettings(), true), 'Settings must start disabled');
check(plugin_ticketmailer_uninstall(), 'Uninstall must succeed');
check(Config::$values['plugin:ticketmailer'] === ['unrelated' => 'keep'], 'Uninstall must remove only owned interface keys');
check(Config::$values['core'] === ['unrelated' => 'keep'], 'Core settings must remain intact');
check(plugin_ticketmailer_uninstall(), 'Repeated uninstall must succeed');
check(plugin_ticketmailer_install(), 'Reinstall must succeed');
check(!in_array(false, PluginTicketmailerConfig::interfaceSettings(), true), 'Reinstall must restore enabled defaults');
echo "PASS uninstall/reinstall interface lifecycle\n";
