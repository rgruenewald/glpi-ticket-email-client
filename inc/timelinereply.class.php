<?php

final class PluginTicketemailclientTimelineReply
{
    public function renderForm(Ticket $ticket, bool $inline = true): string
    {
        return PluginTicketemailclientTimelineAction::renderReply($ticket, $inline);
    }
}
