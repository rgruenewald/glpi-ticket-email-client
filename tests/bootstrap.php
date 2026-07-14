<?php
// tests/bootstrap.php — PHPUnit bootstrap for the ticketmailer plugin.
// Loads Composer autoloader if present; otherwise tests rely on
// the project files only and do not need any class autoloading.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
