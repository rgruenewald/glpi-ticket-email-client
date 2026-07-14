<?php
/**
 * inc/timeline.class.php — create a standard ITILFollowup for a sent email.
 * Uses _disablenotif=1 so GLPI does not fan out a second notification.
 * Does NOT call NotificationEvent / NotificationMailing.
 */
class PluginTicketmailerTimeline
{
    /**
     * @param array{
     *   tickets_id:int,
     *   users_id:int,
     *   subject:string,
     *   body_html:string,
     *   body_text:?string,
     *   recipients_to:list<string>,
     *   recipients_cc:list<string>,
     *   recipients_bcc:list<string>,
     *   attachments:list<array<string,mixed>>,
     *   log_id:int,
     *   sent_at?:string,
     *   from?:string
     *   requesttypes_id?:int,
     * } $payload
     * @return array{ok:bool,followups_id:?int,error:?string}
     */
    public static function recordOutbound(array $payload): array
    {
        try {
            $content = self::buildContent($payload);
            $followup = new ITILFollowup();
            $input = [
                'itemtype'        => 'Ticket',
                'items_id'        => (int) $payload['tickets_id'],
                'users_id'        => (int) $payload['users_id'],
                'content'         => $content,
                'is_private'      => 0,
                '_disablenotif'   => 1,
                'requesttypes_id' => (int) ($payload['requesttypes_id'] ?? 0),
            ];
            $id = $followup->add($input);
            if (!$id) {
                return [
                    'ok'           => false,
                    'followups_id' => null,
                    'error'        => 'ITILFollowup::add failed',
                ];
            }
            return [
                'ok'           => true,
                'followups_id' => (int) $id,
                'error'        => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'           => false,
                'followups_id' => null,
                'error'        => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string,mixed> $payload
     */

    /**
     * Best-effort HTML sanitize for timeline storage.
     * Prefer GLPI RichText when available.
     */
    public static function sanitizeHtml(string $html): string
    {
        if (class_exists(\Glpi\RichText\RichText::class)
            && method_exists(\Glpi\RichText\RichText::class, 'getSafeHtml')
        ) {
            return (string) \Glpi\RichText\RichText::getSafeHtml($html);
        }
        // Fallback: strip scripts/styles only.
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        return $html;
    }

    public static function buildContent(array $payload): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $join = static function (array $list) use ($esc): string {
            return $list === [] ? '—' : $esc(implode(', ', $list));
        };

        $sent_at = (string) ($payload['sent_at'] ?? date('Y-m-d H:i:s'));
        $from    = (string) ($payload['from'] ?? '');
        $log_id  = (int) ($payload['log_id'] ?? 0);
        $web     = Plugin::getWebDir('ticketmailer');

        $lines   = [];
        $lines[] = '<div class="ticketmailer-followup">';
        $lines[] = '<p><strong>' . $esc(__('GLPI Ticket Email Client', 'ticketmailer')) . '</strong></p>';
        $lines[] = '<dl>';
        $lines[] = '<dt>' . $esc(__('Sent at', 'ticketmailer')) . '</dt><dd>' . $esc($sent_at) . '</dd>';
        if ($from !== '') {
            $lines[] = '<dt>' . $esc(__('From', 'ticketmailer')) . '</dt><dd>' . $esc($from) . '</dd>';
        }
        $lines[] = '<dt>' . $esc(__('To', 'ticketmailer')) . '</dt><dd>'
            . $join((array) ($payload['recipients_to'] ?? [])) . '</dd>';
        $lines[] = '<dt>' . $esc(__('CC', 'ticketmailer')) . '</dt><dd>'
            . $join((array) ($payload['recipients_cc'] ?? [])) . '</dd>';
        // Full BCC list intentionally visible to every ticket reader (A10).
        $lines[] = '<dt>' . $esc(__('BCC', 'ticketmailer')) . '</dt><dd>'
            . $join((array) ($payload['recipients_bcc'] ?? [])) . '</dd>';
        $lines[] = '<dt>' . $esc(__('Subject', 'ticketmailer')) . '</dt><dd>'
            . $esc((string) ($payload['subject'] ?? '')) . '</dd>';
        $lines[] = '</dl>';

        $body = self::sanitizeHtml((string) ($payload['body_html'] ?? ''));
        $lines[] = '<div class="ticketmailer-followup-body">' . $body . '</div>';

        $attachments = (array) ($payload['attachments'] ?? []);
        if ($attachments !== []) {
            $lines[] = '<p><strong>' . $esc(__('Attachments', 'ticketmailer')) . '</strong></p><ul>';
            foreach ($attachments as $a) {
                $id   = (string) ($a['id'] ?? '');
                $name = (string) ($a['filename'] ?? $id);
                if ($id === '' || $log_id <= 0) {
                    $lines[] = '<li>' . $esc($name) . '</li>';
                    continue;
                }
                $href = $web . '/front/download.php?log_id=' . $log_id
                    . '&attachment_id=' . rawurlencode($id);
                $open_attachment = $esc(__('Open attachment', 'ticketmailer'));
                $lines[] = '<li>' . $esc($name)
                    . ' <a class="ticketmailer-attachment-open" href="' . $esc($href)
                    . '" aria-label="' . $open_attachment . '" title="' . $open_attachment
                    . '"><i class="ti ti-external-link" aria-hidden="true"></i></a></li>';
            }
            $lines[] = '</ul>';
        }

        $lines[] = '</div>';
        return implode("\n", $lines);
    }
}
