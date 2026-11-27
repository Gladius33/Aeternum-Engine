-- WP118–119 additive opt-in career journal; no backfill or activation of legacy worlds.
CREATE TABLE IF NOT EXISTS wp_career_ledgers (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 initial_json LONGTEXT NOT NULL CHECK(JSON_VALID(initial_json)),
 state_json LONGTEXT NOT NULL CHECK(JSON_VALID(state_json)),
 state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sequence_no BIGINT UNSIGNED NOT NULL DEFAULT 0,
 FOREIGN KEY(world_id) REFERENCES wp_world_clock(world_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS wp_career_events (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sequence_no BIGINT UNSIGNED NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 command_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_json LONGTEXT NOT NULL CHECK(JSON_VALID(event_json)),
 signature_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 previous_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 receipt_json LONGTEXT NOT NULL CHECK(JSON_VALID(receipt_json)),
 PRIMARY KEY(world_id,sequence_no), UNIQUE KEY wp_career_command(world_id,actor_id,command_id),
 FOREIGN KEY(world_id) REFERENCES wp_career_ledgers(world_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS wp_career_active_offices (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 office_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 mandate_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 career_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 generation BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(world_id,office_id), UNIQUE KEY wp_career_active_mandate(world_id,mandate_id),
 FOREIGN KEY(world_id) REFERENCES wp_career_ledgers(world_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS wp_career_memberships (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 career_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 character_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,account_id), UNIQUE KEY wp_career_id(world_id,career_id), UNIQUE KEY wp_career_character(world_id,character_id),
 FOREIGN KEY(world_id) REFERENCES wp_career_ledgers(world_id),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
