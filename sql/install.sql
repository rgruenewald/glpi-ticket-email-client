-- sql/install.sql — v2 schema for ticketmailer.
-- Idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS glpi_plugin_ticketmailer_logs (
    id                BIGINT       NOT NULL AUTO_INCREMENT,
    tickets_id        INT          NOT NULL,
    users_id          INT          NOT NULL,
    sent_at           DATETIME     NOT NULL,
    subject           VARCHAR(255) NOT NULL,
    body_html         MEDIUMTEXT,
    body_text         MEDIUMTEXT,
    recipients_to     MEDIUMTEXT   NOT NULL,
    recipients_cc     MEDIUMTEXT,
    recipients_bcc    MEDIUMTEXT,
    attachments       MEDIUMTEXT,
    inline_images     MEDIUMTEXT,
    status            ENUM('pending','sent','failed') NOT NULL,
    error_message     TEXT,
    remote_msg_id     VARCHAR(255),
    followups_id      INT          NULL,
    timeline_status   ENUM('pending','recorded','failed') NOT NULL DEFAULT 'pending',
    timeline_error    TEXT         NULL,
    mailbox_override  TINYINT      NOT NULL DEFAULT 0,
    mailbox_matches   MEDIUMTEXT   NULL,
    PRIMARY KEY (id),
    INDEX idx_ticket_sent (tickets_id, sent_at),
    INDEX idx_user        (users_id),
    INDEX idx_followup    (followups_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Minimal reply policy: entity + optional profile → mode.
CREATE TABLE IF NOT EXISTS glpi_plugin_ticketmailer_reply_policies (
    id           INT          NOT NULL AUTO_INCREMENT,
    entities_id  INT          NOT NULL DEFAULT 0,
    profiles_id  INT          NULL,
    mode         ENUM('available','promoted','hide_native') NOT NULL DEFAULT 'available',
    PRIMARY KEY (id),
    UNIQUE KEY uq_entity_profile (entities_id, profiles_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-entity compose preferences. Global defaults use entities_id = 0.
CREATE TABLE IF NOT EXISTS glpi_plugin_ticketmailer_configs (
    entities_id              INT          NOT NULL,
    subject_prefix           VARCHAR(255) NOT NULL DEFAULT '[##ticket.id##]',
    signature_html           MEDIUMTEXT   NULL,
    set_waiting              TINYINT      NOT NULL DEFAULT 1,
    timeline_newest_first    TINYINT      NOT NULL DEFAULT 1,
    open_reply_on_ticket     TINYINT      NOT NULL DEFAULT 1,
    recipient_autocomplete_show_email TINYINT      NOT NULL DEFAULT 1,
    PRIMARY KEY (entities_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
