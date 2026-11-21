CREATE TABLE engine_components (
 world_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 entity_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 component_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 data_json TEXT NOT NULL CHECK (JSON_VALID(data_json)),
 PRIMARY KEY(world_id,entity_id,component_id),
 FOREIGN KEY(world_id,entity_id) REFERENCES engine_entities(world_id,entity_id)
) ENGINE=InnoDB;
