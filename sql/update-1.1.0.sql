-- sql/update-1.1.0.sql — migrate audit log + add reply policy.

ALTER TABLE glpi_plugin_ticketmailer_logs
    MODIFY COLUMN status ENUM('pending','sent','failed') NOT NULL;

ALTER TABLE glpi_plugin_ticketmailer_logs
    ADD COLUMN followups_id     INT NULL AFTER remote_msg_id,
    ADD COLUMN timeline_status  ENUM('pending','recorded','failed') NOT NULL DEFAULT 'pending' AFTER followups_id,
    ADD COLUMN timeline_error   TEXT NULL AFTER timeline_status,
    ADD COLUMN mailbox_override TINYINT NOT NULL DEFAULT 0 AFTER timeline_error,
    ADD COLUMN mailbox_matches  MEDIUMTEXT NULL AFTER mailbox_override;

-- Historical rows had no timeline integration.
UPDATE glpi_plugin_ticketmailer_logs
   SET timeline_status = 'recorded'
 WHERE timeline_status = 'pending'
   AND status IN ('sent', 'failed');

CREATE TABLE IF NOT EXISTS glpi_plugin_ticketmailer_reply_policies (
    id           INT          NOT NULL AUTO_INCREMENT,
    entities_id  INT          NOT NULL DEFAULT 0,
    profiles_id  INT          NULL,
    mode         ENUM('available','promoted','hide_native') NOT NULL DEFAULT 'available',
    PRIMARY KEY (id),
    UNIQUE KEY uq_entity_profile (entities_id, profiles_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
