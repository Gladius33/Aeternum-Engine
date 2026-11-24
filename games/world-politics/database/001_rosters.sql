-- World Politics WP03 — isolated game schema, not Aeternum Engine core migration.
-- MySQL/MariaDB 11.4; perform only on a dedicated game database after backup.
CREATE TABLE IF NOT EXISTS wp_world_rosters (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state_json LONGTEXT NOT NULL CHECK (JSON_VALID(state_json)),
    state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY(world_id,country_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_command_receipts (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL,
    result_json LONGTEXT NOT NULL CHECK(JSON_VALID(result_json)),
    PRIMARY KEY(world_id,country_code,actor_id,receipt_key),
    CONSTRAINT wp_receipt_world_fk FOREIGN KEY(world_id,country_code)
        REFERENCES wp_world_rosters(world_id,country_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_world_events (
    world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL,
    actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    operation VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result_json LONGTEXT NOT NULL CHECK(JSON_VALID(result_json)),
    PRIMARY KEY(world_id,country_code,revision),
    CONSTRAINT wp_event_world_fk FOREIGN KEY(world_id,country_code)
        REFERENCES wp_world_rosters(world_id,country_code)
) ENGINE=InnoDB;
