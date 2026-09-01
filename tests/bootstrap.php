<?php
// tests/bootstrap.php — PHPUnit bootstrap for the ticketmailer plugin.
// Loads Composer autoloader if present; otherwise tests rely on
// the project files only and do not need any class autoloading.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

if (!class_exists('CommonGLPI')) {
    class CommonGLPI
    {
        public static function createTabEntry(string $text, int $count = 0, ?string $itemtype = null, ?string $icon = null): array|string
        {
            return $text;
        }

        public function getType(): string
        {
            return static::class;
        }
    }
}
