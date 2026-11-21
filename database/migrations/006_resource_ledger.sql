-- Append-only resource movements associated with committed journal entries.
CREATE TABLE engine_resource_ledger (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    journal_no BIGINT NOT NULL,
    line_no INT NOT NULL CHECK (line_no > 0),
    account_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    delta_units BIGINT NOT NULL CHECK (delta_units <> 0 AND delta_units BETWEEN -1000000000000 AND 1000000000000),
    balance_after BIGINT NOT NULL CHECK (balance_after BETWEEN 0 AND 1000000000000),
    reason ENUM('opening','production','transfer') NOT NULL,
    PRIMARY KEY (world_id,journal_no,line_no),
    KEY account_history (world_id,account_id,resource_id,journal_no,line_no),
    CONSTRAINT ledger_journal_fk FOREIGN KEY (world_id,journal_no) REFERENCES engine_journal(world_id,journal_no)
) ENGINE=InnoDB;
