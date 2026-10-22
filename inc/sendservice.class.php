<?php
/** Shared irreversible delivery boundary for replies and new outbound tickets. */
class PluginTicketmailerSendservice
{
    public static function deliver(Ticket $ticket, array $payload, array $audit_attachments, array $audit_inline,
        bool $mailbox_override, array $mailbox_matches, bool $new_conversation, int $requesttypes_id,
        bool $set_waiting, bool $set_solved, ?array $proactive_context = null, ?int $expected_status = null): array
    {
        PluginTicketmailerTimeline::validateSubmission($ticket,
            $expected_status ?? (int) $ticket->getField('status'), $set_waiting, $set_solved);
        $tickets_id = (int) $ticket->getField('id');
        $original_status = (int) $ticket->getField('status');
        $subject = $payload['subject'];
        $recipients_to = $payload['to'];
        $recipients_cc = $payload['cc'];
        $recipients_bcc = $payload['bcc'];
        $log_id = PluginTicketmailerAudit::createIntent(
            $tickets_id,
            (int) Session::getLoginUserID(),
            $subject,
            $payload['body_html'],
            $payload['body_text'],
            $recipients_to,
            $recipients_cc,
            $recipients_bcc,
            $audit_attachments,
            $audit_inline,
            $mailbox_override && $mailbox_matches !== [],
            $mailbox_matches,
            $new_conversation,
        );
        
        if (!$log_id) { throw new RuntimeException('Could not persist audit intent; no email was sent.'); }
        if ($proactive_context !== null) {
            PluginTicketmailerProactive::saveState($proactive_context, ['phase'=>'sending', 'tickets_id'=>$tickets_id, 'log_id'=>$log_id]);
        }
        $result = PluginTicketmailerMailer::send($payload);
        PluginTicketmailerAudit::markSmtpResult(
            $log_id,
            $result['status'],
            $result['error'],
            $result['msg_id'],
        );
        
        if ($proactive_context !== null) {
            PluginTicketmailerProactive::saveState($proactive_context, ['phase'=>$result['status'], 'tickets_id'=>$tickets_id, 'log_id'=>$log_id]);
        }
        
        $timeline_recorded = false;
        $followups_id = 0;
        if ($result['status'] === 'sent') {
            $timeline = PluginTicketmailerTimeline::recordOutbound([
                'tickets_id'     => $tickets_id,
                'users_id'       => (int) Session::getLoginUserID(),
                'subject'        => $subject,
                'body_html'      => (string) $payload['body_html'],
                'body_text'      => (string) $payload['body_text'],
                'recipients_to'  => $recipients_to,
                'recipients_cc'  => $recipients_cc,
                'recipients_bcc' => $recipients_bcc,
                'attachments'    => $audit_attachments,
                'log_id'         => $log_id,
                'sent_at'        => date('Y-m-d H:i:s'),
                'from'           => (string) ($payload['from'] ?? ''),
                'requesttypes_id' => $requesttypes_id,
                'new_conversation' => $new_conversation,
            ]);
            if ($timeline['ok']) {
                PluginTicketmailerAudit::markTimelineResult(
                    $log_id,
                    'recorded',
                    $timeline['followups_id'],
                    null,
                );
                $timeline_recorded = true;
                $followups_id = (int) $timeline['followups_id'];
                if ($set_waiting || $set_solved) {
                    $target = $set_solved ? Ticket::SOLVED : Ticket::WAITING;
                    // Recheck after SMTP too: a concurrent edit must not be overwritten.
                    $status_updated = $ticket->getFromDB($tickets_id)
                        && (int) $ticket->getField('status') === $original_status
                        && PluginTicketmailerTimeline::canChangeStatus($ticket, $target)
                        && $ticket->update([
                            'id' => $tickets_id,
                            'status' => $target,
                            '_disablenotif' => 1,
                        ]) && $ticket->getFromDB($tickets_id) && (int) $ticket->getField('status') === $target;
                    if (!$status_updated) {
                        Session::addMessageAfterRedirect(
                            __('The email was sent, but the ticket status could not be changed. Check the current ticket status. Do not send the email again.', 'ticketmailer'),
                            false, ERROR,
                        );
                    }
                }
            } else {
                PluginTicketmailerAudit::markTimelineResult(
                    $log_id,
                    'failed',
                    null,
                    $timeline['error'],
                );
            }
        } else {
            // A7: SMTP failed — no successful-send followup; timeline_status stays pending.
        }
        
        return ['log_id'=>$log_id, 'timeline_recorded'=>$timeline_recorded, 'followups_id'=>$followups_id, 'status'=>$result['status']];
    }
}
