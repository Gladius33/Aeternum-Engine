-- WP06: GAME-ONLY institutional persistence. Never apply to Aeternum main.
-- Requires game migration 001_rosters.sql and a dedicated test/deployment DB.
CREATE TABLE IF NOT EXISTS wp_institutional_states (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state_json LONGTEXT NOT NULL CHECK(JSON_VALID(state_json)),
    state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY(world_id,country_code),
    CONSTRAINT wp_inst_roster_fk FOREIGN KEY(world_id,country_code)
        REFERENCES wp_world_rosters(world_id,country_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_institutional_receipts (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    response_json LONGTEXT NOT NULL CHECK(JSON_VALID(response_json)),
    PRIMARY KEY(world_id,country_code,actor_id,receipt_key),
    CONSTRAINT wp_inst_receipt_fk FOREIGN KEY(world_id,country_code)
        REFERENCES wp_institutional_states(world_id,country_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_institutional_journal (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL,
    actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_role VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    operation VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result_json LONGTEXT NOT NULL CHECK(JSON_VALID(result_json)),
    PRIMARY KEY(world_id,country_code,revision),
    CONSTRAINT wp_inst_journal_fk FOREIGN KEY(world_id,country_code)
        REFERENCES wp_institutional_states(world_id,country_code)
) ENGINE=InnoDB;
