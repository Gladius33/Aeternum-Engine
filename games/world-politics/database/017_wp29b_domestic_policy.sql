-- WP29B: standalone domestic policy aggregate, game database only.
CREATE TABLE IF NOT EXISTS wp_domestic_states (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  assumptions_json LONGTEXT NOT NULL CHECK (JSON_VALID(assumptions_json)),
  assumptions_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state_json LONGTEXT NOT NULL CHECK (JSON_VALID(state_json)),
  state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_domestic_journal (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL,
  principal_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  operation ENUM('propose','enact','clock') NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_json LONGTEXT NOT NULL CHECK (JSON_VALID(request_json)),
  before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  result_json LONGTEXT NOT NULL CHECK (JSON_VALID(result_json)),
  PRIMARY KEY (world_id,revision),
  CONSTRAINT wp_domestic_journal_fk FOREIGN KEY (world_id) REFERENCES wp_domestic_states(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_domestic_receipts (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  principal_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  response_json LONGTEXT NOT NULL CHECK (JSON_VALID(response_json)),
  PRIMARY KEY (world_id,principal_id,receipt_key),
  CONSTRAINT wp_domestic_receipts_fk FOREIGN KEY (world_id) REFERENCES wp_domestic_states(world_id)
) ENGINE=InnoDB;
