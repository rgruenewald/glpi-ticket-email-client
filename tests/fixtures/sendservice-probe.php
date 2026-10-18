<?php
// Runs in a fresh PHP process so GLPI test doubles cannot leak between suites.
class Ticket {
    public const SOLVED = 5;
    public const WAITING = 4;
    public function getField(string $name): int { return 42; }
    public function update(array $input): bool { return true; }
}
class Session { public static function getLoginUserID(): int { return 7; } }
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
        return ['status'=>($GLOBALS['argv'][1] ?? '') === 'smtp-failure' ? 'failed' : 'sent', 'error'=>null, 'msg_id'=>null];
    }
}
class PluginTicketmailerTimeline {
    public static array $authors = [];
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
    ], [], [], false, [], false, 0, false, false, ['token'=>'synthetic']);
} catch (RuntimeException $e) { $result = ['error'=>$e->getMessage()]; }
echo json_encode(['result'=>$result, 'attempts'=>PluginTicketmailerMailer::$attempts,
    'audit'=>PluginTicketmailerAudit::$calls, 'authors'=>PluginTicketmailerTimeline::$authors,
    'phases'=>PluginTicketmailerProactive::$phases], JSON_THROW_ON_ERROR);
