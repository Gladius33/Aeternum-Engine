-- MariaDB / InnoDB. Apply once to a new database; no destructive statements.
CREATE TABLE engine_worlds (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    next_sequence BIGINT NOT NULL DEFAULT 0 CHECK (next_sequence >= 0),
    watermark BIGINT NOT NULL DEFAULT -1 CHECK (watermark >= -1),
    state_json JSON NOT NULL
) ENGINE=InnoDB;

CREATE TABLE engine_events (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_type VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    due_at BIGINT NOT NULL CHECK (due_at >= 0),
    sequence_no BIGINT NOT NULL CHECK (sequence_no > 0),
    payload_json JSON NOT NULL,
    completed TINYINT NOT NULL DEFAULT 0 CHECK (completed IN (0,1)),
    PRIMARY KEY (world_id, event_id),
    UNIQUE KEY world_sequence (world_id, sequence_no),
    KEY due_order (world_id, completed, due_at, sequence_no),
    CONSTRAINT event_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds (world_id)
) ENGINE=InnoDB;
