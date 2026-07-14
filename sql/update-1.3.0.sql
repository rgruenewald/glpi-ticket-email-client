-- sql/update-1.3.0.sql — timeline display preferences.
ALTER TABLE glpi_plugin_ticketemailclient_configs
    ADD COLUMN timeline_newest_first TINYINT NOT NULL DEFAULT 1,
    ADD COLUMN open_reply_on_ticket TINYINT NOT NULL DEFAULT 1;
