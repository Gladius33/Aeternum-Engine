-- Preserve historical programme choices; direct actions are immutable decisions.
ALTER TABLE wp_universe_slots ADD COLUMN programme_set_on DATE NULL;
CREATE TABLE wp_universe_programme_direct (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 mandate_start DATE NOT NULL,
 programme VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 decision_day DATE NOT NULL,
 generation INT UNSIGNED NOT NULL,
 action_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 outcome VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,mandate_start,programme),
 UNIQUE KEY wp_programme_direct_day(world_id,decision_day),
 FOREIGN KEY(world_id) REFERENCES wp_universes(world_id)
) ENGINE=InnoDB;
