CREATE TABLE engine_locations (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 location_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,location_id),
 FOREIGN KEY(world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE engine_entities (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 entity_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 location_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,entity_id),
 FOREIGN KEY(world_id,location_id) REFERENCES engine_locations(world_id,location_id),
 FOREIGN KEY(world_id,actor_id) REFERENCES engine_memberships(world_id,actor_id)
) ENGINE=InnoDB;
