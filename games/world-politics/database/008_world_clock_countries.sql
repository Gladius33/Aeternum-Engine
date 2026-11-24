-- WP18 extends the recoverable world clock with per-country institutions.
-- Apply after 007_world_clock.sql, 001_rosters.sql and 002_institutions.sql.
-- Never apply to the generic Aeternum engine database.
CREATE TABLE IF NOT EXISTS wp_world_clock_countries (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY(world_id,country_code),
  CONSTRAINT wp_clock_country_world_fk FOREIGN KEY(world_id)
    REFERENCES wp_world_clock(world_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wp_world_clock_country_steps (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  command_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  before_day DATE NOT NULL,
  after_day DATE NOT NULL,
  after_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  recovered TINYINT(1) NOT NULL DEFAULT 0,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(world_id,command_key,country_code),
  CONSTRAINT wp_clock_country_step_roster_fk FOREIGN KEY(world_id,country_code)
    REFERENCES wp_world_clock_countries(world_id,country_code)
) ENGINE=InnoDB;
