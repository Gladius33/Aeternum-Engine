-- M04 additive geography: one immutable territory identity per world, optional
-- trusted assignment to one active member. Existing locations/worlds are untouched.
CREATE TABLE engine_territories (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 territory_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 location_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 owner_actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 PRIMARY KEY(world_id,territory_id),
 INDEX territory_location (world_id,location_id,territory_id),
 INDEX territory_owner (world_id,owner_actor_id,territory_id),
 CONSTRAINT territory_location_fk FOREIGN KEY (world_id,location_id)
   REFERENCES engine_locations(world_id,location_id),
 CONSTRAINT territory_owner_fk FOREIGN KEY (world_id,owner_actor_id)
   REFERENCES engine_memberships(world_id,actor_id)
) ENGINE=InnoDB;
