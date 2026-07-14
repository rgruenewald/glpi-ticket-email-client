<?php
/**
 * inc/audit.class.php — durable send intent + outcome (v2).
 * pending → sent/failed for SMTP; timeline_status pending/recorded/failed.
 */
class PluginTicketmailerAudit
{
    /**
     * Create durable intent before SMTP. status+timeline_status = pending.
     *
     * @param list<string> $recipients_to
     * @param list<string> $recipients_cc
     * @param list<string> $recipients_bcc
     * @param list<array<string, mixed>> $attachments
     * @param list<array<string, mixed>> $inline_images
     * @param list<string> $mailbox_matches
     */
    public static function createIntent(
        int $tickets_id,
        int $users_id,
        string $subject,
        ?string $body_html,
        ?string $body_text,
        array $recipients_to,
        array $recipients_cc,
        array $recipients_bcc,
        array $attachments,
        array $inline_images,
        bool $mailbox_override,
        array $mailbox_matches,
    ): int {
        global $DB;
        $DB->insert(
            'glpi_plugin_ticketmailer_logs',
            [
                'tickets_id'       => $tickets_id,
                'users_id'         => $users_id,
                'sent_at'          => date('Y-m-d H:i:s'),
                'subject'          => $subject,
                'body_html'        => $body_html,
                'body_text'        => $body_text,
                'recipients_to'    => self::encode($recipients_to),
                'recipients_cc'    => self::encode($recipients_cc),
                'recipients_bcc'   => self::encode($recipients_bcc),
                'attachments'      => self::encode($attachments),
                'inline_images'    => self::encode($inline_images),
                'status'           => 'pending',
                'error_message'    => null,
                'remote_msg_id'    => null,
                'followups_id'     => null,
                'timeline_status'  => 'pending',
                'timeline_error'   => null,
                'mailbox_override' => $mailbox_override ? 1 : 0,
                'mailbox_matches'  => self::encode($mailbox_matches),
            ],
        );
        return (int) $DB->insertid();
    }

    public static function markSmtpResult(
        int $id,
        string $status,
        ?string $error_message,
        ?string $remote_msg_id,
    ): void {
        global $DB;
        $DB->update(
            'glpi_plugin_ticketmailer_logs',
            [
                'status'        => $status,
                'error_message' => $error_message,
                'remote_msg_id' => $remote_msg_id,
                'sent_at'       => date('Y-m-d H:i:s'),
            ],
            ['id' => $id],
        );
    }

    public static function markTimelineResult(
        int $id,
        string $timeline_status,
        ?int $followups_id,
        ?string $timeline_error,
    ): void {
        global $DB;
        $DB->update(
            'glpi_plugin_ticketmailer_logs',
            [
                'timeline_status' => $timeline_status,
                'followups_id'    => $followups_id,
                'timeline_error'  => $timeline_error,
            ],
            ['id' => $id],
        );
    }

    /**
     * Legacy one-shot record (kept for any remaining callers).
     *
     * @param list<string> $recipients_to
     * @param list<string> $recipients_cc
     * @param list<string> $recipients_bcc
     * @param list<array<string, mixed>> $attachments
     * @param list<array<string, mixed>> $inline_images
     */
    public static function record(
        int $tickets_id,
        int $users_id,
        string $subject,
        ?string $body_html,
        ?string $body_text,
        array $recipients_to,
        array $recipients_cc,
        array $recipients_bcc,
        array $attachments,
        array $inline_images,
        string $status,
        ?string $error_message,
        ?string $remote_msg_id,
        string $timeline_status = 'pending',
        ?int $followups_id = null,
        ?string $timeline_error = null,
        bool $mailbox_override = false,
        array $mailbox_matches = [],
    ): int {
        $id = self::createIntent(
            $tickets_id,
            $users_id,
            $subject,
            $body_html,
            $body_text,
            $recipients_to,
            $recipients_cc,
            $recipients_bcc,
            $attachments,
            $inline_images,
            $mailbox_override,
            $mailbox_matches,
        );
        self::markSmtpResult($id, $status, $error_message, $remote_msg_id);
        if ($timeline_status !== 'pending' || $followups_id !== null || $timeline_error !== null) {
            self::markTimelineResult($id, $timeline_status, $followups_id, $timeline_error);
        }
        return $id;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        global $DB;
        foreach ($DB->request('glpi_plugin_ticketmailer_logs', ['id' => $id]) as $r) {
            return $r;
        }
        return null;
    }

    /** @return list<array<string, mixed>> */
    public static function listForTicket(int $tickets_id): array
    {
        global $DB;
        $rows = [];
        foreach (
            $DB->request(
                'glpi_plugin_ticketmailer_logs',
                ['tickets_id' => $tickets_id, 'ORDER' => 'sent_at DESC'],
            ) as $r
        ) {
            $rows[] = $r;
        }
        return $rows;
    }

    /** @return list<mixed> */
    public static function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function encode(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
