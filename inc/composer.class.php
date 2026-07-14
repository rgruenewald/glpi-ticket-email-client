<?php
/**
 * inc/composer.class.php — build an email payload from
 * the compose form. Produces both HTML and a plain-text
 * alternative (spec § A5). HTML→text extraction lives
 * here per the spec assumption "HTML→text extraction is
 * a server-side concern in composer.class.php".
 */
class PluginTicketemailclientComposer
{
    /**
     * @param list<string> $recipients_to
     * @param list<string> $recipients_cc
     * @param list<string> $recipients_bcc
     * @return array<string, mixed>
     */
    public static function build(
        int $tickets_id,
        int $users_id,
        array $recipients_to,
        array $recipients_cc,
        array $recipients_bcc,
        string $subject,
        string $body_html,
        array $attachments = [],
        array $inline_images = [],
    ): array {
        $body_html = strtr(
            html_entity_decode($body_html, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ['\\r\\n' => "\n", '\\n' => "\n", '\\r' => "\n", "\u{00C2}\u{00A0}" => "\u{00A0}"],
        );
        $user = new User();
        $user->getFromDB($users_id);
        $from_email = (string) ($user->getDefaultEmail() ?? '');
        $from_name  = (string) ($user->getFriendlyName() ?? '');
        // User has no mail (common for default `glpi` account). Prefer
        // SMTP auth identity so providers accept MAIL FROM; then GLPI
        // notification From/Admin. Never example.org — undeliverable.
        if ($from_email === '' || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $smtp_user = PluginTicketemailclientConfig::smtpUsername();
            if ($smtp_user !== '' && filter_var($smtp_user, FILTER_VALIDATE_EMAIL)) {
                $from_email = $smtp_user;
            } else {
                $sender = Config::getEmailSender();
                $from_email = (string) ($sender['email'] ?? '');
                if ($from_name === '' && !empty($sender['name'])) {
                    $from_name = (string) $sender['name'];
                }
            }
        }

        return [
            'from'        => $from_email,
            'from_name'   => $from_name,
            'to'          => $recipients_to,
            'cc'          => $recipients_cc,
            'bcc'         => $recipients_bcc,
            'subject'     => $subject,
            'body_html'   => $body_html,
            'body_text'   => self::htmlToText($body_html),
            'is_html'     => true,
            'attachments' => $attachments,
            'inline_images' => $inline_images,
        ];
    }

    /**
     * Best-effort HTML → plain text conversion. Strips
     * tags, decodes common entities, normalises
     * whitespace. Good enough for an AltBody; not a
     * full HTML renderer.
     */
    public static function htmlToText(string $html): string
    {
        $text = preg_replace(
            '/<style\b[^>]*>.*?<\/style>/is',
            '',
            $html,
        ) ?? $html;
        $text = preg_replace(
            '/<script\b[^>]*>.*?<\/script>/is',
            '',
            $text,
        ) ?? $text;
        // Block-level closing tags get a newline.
        $text = preg_replace(
            '/<\/(p|div|br|tr|li|h[1-6])>/i',
            "\n",
            $text,
        ) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }
}
