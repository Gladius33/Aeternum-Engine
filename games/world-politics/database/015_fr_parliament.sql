-- WP28: game-owned, provenance-labelled French Assembly roster and roll calls.
-- This schema must NEVER be installed in the generic Aeternum Engine main.
CREATE TABLE IF NOT EXISTS wp_fr_parliament_states (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state_json LONGTEXT NOT NULL CHECK(JSON_VALID(state_json)),
  state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  constitutional_world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY(world_id),
  CONSTRAINT wp_fr_parliament_constitution_fk
    FOREIGN KEY(constitutional_world_id)
    REFERENCES wp_fr_constitutional_states(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_parliament_receipts (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  principal_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  response_json LONGTEXT NOT NULL CHECK(JSON_VALID(response_json)),
  PRIMARY KEY(world_id,principal_id,receipt_key),
  CONSTRAINT wp_fr_parliament_receipt_fk FOREIGN KEY(world_id)
    REFERENCES wp_fr_parliament_states(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_fr_parliament_journal (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL,
  principal_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  operation VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  constitutional_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  result_json LONGTEXT NOT NULL CHECK(JSON_VALID(result_json)),
  PRIMARY KEY(world_id,revision),
  CONSTRAINT wp_fr_parliament_journal_fk FOREIGN KEY(world_id)
    REFERENCES wp_fr_parliament_states(world_id)
) ENGINE=InnoDB;
