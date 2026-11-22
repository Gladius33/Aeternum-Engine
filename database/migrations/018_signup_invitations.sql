-- Optional controlled beta: one high-entropy invitation can enroll one actor in one configured world.
CREATE TABLE engine_signup_invitations (
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 created_at BIGINT NOT NULL CHECK(created_at>=0),
 expires_at BIGINT NOT NULL CHECK(expires_at>created_at),
 revoked TINYINT NOT NULL DEFAULT 0 CHECK(revoked IN (0,1)),
 used_at BIGINT NULL,
 INDEX invitation_world (world_id,actor_id),
 CONSTRAINT invite_identity_fk FOREIGN KEY(actor_id) REFERENCES engine_identities(actor_id),
 CONSTRAINT invitation_use CHECK ((actor_id IS NULL AND used_at IS NULL) OR (actor_id IS NOT NULL AND used_at IS NOT NULL))
) ENGINE=InnoDB;
