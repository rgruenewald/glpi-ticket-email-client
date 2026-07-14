<?php
/**
 * Bootstrap GLPI for plugin front and AJAX entry points.
 *
 * GLPI 11 no longer initializes its legacy global APIs from
 * `inc/includes.php`; direct plugin controllers must boot the kernel.
 */
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!class_exists('Session') || !isset($GLOBALS['DB'])) {
    $kernel = new \Glpi\Kernel\Kernel();
    $kernel->boot();
}
// Direct plugin aliases bypass GLPI's normal request listener.
Session::setPath();
Session::start();
