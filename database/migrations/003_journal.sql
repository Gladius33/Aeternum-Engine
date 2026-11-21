-- Quiesce every writer before applying this migration.
-- Existing worlds get a migration checkpoint, not a fabricated historical journal.
ALTER TABLE engine_worlds ADD COLUMN journal_sequence BIGINT NOT NULL DEFAULT 0 CHECK (journal_sequence >= 0),
    ADD COLUMN checkpoint_json JSON NULL,
    ADD COLUMN checkpoint_at BIGINT NOT NULL DEFAULT -1 CHECK (checkpoint_at >= -1);
UPDATE engine_worlds SET checkpoint_json = state_json, checkpoint_at = watermark;
ALTER TABLE engine_worlds MODIFY COLUMN checkpoint_json JSON NOT NULL;

CREATE TABLE engine_journal (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    journal_no BIGINT NOT NULL CHECK (journal_no > 0),
    entry_kind ENUM('command','event') NOT NULL,
    entry_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entry_type VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    effective_at BIGINT NOT NULL CHECK (effective_at >= 0),
    payload_json JSON NOT NULL,
    state_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    followup_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (world_id,journal_no),
    UNIQUE KEY journal_entry (world_id,entry_kind,entry_id),
    CONSTRAINT journal_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;
