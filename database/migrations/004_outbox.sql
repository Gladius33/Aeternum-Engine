-- Outbox contains transition metadata, not private world snapshots.
-- Existing journal entries are intentionally not backfilled for delivery.
CREATE TABLE engine_outbox (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    journal_no BIGINT NOT NULL,
    envelope_json JSON NOT NULL,
    available_at BIGINT NOT NULL DEFAULT 0 CHECK (available_at >= 0),
    lease_until BIGINT NOT NULL DEFAULT 0 CHECK (lease_until >= 0),
    lease_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    attempts BIGINT NOT NULL DEFAULT 0 CHECK (attempts >= 0),
    delivered_at BIGINT NULL,
    PRIMARY KEY (world_id,journal_no),
    KEY delivery_scan (world_id,delivered_at,available_at,lease_until,journal_no),
    CONSTRAINT outbox_journal_fk FOREIGN KEY (world_id,journal_no) REFERENCES engine_journal(world_id,journal_no)
) ENGINE=InnoDB;
