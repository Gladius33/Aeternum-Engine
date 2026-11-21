-- Trusted administrative membership and immutable account ownership.
CREATE TABLE engine_memberships (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    active TINYINT NOT NULL DEFAULT 1 CHECK (active IN (0,1)),
    PRIMARY KEY (world_id,actor_id),
    CONSTRAINT membership_world_fk FOREIGN KEY (world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE engine_account_owners (
    world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    account_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (world_id,account_id),
    KEY actor_accounts (world_id,actor_id),
    CONSTRAINT owner_membership_fk FOREIGN KEY (world_id,actor_id) REFERENCES engine_memberships(world_id,actor_id)
) ENGINE=InnoDB;
ALTER TABLE engine_commands ADD COLUMN actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD COLUMN client_request_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD UNIQUE KEY actor_request (world_id,actor_id,client_request_id),
    ADD CONSTRAINT command_actor_fk FOREIGN KEY (world_id,actor_id) REFERENCES engine_memberships(world_id,actor_id);
