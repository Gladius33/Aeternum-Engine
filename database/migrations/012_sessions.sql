CREATE TABLE engine_sessions (
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at BIGINT NOT NULL,
 revoked TINYINT NOT NULL DEFAULT 0,
 FOREIGN KEY(actor_id) REFERENCES engine_identities(actor_id),
 INDEX(expires_at)
) ENGINE=InnoDB;
