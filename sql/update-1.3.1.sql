-- sql/update-1.3.1.sql — reply-form auto-open preference.
ALTER TABLE glpi_plugin_ticketmailer_configs
    ADD COLUMN open_reply_on_ticket TINYINT NOT NULL DEFAULT 1
    AFTER timeline_newest_first;
