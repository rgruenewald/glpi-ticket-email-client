<?php
/**
 * setup.php — plugin registration and metadata for the
 * `ticketmailer` plugin. Sourced by GLPI when the plugin
 * is loaded. Lifecycle callbacks live in `hook.php`.
 */

define('PLUGIN_TICKETMAILER_VERSION', '2.0.0');
define('PLUGIN_TICKETMAILER_MIN_GLPI', '11.0.0');
define('PLUGIN_TICKETMAILER_MAX_GLPI', '11.99.99');

/**
 * Plugin version. Required by GLPI's plugin loader.
 *
 * @return array<string, mixed>
 */
function plugin_version_ticketmailer(): array
{
    return [
        'version'         => PLUGIN_TICKETMAILER_VERSION,
        'name'            => __('GLPI Ticket Email Client', 'ticketmailer'),
        'author'          => 'Ronny Gruenewald',
        'license'         => 'GPL-3.0-or-later',
        'minGlpiVersion'  => PLUGIN_TICKETMAILER_MIN_GLPI,
        'requirements'    => [
            'glpi' => [
                'min' => PLUGIN_TICKETMAILER_MIN_GLPI,
                'max' => PLUGIN_TICKETMAILER_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Runtime init. GLPI includes setup.php inside a closure,
 * so hooks MUST be registered here (global $PLUGIN_HOOKS),
 * not at file top-level.
 */
function plugin_init_ticketmailer(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['ticketmailer'] = true;
    $PLUGIN_HOOKS['add_css']['ticketmailer'] = 'css/ticketmailer.css';
    $PLUGIN_HOOKS['add_javascript']['ticketmailer'] = [
        'js/composer.js',
        'js/ticket-timeline.js',
    ];

    PluginTicketmailerConfig::applyTimelineOrderForCurrentTicket();

    $PLUGIN_HOOKS['timeline_answer_actions']['ticketmailer']
        = 'PluginTicketmailerTimelineAction::getAnswerActions';
    $PLUGIN_HOOKS['timeline_actions']['ticketmailer']
        = 'PluginTicketmailerTimelineAction::displayActions';
    $PLUGIN_HOOKS['config_page']['ticketmailer'] = 'front/config.form.php';
    $PLUGIN_HOOKS['post_init']['ticketmailer'] = 'plugin_ticketmailer_post_init';
    $PLUGIN_HOOKS['item_purge']['ticketmailer'] = [
        'Ticket' => 'plugin_ticketmailer_item_purge',
    ];
}

/**
 * Plugin prerequisites. Required by GLPI's plugin loader.
 */
function plugin_ticketmailer_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_TICKETMAILER_MIN_GLPI, 'lt')) {
        echo sprintf(
            'This plugin requires GLPI >= %1$s (you have %2$s).',
            PLUGIN_TICKETMAILER_MIN_GLPI,
            GLPI_VERSION,
        );
        return false;
    }
    return true;
}

/**
 * Plugin config check. Required by GLPI's plugin loader.
 * Returns true unconditionally: this plugin reads SMTP
 * from GLPI's core config and ships no plugin-side form
 * (per spec § A8 / A9).
 */
function plugin_ticketmailer_check_config($verbose = false): bool
{
    return true;
}
