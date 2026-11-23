-- Append-only governance audit. Additive; existing worlds and owners remain unchanged.
CREATE TABLE engine_alliance_owner_recoveries (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 recovery_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 alliance_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 old_owner VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 new_owner VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT NOT NULL,
 PRIMARY KEY(world_id,recovery_id),
 KEY alliance_recovery_history(world_id,alliance_id,created_at,recovery_id),
 CONSTRAINT alliance_recovery_parent_fk FOREIGN KEY(world_id,alliance_id) REFERENCES engine_alliances(world_id,alliance_id),
 CONSTRAINT alliance_recovery_old_fk FOREIGN KEY(world_id,old_owner) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT alliance_recovery_new_fk FOREIGN KEY(world_id,new_owner) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT alliance_recovery_distinct_ck CHECK(old_owner<>new_owner)
) ENGINE=InnoDB;
