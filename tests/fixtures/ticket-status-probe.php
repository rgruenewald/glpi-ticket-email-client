<?php
// Fresh process: exercise real status guards and note hooks without shared test doubles.
define('CREATE', 4);
define('ERROR', 1);
function __(string $text, string $domain = ''): string { return $text; }
class Session {
    public static array $messages = [];
    public static function haveAccessToEntity(int $id): bool { return true; }
    public static function addMessageAfterRedirect(string $message, ...$args): void { self::$messages[] = $message; }
}
class Ticket {
    public const WAITING = 4, SOLVED = 5;
    public array $fields = ['locations_id'=>0];
    public function getITILTemplateFromInput(array $input): ?object {
        return (object)['mandatory' => ($GLOBALS['argv'][1] === 'template-required' ? ['locations_id'=>83] : [])];
    }
    public static int $status = 1;
    public static bool $followup = true, $update = true, $lifecycle = true, $required = true;
    public function getField(string $name): int { return $name === 'status' ? self::$status : 42; }
    public function getFromDB(int $id): bool { return true; }
    public function getID(): int { return 42; }
    public function canViewItem(): bool { return true; }
    public function isNotSolved(): bool { return self::$status < 5; }
    public static function canUpdate(): bool { return self::$update; }
    public function canUpdateItem(): bool { return self::$update; }
    public function canSolve(): bool { return self::$update && self::$lifecycle; }
    public function checkRequiredFieldsFilled(): bool { return self::$required; }
    public static function isAllowedStatus($old, $new): bool { return self::$lifecycle; }
}
class ITILFollowup {
    public array $fields = [];
    public array|false $input = [];
    public function getEmpty(): void { $this->fields = []; }
    public function can($id, $right, array &$input): bool { return Ticket::$followup; }
}
require __DIR__ . '/../../inc/timeline.class.php';
require __DIR__ . '/../../hook.php';
$scenario = $argv[1];
$ticket = new Ticket();
if (str_contains($scenario, 'solved')) Ticket::$status = 5;
if (str_contains($scenario, 'waiting')) Ticket::$status = 4;
if ($scenario === 'denied-followup') Ticket::$followup = false;
if ($scenario === 'denied-lifecycle') Ticket::$lifecycle = false;
if ($scenario === 'missing-required') Ticket::$required = false;
if (in_array($scenario, ['no-update','followup-only'], true)) Ticket::$update = false;
if (str_starts_with($scenario, 'note-')) {
    $f = new ITILFollowup();
    $f->input = ['itemtype'=>'Ticket', 'items_id'=>42, 'content'=>'Synthetic note', 'is_private'=>0,
        '_ticketmailer_internal_note'=>'1', '_ticketmailer_expected_status'=>Ticket::$status,
        'pending'=>0, '_reopen'=>1, 'add_reopen'=>1, '_status'=>1];
    if ($scenario === 'note-stale') $f->input['_ticketmailer_expected_status'] = 5;
    if ($scenario === 'note-solved-forged-waiting') $f->input['pending'] = 1;
    plugin_ticketmailer_force_internal_note_private($f);
    if ($f->input !== false) {
        // Simulate a native template reintroducing status and visibility fields.
        $f->input['pending'] = 1;
        $f->input['is_private'] = 0;
        $f->input['_status'] = 1;
        plugin_ticketmailer_prepare_internal_note($f);
    }
    $input = $f->input;
    if (is_array($input)) unset($input['_job']);
    echo json_encode(['input'=>$input, 'messages'=>Session::$messages], JSON_THROW_ON_ERROR);
    exit;
}
$error = null;
try {
    PluginTicketmailerTimeline::validateSubmission($ticket, $scenario === 'stale' ? 5 : Ticket::$status,
        in_array($scenario, ['denied-lifecycle','no-update','solved-transition','both'], true),
        in_array($scenario, ['missing-required','template-required','both'], true));
} catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
echo json_encode(['error'=>$error, 'waiting'=>PluginTicketmailerTimeline::canChangeStatus($ticket, 4),
    'solved'=>PluginTicketmailerTimeline::canChangeStatus($ticket, 5)], JSON_THROW_ON_ERROR);
