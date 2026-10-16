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

    public function testTimelineFailureNeverRetriesSmtp(): void
    {
        $result = $this->probe('timeline-failure');
        self::assertSame(1, $result['attempts']);
        self::assertSame('sent', $result['result']['status']);
        self::assertFalse($result['result']['timeline_recorded']);
        self::assertSame(['timeline','failed'], $result['audit'][2]);
    }
}
