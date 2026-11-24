-- WP17 World Politics cross-domain day advancement.
-- Install ONLY in game-owned database after 003, 004 and 005. No engine table.
CREATE TABLE IF NOT EXISTS wp_world_clock (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  committed_day DATE NOT NULL,
  pending_day DATE NULL,
  pending_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_command_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  last_target_day DATE NULL,
  last_receipt_json LONGTEXT NULL CHECK (last_receipt_json IS NULL OR JSON_VALID(last_receipt_json)),
  PRIMARY KEY(world_id),
  CONSTRAINT wp_clock_pending_both CHECK (
    (pending_day IS NULL AND pending_key IS NULL)
    OR (pending_day IS NOT NULL AND pending_key IS NOT NULL)
  )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_world_clock_steps (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  command_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  subsystem ENUM('organizations','diplomacy','economy') NOT NULL,
  before_day DATE NOT NULL,
  after_day DATE NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  recovered TINYINT(1) NOT NULL DEFAULT 0,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(world_id,command_key,subsystem),
  CONSTRAINT wp_clock_step_world_fk FOREIGN KEY(world_id)
    REFERENCES wp_world_clock(world_id)
) ENGINE=InnoDB;
