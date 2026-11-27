-- Explicit world-level dataset release. Off by default; no retroactive collection.
CREATE TABLE wp_game_dataset_consent (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
 FOREIGN KEY(world_id) REFERENCES wp_alpha_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE wp_game_dataset_outbox (
 seq BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 committed_day DATE NOT NULL,
 envelope_json LONGTEXT NOT NULL CHECK(JSON_VALID(envelope_json)),
 UNIQUE KEY world_day(world_id,committed_day),
 FOREIGN KEY(world_id) REFERENCES wp_game_dataset_consent(world_id)
) ENGINE=InnoDB;
