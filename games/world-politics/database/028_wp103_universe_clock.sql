-- WP103 persistent game-time schedule and inherited fictional diplomacy programme.
ALTER TABLE wp_universes
 ADD COLUMN tick_seconds INT UNSIGNED NOT NULL DEFAULT 3600,
 ADD COLUMN next_tick_epoch BIGINT UNSIGNED NULL,
 ADD COLUMN next_game_day DATE NULL,
 ADD INDEX wp_universe_due(next_tick_epoch);
ALTER TABLE wp_universe_slots
 ADD COLUMN diplomacy_programme VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'balanced',
 ADD COLUMN last_human_diplomacy_day DATE NULL;
UPDATE wp_universes u JOIN wp_world_clock c ON c.world_id=u.world_id
 SET u.next_game_day=DATE_ADD(c.committed_day,INTERVAL 1 DAY),u.next_tick_epoch=UNIX_TIMESTAMP()+u.tick_seconds
 WHERE EXISTS(SELECT 1 FROM wp_universe_control_periods p WHERE p.world_id=u.world_id);
