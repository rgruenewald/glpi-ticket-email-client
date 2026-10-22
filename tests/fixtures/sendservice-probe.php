<?php
// Runs in a fresh PHP process so GLPI test doubles cannot leak between suites.
define('ERROR', 1);
function __(string $text, string $domain = ''): string { return $text; }
class Ticket {
    public static int $status = 1;
    public static array $updates = [];
    public static bool $allowed = true;
    public const SOLVED = 5;
    public const WAITING = 4;
    public function getField(string $name): int { return $name === 'status' ? self::$status : 42; }
    public function update(array $input): bool {
        self::$updates[] = $input;
        if (in_array($GLOBALS['argv'][1], ['status-update-failure','status-update-noop'], true)) return $GLOBALS['argv'][1] === 'status-update-noop';
        self::$status = $input['status'];
        return true;
    }
    public function getFromDB(int $id): bool { return true; }
}
class Session {
    public static array $messages = [];
    public static function getLoginUserID(): int { return 7; }
    public static function addMessageAfterRedirect(string $text, ...$args): void { self::$messages[] = $text; }
}
class PluginTicketmailerAudit {
    public static array $calls = [];
    public static function createIntent(...$args): int {
        self::$calls[] = ['intent', $args[1]];
        if (($GLOBALS['argv'][1] ?? '') === 'audit-failure') { throw new RuntimeException('audit unavailable'); }
        return 8;
    }
    public static function markSmtpResult(...$args): bool { self::$calls[] = ['smtp', $args[1]]; return true; }
    public static function markTimelineResult(...$args): bool { self::$calls[] = ['timeline', $args[1]]; return true; }
}
class PluginTicketmailerMailer {
    public static int $attempts = 0;
    public static function send(array $payload): array {
        self::$attempts++;
        if ($GLOBALS['argv'][1] === 'status-changed-during-smtp') Ticket::$status = 5;
        if ($GLOBALS['argv'][1] === 'rights-changed-during-smtp') Ticket::$allowed = false;
        return ['status'=>($GLOBALS['argv'][1] ?? '') === 'smtp-failure' ? 'failed' : 'sent', 'error'=>null, 'msg_id'=>null];
    }
}
class PluginTicketmailerTimeline {
    public static array $authors = [];
    public static function canChangeStatus(Ticket $ticket, int $target): bool { return Ticket::$allowed; }
    public static function validateSubmission(Ticket $ticket, int $expected, bool $waiting, bool $solved): void {
        if (($GLOBALS['argv'][1] ?? '') === 'denied-followup') throw new InvalidArgumentException('denied');
    }
    public static function recordOutbound(array $payload): array {
        self::$authors[] = $payload['users_id'];
        return ['ok'=>($GLOBALS['argv'][1] ?? '') !== 'timeline-failure', 'followups_id'=>9, 'error'=>'timeline unavailable'];
    }
}
class PluginTicketmailerProactive {
    public static array $phases = [];
    public static function saveState(array $context, array $state): void { self::$phases[] = $state['phase']; }
}
require __DIR__ . '/../../inc/sendservice.class.php';
try {
    $result = PluginTicketmailerSendservice::deliver(new Ticket(), [
        'subject'=>'Example', 'to'=>['customer@example.test'], 'cc'=>[], 'bcc'=>[], 'body_html'=>'<p>Example</p>', 'body_text'=>'Example',
    ], [], [], false, [], false, 0, str_contains($argv[1], 'status-') || str_contains($argv[1], 'during-smtp'), false, ['token'=>'synthetic']);
} catch (RuntimeException | InvalidArgumentException $e) { $result = ['error'=>$e->getMessage()]; }
echo json_encode(['result'=>$result, 'attempts'=>PluginTicketmailerMailer::$attempts,
    'audit'=>PluginTicketmailerAudit::$calls, 'authors'=>PluginTicketmailerTimeline::$authors,
    'phases'=>PluginTicketmailerProactive::$phases, 'updates'=>Ticket::$updates,
    'ticket_status'=>Ticket::$status, 'messages'=>Session::$messages], JSON_THROW_ON_ERROR);
