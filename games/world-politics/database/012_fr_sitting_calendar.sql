-- Existing installations: add the immutable calendar configuration bit.
ALTER TABLE wp_fr_constitutional_states
  ADD COLUMN IF NOT EXISTS sitting_calendar_required TINYINT(1) NOT NULL DEFAULT 0;

-- WP26, game-owned parliamentary sitting calendar, not part of Aeternum Engine.
CREATE TABLE IF NOT EXISTS wp_fr_sitting_calendars (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state_json LONGTEXT NOT NULL CHECK (JSON_VALID(state_json)),
  state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_sitting_journal (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL,
  motion_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  scheduled_epoch BIGINT UNSIGNED NOT NULL,
  actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (world_id, revision),
  UNIQUE KEY wp_fr_sitting_one_motion (world_id, motion_id),
  CONSTRAINT wp_fr_sitting_journal_world_fk
    FOREIGN KEY (world_id) REFERENCES wp_fr_sitting_calendars(world_id)
) ENGINE=InnoDB;
