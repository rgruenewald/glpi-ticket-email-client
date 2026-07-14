<?php
/**
 * inc/plugin.class.php — central plugin descriptor.
 * The constants and hooks are declared in setup.php;
 * this class only carries a single self-reference for
 * callers that need an instance.
 */
class PluginTicketemailclient extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return _sn('GLPI Ticket Email Client', 'GLPI Ticket Email Clients', $nb, 'ticketemailclient');
    }

    public static function getPluginName(): string
    {
        return 'ticketemailclient';
    }
}
