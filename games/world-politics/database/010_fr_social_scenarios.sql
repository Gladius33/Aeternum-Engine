-- WP24. Durable French social and professional reaction archives.
-- Game-specific; never install in the Aeternum generic engine schema.
CREATE TABLE IF NOT EXISTS wp_fr_social_states (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state_json LONGTEXT NOT NULL CHECK(JSON_VALID(state_json)),
  state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cohort_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_social_assumptions (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  bill_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  assumption_json LONGTEXT NOT NULL CHECK(JSON_VALID(assumption_json)),
  assumption_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY(world_id,bill_id),
  CONSTRAINT wp_fr_social_assumption_fk FOREIGN KEY(world_id)
    REFERENCES wp_fr_social_states(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_social_receipts (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  principal_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  response_json LONGTEXT NOT NULL CHECK(JSON_VALID(response_json)),
  PRIMARY KEY(world_id,principal_id,receipt_key),
  CONSTRAINT wp_fr_social_receipt_fk FOREIGN KEY(world_id)
    REFERENCES wp_fr_social_states(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_social_journal (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL,
  principal_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  operation VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  constitution_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  assumptions_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  result_json LONGTEXT NOT NULL CHECK(JSON_VALID(result_json)),
  PRIMARY KEY(world_id,revision),
  CONSTRAINT wp_fr_social_event_fk FOREIGN KEY(world_id)
    REFERENCES wp_fr_social_states(world_id)
) ENGINE=InnoDB;
