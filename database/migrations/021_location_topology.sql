-- M04: world-scoped directed topology; additive, preserves legacy locations.
CREATE TABLE engine_location_links (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 from_location VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 to_location VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 travel_cost INT UNSIGNED NOT NULL CHECK (travel_cost > 0),
 PRIMARY KEY(world_id,from_location,to_location),
 INDEX(world_id,to_location,from_location),
 FOREIGN KEY(world_id,from_location) REFERENCES engine_locations(world_id,location_id),
 FOREIGN KEY(world_id,to_location) REFERENCES engine_locations(world_id,location_id),
 CHECK (from_location <> to_location)
) ENGINE=InnoDB;
