-- WP29 final: pin domestic participation, extend the existing recoverable checkpoint enum.
ALTER TABLE wp_world_clock ADD COLUMN IF NOT EXISTS domestic_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE wp_world_clock_steps MODIFY COLUMN subsystem ENUM('organizations','diplomacy','economy','domestic') NOT NULL;
CREATE TABLE IF NOT EXISTS wp_domestic_enactments (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  policy_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  record_json LONGTEXT NOT NULL CHECK(JSON_VALID(record_json)),
  record_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY(world_id,policy_id),
  CONSTRAINT wp_domestic_enactment_world_fk FOREIGN KEY(world_id) REFERENCES wp_domestic_states(world_id)
) ENGINE=InnoDB;
