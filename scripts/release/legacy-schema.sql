-- Only for the disposable release-check database, never production.
ALTER TABLE glpi_plugin_ticketmailer_logs
  DROP COLUMN followups_id, DROP COLUMN timeline_status,
  DROP COLUMN timeline_error, DROP COLUMN mailbox_override,
  DROP COLUMN mailbox_matches, DROP COLUMN new_conversation;
ALTER TABLE glpi_plugin_ticketmailer_configs DROP COLUMN hide_native_answer;
