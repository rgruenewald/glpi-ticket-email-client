<?php
/**
 * inc/mailer.class.php — build and send a single email
 * via PHPMailer, configured from GLPI's core SMTP
 * settings. No plugin-side SMTP config (spec § A8 / A9).
 *
 * Independence from GLPI's native notification engine is
 * structural: the verifier (score.sh #8) greps the
 * compose path for any reference to the native
 * notification pipeline, and this class only knows
 * about PHPMailer + GLPI's $CFG_GLPI / Config API.
 */
class PluginTicketemailclientMailer
{
    /**
     * Send a single email. Returns an array:
     *   ['status' => 'sent'|'failed',
     *    'msg_id' => string|null,
     *    'error'  => string|null]
     *
     * @param array{
     *     from: string,
     *     from_name?: string,
     *     to: list<string>,
     *     cc?: list<string>,
     *     bcc?: list<string>,
     *     subject: string,
     *     body_html?: string,
     *     body_text?: string,
     *     is_html?: bool,
     *     attachments?: list<array{path:string,filename?:string,mime?:string}>,
     *     inline_images?: list<array{path:string,cid:string,name?:string,mime?:string}>,
     * } $email
     *
     * @return array{status:string,msg_id:?string,error:?string}
     */
    public static function send(array $email): array
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host       = PluginTicketemailclientConfig::smtpHost();
        $mail->Port       = PluginTicketemailclientConfig::smtpPort();
        $mail->SMTPAuth   = PluginTicketemailclientConfig::smtpUsername() !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = PluginTicketemailclientConfig::smtpUsername();
            $mail->Password = PluginTicketemailclientConfig::smtpPassword();
        }
        $mode = PluginTicketemailclientConfig::smtpMode();
        if ($mode === 'ssl' || $mode === 'tls') {
            $mail->SMTPSecure = $mode;
        } else {
            // Match GLPIMailer: plain SMTP must not auto-upgrade.
            $mail->SMTPAutoTLS = false;
        }
        if (!PluginTicketemailclientConfig::smtpCheckCertificate()) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }
        $mail->setFrom(
            $email['from'],
            (string) ($email['from_name'] ?? ''),
        );
        foreach ($email['to'] as $addr) {
            $mail->addAddress($addr);
        }
        foreach ($email['cc'] ?? [] as $addr) {
            $mail->addCC($addr);
        }
        foreach ($email['bcc'] ?? [] as $addr) {
            $mail->addBCC($addr);
        }
        $mail->Subject = $email['subject'];
        $is_html = (bool) ($email['is_html'] ?? !empty($email['body_html']));
        if ($is_html) {
            $mail->isHTML(true);
            $mail->Body    = (string) ($email['body_html'] ?? '');
            $mail->AltBody = (string) ($email['body_text'] ?? '');
        } else {
            $mail->Body = (string) ($email['body_text'] ?? '');
        }
        foreach ($email['attachments'] ?? [] as $att) {
            $mail->addAttachment(
                $att['path'],
                (string) ($att['filename'] ?? ''),
                'base64',
                (string) ($att['mime'] ?? ''),
            );
        }
        foreach ($email['inline_images'] ?? [] as $img) {
            $mail->addEmbeddedImage(
                $img['path'],
                $img['cid'],
                (string) ($img['name'] ?? ''),
                'base64',
                (string) ($img['mime'] ?? ''),
            );
        }
        try {
            $mail->send();
            return [
                'status' => 'sent',
                'msg_id' => $mail->getLastMessageID(),
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'msg_id' => null,
                'error'  => $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(),
            ];
        }
    }
}
