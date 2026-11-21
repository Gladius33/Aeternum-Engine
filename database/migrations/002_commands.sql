-- Append-only command receipts for durable idempotency.
CREATE TABLE engine_commands (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    command_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    command_type VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_json JSON NOT NULL,
    effective_at BIGINT NOT NULL CHECK (effective_at >= 0),
    result_state_json JSON NOT NULL,
    scheduled_ids_json JSON NOT NULL,
    PRIMARY KEY (world_id, command_id),
    CONSTRAINT command_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds (world_id)
) ENGINE=InnoDB;
