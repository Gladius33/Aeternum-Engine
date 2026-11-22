-- M04: operator-approved, immutable cost schedules and actor-owned building
-- foundations. No economic debit or completion is performed by this schema.
CREATE TABLE engine_building_types (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 building_type VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 rules_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 stages_json TEXT NOT NULL CHECK (JSON_VALID(stages_json)),
 PRIMARY KEY(world_id,building_type),
 CONSTRAINT building_type_world_fk FOREIGN KEY(world_id) REFERENCES engine_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE engine_buildings (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 building_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 entity_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 territory_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 building_type VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 level_no INT UNSIGNED NOT NULL DEFAULT 0 CHECK(level_no=0),
 PRIMARY KEY(world_id,building_id),
 INDEX building_actor_page(world_id,actor_id,building_id),
 CONSTRAINT building_entity_fk FOREIGN KEY(world_id,entity_id) REFERENCES engine_entities(world_id,entity_id),
 CONSTRAINT building_territory_fk FOREIGN KEY(world_id,territory_id) REFERENCES engine_territories(world_id,territory_id),
 CONSTRAINT building_member_fk FOREIGN KEY(world_id,actor_id) REFERENCES engine_memberships(world_id,actor_id),
 CONSTRAINT building_type_fk FOREIGN KEY(world_id,building_type) REFERENCES engine_building_types(world_id,building_type)
) ENGINE=InnoDB;
