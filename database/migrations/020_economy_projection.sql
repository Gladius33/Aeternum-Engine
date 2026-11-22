-- M03 / 020: indexed relational economy projection. DDL commits implicitly.
-- Operators MUST quiesce writes and take an off-instance verified backup first.
-- Do not delete legacy JSON snapshots: old journal receipts/replay still use them.
ALTER TABLE engine_worlds
  ADD COLUMN economy_projection_version TINYINT NOT NULL DEFAULT 0
    CHECK (economy_projection_version IN (0,1));

CREATE TABLE engine_economy_balances (
  world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  account_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  resource_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  stock_units BIGINT NOT NULL CHECK (stock_units BETWEEN 0 AND 1000000000000),
  settled_at BIGINT NOT NULL CHECK (settled_at >= 0),
  remainder BIGINT NOT NULL CHECK (remainder >= 0),
  level_no INT NOT NULL DEFAULT 0 CHECK (level_no BETWEEN 0 AND 100),
  pending_event_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
  PRIMARY KEY (world_id,account_id,resource_id),
  KEY balance_world_resource (world_id,resource_id,account_id),
  CONSTRAINT balance_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;

CREATE TABLE engine_market_offers (
  world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  offer_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  seller_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  give_resource VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  give_units BIGINT NOT NULL CHECK (give_units BETWEEN 1 AND 1000000000000),
  want_resource VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  want_units BIGINT NOT NULL CHECK (want_units BETWEEN 1 AND 1000000000000),
  status ENUM('open','filled','cancelled','expired') NOT NULL,
  buyer_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  expires_at BIGINT NULL CHECK (expires_at IS NULL OR expires_at >= 0),
  PRIMARY KEY (world_id,offer_id),
  KEY offer_open_page (world_id,status,offer_id),
  KEY offer_seller_status (world_id,seller_id,status),
  CONSTRAINT market_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;

CREATE TABLE engine_market_refunds (
  world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  account_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  resource_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  claimable_units BIGINT NOT NULL CHECK (claimable_units BETWEEN 1 AND 1000000000000),
  PRIMARY KEY (world_id,account_id,resource_id),
  CONSTRAINT refund_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;

ALTER TABLE engine_resource_ledger MODIFY COLUMN reason
  ENUM('opening','production','transfer','upgrade_cost','market_reserve',
       'market_payment','market_delivery','market_release','market_claim') NOT NULL;
