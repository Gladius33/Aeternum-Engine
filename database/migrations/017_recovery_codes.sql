-- High-entropy bearer recovery codes. The plaintext is returned only once.
CREATE TABLE engine_account_recovery (
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 updated_at BIGINT NOT NULL CHECK (updated_at>=0),
 CONSTRAINT recovery_identity_fk FOREIGN KEY (actor_id) REFERENCES engine_identities(actor_id)
) ENGINE=InnoDB;
