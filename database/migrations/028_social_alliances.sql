-- M09 social foundation: world-scoped alliances, role membership and invitations.
-- Additive InnoDB schema. Take a consistent backup before running the migrator.
CREATE TABLE engine_alliances (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 alliance_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 name VARCHAR(100) NOT NULL,
 created_by VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,alliance_id),
 CONSTRAINT alliance_world_fk FOREIGN KEY(world_id) REFERENCES engine_worlds(world_id),
 CONSTRAINT alliance_creator_fk FOREIGN KEY(world_id,created_by) REFERENCES engine_memberships(world_id,actor_id)
) ENGINE=InnoDB;
CREATE TABLE engine_alliance_members (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 alliance_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,alliance_id,actor_id),
 KEY alliance_member_actor(world_id,actor_id),
 CONSTRAINT alliance_member_parent_fk FOREIGN KEY(world_id,alliance_id) REFERENCES engine_alliances(world_id,alliance_id),
 CONSTRAINT alliance_member_actor_fk FOREIGN KEY(world_id,actor_id) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT alliance_member_role_ck CHECK(role IN ('owner','officer','member'))
) ENGINE=InnoDB;
CREATE TABLE engine_alliance_invites (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 alliance_id VARCHAR(64) CHARACTER ascii COLLATE ascii_bin NOT NULL,
 invited_actor VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 invited_by VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
 PRIMARY KEY(world_id,alliance_id,invited_actor),
 CONSTRAINT alliance_invite_parent_fk FOREIGN KEY(world_id,alliance_id) REFERENCES engine_alliances(world_id,alliance_id),
 CONSTRAINT alliance_invite_target_fk FOREIGN KEY(world_id,invited_actor) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT alliance_invite_sender_fk FOREIGN KEY(world_id,invited_by) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT alliance_invite_status_ck CHECK(status IN ('pending','accepted','revoked','declined'))
) ENGINE=InnoDB;
