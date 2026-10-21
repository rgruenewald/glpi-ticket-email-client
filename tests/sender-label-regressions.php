<?php
declare(strict_types=1);
// Standalone GLPI doubles keep host classes out of the PHPUnit process.
const CREATE = 1;
const UPDATE = 2;
function __($text, $domain = null) { return $text; }
class Session {
    public static function getCurrentInterface() { return 'central'; }
    public static function haveRight($name, $right) { return true; }
    public static function getLoginUserID() { return 7; }
}
class Ticket { public const READMY = 4; public const READALL = 8; }
class Profile_User {
    public static function getEntitiesForProfileByUser(...$args) { return [0, 4, 5]; }
}
class Config {
    public static array $settings = [];
    public static function getConfigurationValues($context, $keys) { return self::$settings; }
    public static function setConfigurationValues($context, $values) { self::$settings = $values; }
    public static function getEmailSender($id) { return ['email' => $id === 5 ? '' : 'sender@example.test']; }
}
class Dropdown {
    public static function getDropdownName($table, $id) { return $id === 0 ? 'Root' : 'Root > _ > Bund.IT-Betrieb'; }
}
class Entity {
    private int $id;
    public function getFromDB($id) { $this->id = $id; return true; }
    public function getField($key) { return $this->id === 0 ? 'Root' : 'Bund.IT-Betrieb'; }
}
require_once __DIR__ . '/../inc/config.class.php';
require_once __DIR__ . '/../inc/proactive.class.php';
function expectSame($expected, $actual): void {
    if ($expected !== $actual) { throw new RuntimeException(var_export([$expected, $actual], true)); }
}
$full = PluginTicketmailerProactive::senderOptions();
expectSame([
    ['id' => 0, 'label' => 'Root <sender@example.test>'],
    ['id' => 4, 'label' => 'Root > _ > Bund.IT-Betrieb <sender@example.test>'],
], $full);
PluginTicketmailerConfig::saveInterfaceSettings([]);
expectSame(false, PluginTicketmailerConfig::interfaceSettings()['show_sender_entity_hierarchy']);
expectSame([
    ['id' => 0, 'label' => 'Root <sender@example.test>'],
    ['id' => 4, 'label' => 'Bund.IT-Betrieb <sender@example.test>'],
], PluginTicketmailerProactive::senderOptions());
PluginTicketmailerConfig::saveInterfaceSettings(['show_sender_entity_hierarchy' => 1]);
expectSame($full, PluginTicketmailerProactive::senderOptions());
echo "PASS sender entity hierarchy labels
";
