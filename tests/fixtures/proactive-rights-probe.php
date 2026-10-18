<?php
const CREATE = 4;
const UPDATE = 2;
class Ticket { public const READMY = 1; public const READALL = 1024; public const READASSIGN = 4096; }
class Profile_User { public static function getEntitiesForProfileByUser(...$args): array { return [0]; } }
class Session {
    public static int $rights;
    public static function getLoginUserID(): int { return 1; }
    public static function getCurrentInterface(): string { return 'central'; }
    public static function haveRight(string $name, int $right): bool { return (self::$rights & $right) !== 0; }
    public static function haveAccessToEntity(int $entity): bool { return true; }
}
require __DIR__ . '/../../inc/proactive.class.php';
Session::$rights = CREATE | UPDATE | Ticket::READASSIGN;
$restricted = PluginTicketmailerProactive::canUse(0);
Session::$rights |= Ticket::READMY;
$own = PluginTicketmailerProactive::canUse(0);
Session::$rights = CREATE | UPDATE | Ticket::READALL;
echo json_encode([$restricted, $own, PluginTicketmailerProactive::canUse(0)], JSON_THROW_ON_ERROR);
