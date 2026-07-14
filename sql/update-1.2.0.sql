-- sql/update-1.2.0.sql — per-entity compose preferences.
CREATE TABLE IF NOT EXISTS glpi_plugin_ticketmailer_configs (
    entities_id    INT          NOT NULL,
    subject_prefix VARCHAR(255) NOT NULL DEFAULT '[#%d]',
    signature_html MEDIUMTEXT   NULL,
    set_waiting    TINYINT      NOT NULL DEFAULT 1,
    PRIMARY KEY (entities_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
