<?php

final class PluginTicketmailerTimelineReply
{
    public function renderForm(Ticket $ticket, bool $inline = true): string
    {
        return PluginTicketmailerTimelineAction::renderReply($ticket, $inline);
    }
}
