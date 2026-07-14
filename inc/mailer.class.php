<?php
/**
 * inc/mailer.class.php — build and send exactly one email through
 * GLPI's direct `GLPIMailer` transport. It uses GLPI's core SMTP
 * configuration but never invokes the notification delivery pipeline.
 */
class PluginTicketemailclientMailer
{
    /**
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
        try {
            $mailer = new GLPIMailer();
            $message = $mailer->getEmail();
            $message->from(new \Symfony\Component\Mime\Address(
                $email['from'],
                (string) ($email['from_name'] ?? ''),
            ));
            foreach ($email['to'] as $address) {
                $message->addTo($address);
            }
            foreach ($email['cc'] ?? [] as $address) {
                $message->addCc($address);
            }
            foreach ($email['bcc'] ?? [] as $address) {
                $message->addBcc($address);
            }
            $message->subject($email['subject']);

            $isHtml = (bool) ($email['is_html'] ?? !empty($email['body_html']));
            if ($isHtml) {
                $message->html((string) ($email['body_html'] ?? ''));
                $message->text((string) ($email['body_text'] ?? ''));
            } else {
                $message->text((string) ($email['body_text'] ?? ''));
            }

            foreach ($email['attachments'] ?? [] as $attachment) {
                $message->attachFromPath(
                    $attachment['path'],
                    (string) ($attachment['filename'] ?? ''),
                    (string) ($attachment['mime'] ?? ''),
                );
            }
            foreach ($email['inline_images'] ?? [] as $image) {
                $part = \Symfony\Component\Mime\Part\DataPart::fromPath(
                    $image['path'],
                    (string) ($image['name'] ?? ''),
                    (string) ($image['mime'] ?? ''),
                );
                $part->asInline()->setContentId($image['cid']);
                $message->addPart($part);
            }

            if (!$mailer->send()) {
                return [
                    'status' => 'failed',
                    'msg_id' => null,
                    'error' => $mailer->getError() ?? __('Unable to send email.', 'ticketemailclient'),
                ];
            }

            $messageId = $message->getHeaders()->get('Message-Id');
            return [
                'status' => 'sent',
                'msg_id' => $messageId?->getBodyAsString(),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'msg_id' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
