-- WP104 experimental player-period score; never assign pre-claim income gains.
CREATE TABLE wp_universe_income_checkpoints (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 day DATE NOT NULL,
 index_bps INT UNSIGNED NOT NULL,
 cohort_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 source_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,country_code),
 FOREIGN KEY(world_id) REFERENCES wp_alpha_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE wp_universe_period_scores (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 generation INT UNSIGNED NOT NULL,
 cohort_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 opening_index_bps INT UNSIGNED NOT NULL,
 latest_index_bps INT UNSIGNED NOT NULL,
 opening_source_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 latest_source_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 through_day DATE NOT NULL,
 elapsed_control_days INT UNSIGNED NOT NULL DEFAULT 0,
 gain_bps INT NOT NULL DEFAULT 0,
 score BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(world_id,country_code,generation),
 INDEX wp_universe_scores_cohort(cohort_sha,score),
 FOREIGN KEY(world_id,country_code,generation) REFERENCES wp_universe_control_periods(world_id,country_code,generation)
) ENGINE=InnoDB;
