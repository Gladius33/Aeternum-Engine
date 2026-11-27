-- Additive migration; legacy universes keep manual domestic policy.
ALTER TABLE wp_universe_slots ADD COLUMN domestic_programme VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'manual',
 ADD COLUMN last_human_domestic_day DATE NULL;
CREATE TABLE wp_universe_programme_actions (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 decision_day DATE NOT NULL,
 generation INT UNSIGNED NOT NULL,
 programme VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 policy_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 effective_day DATE NOT NULL,
 PRIMARY KEY(world_id,decision_day),
 FOREIGN KEY(world_id) REFERENCES wp_universes(world_id)
) ENGINE=InnoDB;
