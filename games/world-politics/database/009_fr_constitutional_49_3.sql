-- WP21 France constitutional process, headless and game-owned only.
-- No migration of the generic Aeternum engine is authorized.
CREATE TABLE IF NOT EXISTS wp_fr_constitutional_states (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state_json LONGTEXT NOT NULL CHECK(JSON_VALID(state_json)),
    state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sitting_calendar_required TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_constitutional_receipts (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    response_json LONGTEXT NOT NULL CHECK(JSON_VALID(response_json)),
    PRIMARY KEY(world_id,actor_id,receipt_key),
    CONSTRAINT wp_fr_const_receipt_fk FOREIGN KEY(world_id)
        REFERENCES wp_fr_constitutional_states(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_constitutional_journal (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL,
    actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    operation VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result_json LONGTEXT NOT NULL CHECK(JSON_VALID(result_json)),
    PRIMARY KEY(world_id,revision),
    CONSTRAINT wp_fr_const_journal_fk FOREIGN KEY(world_id)
        REFERENCES wp_fr_constitutional_states(world_id)
) ENGINE=InnoDB;
