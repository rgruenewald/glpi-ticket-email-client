<?php
/**
 * setup.php — plugin registration and metadata for the
 * `ticketemailclient` plugin. Sourced by GLPI when the plugin
 * is loaded. Lifecycle callbacks live in `hook.php`.
 */

define('PLUGIN_TICKETEMAILCLIENT_VERSION', '2.0.0');
define('PLUGIN_TICKETEMAILCLIENT_MIN_GLPI', '11.0.0');
define('PLUGIN_TICKETEMAILCLIENT_MAX_GLPI', '11.99.99');

/**
 * Plugin version. Required by GLPI's plugin loader.
 *
 * @return array<string, mixed>
 */
function plugin_version_ticketemailclient(): array
{
    return [
        'version'         => PLUGIN_TICKETEMAILCLIENT_VERSION,
        'name'            => __('GLPI Ticket Email Client', 'ticketemailclient'),
        'author'          => 'Ronny Gruenewald',
        'license'         => 'GPL-3.0-or-later',
        'minGlpiVersion'  => PLUGIN_TICKETEMAILCLIENT_MIN_GLPI,
        'requirements'    => [
            'glpi' => [
                'min' => PLUGIN_TICKETEMAILCLIENT_MIN_GLPI,
                'max' => PLUGIN_TICKETEMAILCLIENT_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Runtime init. GLPI includes setup.php inside a closure,
 * so hooks MUST be registered here (global $PLUGIN_HOOKS),
 * not at file top-level.
 */
function plugin_init_ticketemailclient(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['ticketemailclient'] = true;
    $PLUGIN_HOOKS['add_css']['ticketemailclient'] = 'css/ticketemailclient.css';
    $PLUGIN_HOOKS['add_javascript']['ticketemailclient'] = [
        'js/composer.js',
        'js/ticket-timeline.js',
    ];

    PluginTicketemailclientConfig::applyTimelineOrderForCurrentTicket();

    $PLUGIN_HOOKS['timeline_answer_actions']['ticketemailclient']
        = 'PluginTicketemailclientTimelineAction::getAnswerActions';
    $PLUGIN_HOOKS['timeline_actions']['ticketemailclient']
        = 'PluginTicketemailclientTimelineAction::displayActions';
    $PLUGIN_HOOKS['config_page']['ticketemailclient'] = 'front/config.form.php';
    $PLUGIN_HOOKS['post_init']['ticketemailclient'] = 'plugin_ticketemailclient_post_init';
    $PLUGIN_HOOKS['item_purge']['ticketemailclient'] = [
        'Ticket' => 'plugin_ticketemailclient_item_purge',
    ];
}

/**
 * Plugin prerequisites. Required by GLPI's plugin loader.
 */
function plugin_ticketemailclient_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_TICKETEMAILCLIENT_MIN_GLPI, 'lt')) {
        echo sprintf(
            'This plugin requires GLPI >= %1$s (you have %2$s).',
            PLUGIN_TICKETEMAILCLIENT_MIN_GLPI,
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
function plugin_ticketemailclient_check_config($verbose = false): bool
{
    return true;
}
