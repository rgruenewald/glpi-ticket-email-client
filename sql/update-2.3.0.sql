ALTER TABLE glpi_plugin_ticketmailer_configs
    ADD COLUMN hide_native_answer TINYINT NOT NULL DEFAULT 0;
