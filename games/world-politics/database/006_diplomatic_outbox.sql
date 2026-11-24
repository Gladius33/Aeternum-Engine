-- WP16: transactional sanctions outbox, owned by World Politics.
-- 003_diplomacy.sql must be applied first, on a dedicated game database.
CREATE TABLE IF NOT EXISTS wp_diplomatic_outbox (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_revision BIGINT UNSIGNED NOT NULL,
    source_actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_json LONGTEXT NOT NULL CHECK(JSON_VALID(payload_json)),
    payload_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('pending','delivered') NOT NULL DEFAULT 'pending',
    assumptions_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    economic_policy_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    economic_revision BIGINT UNSIGNED NULL,
    delivered_at DATETIME NULL,
    PRIMARY KEY(world_id,event_id),
    KEY wp_dip_outbox_pending (status,world_id,source_revision),
    CONSTRAINT wp_dip_outbox_world_fk FOREIGN KEY(world_id)
        REFERENCES wp_diplomatic_states(world_id),
    CONSTRAINT wp_dip_outbox_event_fk FOREIGN KEY(world_id,source_revision)
        REFERENCES wp_diplomatic_journal(world_id,revision)
) ENGINE=InnoDB;
