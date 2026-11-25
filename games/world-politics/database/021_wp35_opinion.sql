-- WP35: optional opinion checkpoint after national and domestic clocks.
ALTER TABLE wp_world_clock ADD COLUMN IF NOT EXISTS opinion_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE wp_world_clock_steps MODIFY COLUMN subsystem ENUM('organizations','diplomacy','economy','domestic','opinion') NOT NULL;
CREATE TABLE IF NOT EXISTS wp_opinion_states (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 state_json LONGTEXT NOT NULL CHECK(JSON_VALID(state_json)),
 state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
 last_key_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 last_receipt_json LONGTEXT NULL CHECK(last_receipt_json IS NULL OR JSON_VALID(last_receipt_json))
) ENGINE=InnoDB;
