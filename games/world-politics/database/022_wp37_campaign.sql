-- WP37: terminal campaign publication, immutable fictional outcome queue.
ALTER TABLE wp_world_clock ADD COLUMN IF NOT EXISTS campaign_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE wp_world_clock ADD COLUMN IF NOT EXISTS game_over TINYINT(1) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS wp_campaign_events (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_day DATE NOT NULL,
 event_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_json LONGTEXT NOT NULL CHECK(JSON_VALID(event_json)),
 event_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,event_day),
 UNIQUE KEY wp_campaign_event_identity(world_id,event_id)
) ENGINE=InnoDB;
