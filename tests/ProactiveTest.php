<?php
use PHPUnit\Framework\TestCase;
if (!defined('GLPI_PLUGIN_DOC_DIR')) { define('GLPI_PLUGIN_DOC_DIR', sys_get_temp_dir() . '/ticketmailer-unit-' . getmypid()); }
if (!class_exists('User')) { class User { public static function getUsersIdByEmails(string $email, array $conditions): array { return $email === 'known@example.test' ? [12] : []; } } }
require_once __DIR__ . '/../inc/proactive.class.php';

final class ProactiveTest extends TestCase
{
    public function testRecipientsBecomeActorsWithoutBcc(): void
    {
        $input = PluginTicketmailerProactive::ticketInput(4, 'Maintenance', ['a@example.test'], ['b@example.test']);
        self::assertSame('', $input['content']);
        self::assertTrue($input['_disablenotif']);
        self::assertSame(4, $input['entities_id']);
        self::assertSame('a@example.test', $input['_actors']['requester'][0]['alternative_email']);
        self::assertSame('b@example.test', $input['_actors']['observer'][0]['alternative_email']);
        self::assertSame([], $input['_actors']['assign']);
        self::assertSame(12, PluginTicketmailerProactive::ticketInput(0, 'Known', ['known@example.test'], [])['_actors']['requester'][0]['items_id']);
        self::assertArrayNotHasKey('bcc', $input['_actors']);
    }

    public function testContextIsBoundToUserAndExpires(): void
    {
        $context = PluginTicketmailerProactive::createContext(4, 7);
        self::assertSame(4, PluginTicketmailerProactive::context($context['token'], 7)['entities_id']);
        self::assertNull(PluginTicketmailerProactive::context($context['token'], 8));
        self::assertNull(PluginTicketmailerProactive::context('../escape', 7));
        $_SESSION['ticketmailer_proactive'][$context['token']]['expires'] = time() - 1;
        self::assertNull(PluginTicketmailerProactive::context($context['token'], 7));
    }

    public function testStateSurvivesReopeningAndDoesNotResend(): void
    {
        $context = PluginTicketmailerProactive::createContext(0, 7);
        $lock = PluginTicketmailerProactive::lock($context);
        PluginTicketmailerProactive::saveState($context, ['phase'=>'sending', 'tickets_id'=>42]);
        self::assertSame('sending', PluginTicketmailerProactive::state($context)['phase']);
        self::assertSame(42, PluginTicketmailerProactive::state($context)['tickets_id']);
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    public function testAuthenticatedSenderIsExplicitlyTheTicketCreator(): void
    {
        $input = PluginTicketmailerProactive::ticketInput(0, 'External', ['external@example.test'], []);
        self::assertSame(Session::getLoginUserID(), $input['users_id_recipient'] ?? null);
    }

    public function testExpiredUploadsAreRemovedWithoutTheirOriginalSession(): void
    {
        $context = PluginTicketmailerProactive::createContext(0, 1);
        $dir = PluginTicketmailerProactive::directory($context);
        mkdir($dir . '/uploads', 0700);
        file_put_contents($dir . '/uploads/proof.txt', 'private');
        touch($dir, time() - PluginTicketmailerProactive::TTL - 60);
        unset($_SESSION['ticketmailer_proactive']);
        PluginTicketmailerProactive::cleanup();
        self::assertDirectoryDoesNotExist($dir);
    }

    public function testCleanupSkipsLockedContexts(): void
    {
        $context = PluginTicketmailerProactive::createContext(0, 1);
        $lock = PluginTicketmailerProactive::lock($context);
        $dir = PluginTicketmailerProactive::directory($context);
        touch($dir, time() - PluginTicketmailerProactive::TTL - 60);
        clearstatcache();
        PluginTicketmailerProactive::cleanup();
        self::assertDirectoryExists($dir);
        flock($lock, LOCK_UN);
        fclose($lock);
        PluginTicketmailerProactive::cleanup();
        self::assertDirectoryDoesNotExist($dir);
    }

    public function testCreationRequiresVisibilityBeforeAnyTicketExists(): void
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/proactive-rights-probe.php'],
            [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $errors);
        self::assertSame([false, true, true], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }
}
