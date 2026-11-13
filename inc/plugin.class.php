<?php
/**
 * inc/plugin.class.php — central plugin descriptor.
 * The constants and hooks are declared in setup.php;
 * this class only carries a single self-reference for
 * callers that need an instance.
 */
class PluginTicketmailer extends CommonDBTM
{
    // Metadata-only descriptor. Configuration access is checked explicitly
    // in front/config.form.php; do not redeclare the version-dependent
    // CommonGLPI::$rightname property.

    public static function getTypeName($nb = 0): string
    {
        return _sn('GLPI Ticket Email Client', 'GLPI Ticket Email Clients', $nb, 'ticketmailer');
    }

    public static function getPluginName(): string
    {
        return 'ticketmailer';
    }

    public static function getWebDir(bool $full = true): string
    {
        return PluginTicketmailerConfig::getWebDir($full);
    }
}
