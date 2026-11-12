<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SenderLabelsTest extends TestCase
{
    public function test_sender_hierarchy_defaults_and_saved_settings(): void
    {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/sender-label-regressions.php') . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame(['PASS sender entity hierarchy labels'], $output);
    }
}
