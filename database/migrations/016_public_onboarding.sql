-- Durable, retryable public signup claim. Identity + claim are inserted in one transaction.
-- The normal world provisioning is a sequence of individually idempotent world-locked
-- transactions, so pending claims are safely retried after any interruption.
CREATE TABLE engine_onboarding (
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL CHECK (status IN ('pending','ready')),
 created_at BIGINT NOT NULL CHECK (created_at>=0),
 CONSTRAINT onboarding_identity_fk FOREIGN KEY (actor_id) REFERENCES engine_identities(actor_id),
 KEY onboarding_status (status,created_at)
) ENGINE=InnoDB;
