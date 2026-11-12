-- sql/update-2.0.3.sql — native Ticket notification-template signatures.
-- Keep signature_html unchanged for the compatibility fallback.
ALTER TABLE glpi_plugin_ticketmailer_configs
    ADD COLUMN notificationtemplates_id INT NULL AFTER subject_prefix;