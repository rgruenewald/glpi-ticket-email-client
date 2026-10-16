<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InterfaceLifecycleTest extends TestCase
{
    public function test_uninstall_removes_owned_settings_and_reinstall_restores_defaults(): void
    {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/interface-regressions.php') . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame(['PASS uninstall/reinstall interface lifecycle'], $output);
    }
}
