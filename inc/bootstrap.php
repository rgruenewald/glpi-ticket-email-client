<?php
/**
 * Bootstrap GLPI for plugin front and AJAX entry points.
 *
 * GLPI 11 no longer initializes its legacy global APIs from
 * `inc/includes.php`; direct plugin controllers must boot the kernel.
 */
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!class_exists('Session') || !isset($GLOBALS['DB'])) {
    // Direct PHP aliases make GLPI derive the plugin directory as base path.
    $script_filename = $_SERVER['SCRIPT_FILENAME'] ?? null;
    $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/public/index.php';
    try {
        $kernel = new \Glpi\Kernel\Kernel();
        $kernel->boot();
    } finally {
        if ($script_filename === null) {
            unset($_SERVER['SCRIPT_FILENAME']);
        } else {
            $_SERVER['SCRIPT_FILENAME'] = $script_filename;
        }
    }
}
// Direct plugin aliases bypass GLPI's normal request listener.
Session::setPath();
Session::start();
