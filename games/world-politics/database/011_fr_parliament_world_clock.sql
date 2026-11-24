-- WP25. France parliamentary constitutional clock pinned to world-clock saga.
-- Install after 007_world_clock.sql, 008_world_clock_countries.sql,
-- 009_fr_constitutional_49_3.sql on the World Politics dedicated database.
-- The real parliamentary system still needs its sitting-day calendar.
CREATE TABLE IF NOT EXISTS wp_world_clock_fr_parliament (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'FR',
  timezone_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Europe/Paris',
  PRIMARY KEY(world_id),
  CONSTRAINT wp_clock_fr_parliament_world_fk FOREIGN KEY(world_id)
    REFERENCES wp_world_clock(world_id),
  CONSTRAINT wp_clock_fr_parliament_only_fr CHECK(country_code='FR'),
  CONSTRAINT wp_clock_fr_parliament_zone CHECK(timezone_id='Europe/Paris')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_world_clock_fr_parliament_steps (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  command_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  before_day DATE NOT NULL,
  after_day DATE NOT NULL,
  after_instant BIGINT UNSIGNED NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  recovered TINYINT(1) NOT NULL DEFAULT 0,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(world_id,command_key),
  CONSTRAINT wp_clock_fr_parliament_step_fk FOREIGN KEY(world_id)
    REFERENCES wp_world_clock_fr_parliament(world_id)
) ENGINE=InnoDB;
