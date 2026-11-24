-- World Politics WP08: isolated global diplomacy aggregate for one game world.
-- Do not apply to Aeternum Engine main schema.
CREATE TABLE IF NOT EXISTS wp_diplomatic_states (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state_json LONGTEXT NOT NULL CHECK (JSON_VALID(state_json)),
  state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_registry_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_diplomatic_receipts (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  response_json LONGTEXT NOT NULL CHECK (JSON_VALID(response_json)),
  PRIMARY KEY(world_id, actor_id, receipt_key),
  CONSTRAINT wp_dip_receipt_world FOREIGN KEY(world_id)
    REFERENCES wp_diplomatic_states(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_diplomatic_journal (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL,
  actor_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  operation VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  before_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  result_json LONGTEXT NOT NULL CHECK (JSON_VALID(result_json)),
  PRIMARY KEY(world_id,revision),
  CONSTRAINT wp_dip_event_world FOREIGN KEY(world_id)
    REFERENCES wp_diplomatic_states(world_id)
) ENGINE=InnoDB;
