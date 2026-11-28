-- New managed worlds pin a separate normalized fiscal scenario. Existing worlds are unchanged.
CREATE TABLE wp_national_fiscal_worlds (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 edition VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 committed_day DATE NOT NULL,
 config_json LONGTEXT NOT NULL,
 config_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state_json LONGTEXT NOT NULL,
 state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 FOREIGN KEY(world_id) REFERENCES wp_universes(world_id)
) ENGINE=InnoDB;
