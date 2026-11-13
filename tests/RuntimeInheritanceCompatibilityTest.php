<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeInheritanceCompatibilityTest extends TestCase
{
    public static function parentDefinitions(): array
    {
        return ['GLPI 11 untyped' => [''], 'GLPI 12 typed' => ['string']];
    }

    #[Test]
    #[DataProvider('parentDefinitions')]
    public function descriptors_load_without_changing_parent_rights(string $type): void
    {
        $source = <<<'PHP'
<?php
class CommonGLPI { public static %s $rightname = ''; }
class CommonDBTM extends CommonGLPI {}
class Ticket extends CommonGLPI {
    public function getFromDB($id): bool { return $id > 0; }
    public function canViewItem(): bool { return false; }
    public function getField($name): int { return 1; }
}
require %s;
require %s;
$_GET['id'] = 1;
echo json_encode([
    'parent_right' => CommonGLPI::$rightname,
    'view_denied' => !PluginTicketmailerLogTab::canView(),
    'tab_denied' => (new PluginTicketmailerLogTab())->getTabNameForItem(new Ticket()) === '',
    'content_denied' => !PluginTicketmailerLogTab::displayTabContentForItem(new Ticket()),
]);
PHP;
        $path = tempnam(sys_get_temp_dir(), 'ticketmailer-inheritance-');
        try {
            file_put_contents($path, sprintf($source, $type,
                var_export(__DIR__ . '/../inc/plugin.class.php', true),
                var_export(__DIR__ . '/../inc/logtab.class.php', true)));
            $process = proc_open([PHP_BINARY, $path], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $error);
            $this->assertSame([
                'parent_right' => '', 'view_denied' => true,
                'tab_denied' => true, 'content_denied' => true,
            ], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
        } finally {
            unlink($path);
        }
    }
}
