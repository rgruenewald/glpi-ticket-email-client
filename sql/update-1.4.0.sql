-- Autocomplete privacy preference: display recipient email addresses by default.
ALTER TABLE glpi_plugin_ticketemailclient_configs
    ADD COLUMN recipient_autocomplete_show_email TINYINT NOT NULL DEFAULT 1
    AFTER open_reply_on_ticket;
