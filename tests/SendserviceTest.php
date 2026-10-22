<?php
use PHPUnit\Framework\TestCase;

final class SendserviceTest extends TestCase
{
    private function probe(string $scenario): array
    {
        $command = [PHP_BINARY, __DIR__ . '/fixtures/sendservice-probe.php', $scenario];
        $process = proc_open($command, [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $errors);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testDeliveryPreservesTheAuthenticatedAuthorAndOrder(): void
    {
        $result = $this->probe('success');
        self::assertSame(1, $result['attempts']);
        self::assertSame([7], $result['authors']);
        self::assertSame([['intent',7], ['smtp','sent'], ['timeline','recorded']], $result['audit']);
        self::assertSame(['sending','sent'], $result['phases']);
        self::assertTrue($result['result']['timeline_recorded']);
    }

    public function testDeniedFollowupStopsBeforeAuditAndSmtp(): void
    {
        $result = $this->probe('denied-followup');
        self::assertSame(0, $result['attempts']);
        self::assertSame([], $result['audit']);
        self::assertSame([], $result['authors']);
    }

    public function testNoSmtpWithoutDurableAudit(): void
    {
        $result = $this->probe('audit-failure');
        self::assertSame(0, $result['attempts']);
        self::assertSame([], $result['authors']);
    }

    public function testSmtpFailureHasNoSuccessfulFollowup(): void
    {
        $result = $this->probe('smtp-failure');
        self::assertSame(1, $result['attempts']);
        self::assertSame([], $result['authors']);
        self::assertFalse($result['result']['timeline_recorded']);
        self::assertSame(['sending','failed'], $result['phases']);
    }

    public function testFailedOrIneffectiveStatusUpdateWarnsWithoutResending(): void
    {
        foreach (['status-update-failure', 'status-update-noop'] as $scenario) {
            $r = $this->probe($scenario);
            self::assertSame(1, $r['attempts']);
            self::assertSame('sent', $r['result']['status']);
            self::assertTrue($r['result']['timeline_recorded']);
            self::assertSame([7], $r['authors']);
            self::assertSame([['intent',7], ['smtp','sent'], ['timeline','recorded']], $r['audit']);
            self::assertCount(1, $r['updates']);
            self::assertSame(1, $r['ticket_status']);
            self::assertCount(1, $r['messages']);
            self::assertStringContainsString('Do not send the email again.', $r['messages'][0]);
        }
    }

    public function testChangesDuringSmtpPreventStatusOverwrite(): void
    {
        foreach (['status-changed-during-smtp'=>5, 'rights-changed-during-smtp'=>1] as $scenario=>$status) {
            $r = $this->probe($scenario);
            self::assertSame(1, $r['attempts']);
            self::assertTrue($r['result']['timeline_recorded']);
            self::assertSame('sent', $r['result']['status']);
            self::assertSame([], $r['updates']);
            self::assertSame($status, $r['ticket_status']);
            self::assertCount(1, $r['messages']);
            self::assertStringContainsString('Do not send the email again.', $r['messages'][0]);
        }
        $r = $this->probe('status-success');
        self::assertSame(4, $r['ticket_status']);
        self::assertCount(1, $r['updates']);
        self::assertSame([], $r['messages']);
        self::assertSame(1, $r['attempts']);
    }

    public function testTimelineFailureNeverRetriesSmtp(): void
    {
        $result = $this->probe('timeline-failure');
        self::assertSame(1, $result['attempts']);
        self::assertSame('sent', $result['result']['status']);
        self::assertFalse($result['result']['timeline_recorded']);
        self::assertSame(['timeline','failed'], $result['audit'][2]);
    }
}
