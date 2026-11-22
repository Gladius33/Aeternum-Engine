-- Optional activation mode: no public bearer until proof of the mailbox.
-- Raw 256-bit activation tokens never persist; email is PII, restrict DB access.
-- Old operator accounts and old public onboarding claims remain unchanged.
CREATE TABLE engine_email_activations (
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 email VARCHAR(254) NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT NOT NULL CHECK(created_at>=0),
 expires_at BIGINT NOT NULL CHECK(expires_at>created_at),
 verified_at BIGINT NULL,
 consumed_at BIGINT NULL,
 CONSTRAINT email_activation_actor_fk FOREIGN KEY(actor_id) REFERENCES engine_identities(actor_id),
 UNIQUE KEY email_activation_email(email),
 INDEX email_activation_expiry(expires_at,verified_at)
) ENGINE=InnoDB;
