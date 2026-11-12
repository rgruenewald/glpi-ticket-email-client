-- sql/update-2.2.0.sql — explicit unlinked conversation intent.
ALTER TABLE glpi_plugin_ticketmailer_logs
    ADD COLUMN new_conversation TINYINT NOT NULL DEFAULT 0 AFTER mailbox_matches;
