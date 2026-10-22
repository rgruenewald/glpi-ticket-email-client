<?php
use PHPUnit\Framework\TestCase;
final class TicketStatusTest extends TestCase
{
    private function probe(string $scenario): array
    {
        $p = proc_open([PHP_BINARY, __DIR__.'/fixtures/ticket-status-probe.php', $scenario], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($p), $out.$err);
        return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }
    public function testPreflightRejectsDeniedFollowupsStaleFormsAndForbiddenTransitions(): void
    {
        foreach (['denied-followup','stale','denied-lifecycle','missing-required','template-required','no-update','solved-transition','both'] as $case) {
            self::assertNotNull($this->probe($case)['error'], $case);
        }
    }
    public function testPreserveDoesNotRequireTicketUpdatePermission(): void
    {
        self::assertNull($this->probe('success')['error']);
        self::assertNull($this->probe('followup-only')['error']);
        $r=$this->probe('solved-preserve');
        self::assertNull($r['error']); self::assertFalse($r['waiting']); self::assertFalse($r['solved']);
        self::assertFalse($this->probe('waiting-preserve')['waiting']);
    }
    public function testNativePendingZeroAndTemplatesCannotReopenOrChangeNoteVisibility(): void
    {
        foreach (['note-open','note-waiting','note-solved'] as $case) {
            $r=$this->probe($case); $input=$r['input'];
            self::assertIsArray($input, $case); self::assertSame([], $r['messages']);
            self::assertSame(1, $input['is_private']); self::assertSame(1, $input['_no_reopen']);
            foreach (['pending','_status','_reopen','add_reopen'] as $key) self::assertArrayNotHasKey($key, $input);
        }
    }
    public function testNativeNoteRejectsStaleAndForgedStatusActionsBeforeCreation(): void
    {
        foreach (['note-stale','note-solved-forged-waiting'] as $case) {
            $r=$this->probe($case); self::assertFalse($r['input']); self::assertNotEmpty($r['messages']);
        }
    }
}
