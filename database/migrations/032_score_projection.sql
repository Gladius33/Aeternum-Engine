-- F02: derived score projection; canonical journal and pinned rules are unchanged.
-- Quiesce writers and take a verified coherent backup before schema upgrades.
CREATE TABLE engine_score_watermarks (
  world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  journal_no BIGINT NOT NULL CHECK (journal_no >= 0),
  PRIMARY KEY (world_id),
  CONSTRAINT score_watermark_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;

CREATE TABLE engine_account_scores (
  world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  account_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  development BIGINT NOT NULL CHECK (development BETWEEN 0 AND 1000000000000),
  military BIGINT NOT NULL CHECK (military BETWEEN 0 AND 1000000000000),
  global_score BIGINT NOT NULL CHECK (global_score BETWEEN 1 AND 1000000000000 AND global_score=development+military),
  PRIMARY KEY (world_id,account_id),
  KEY score_rank (world_id,global_score,account_id),
  CONSTRAINT score_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;
