-- Optional experimental scenario commands; previous fiscal editions do not consume them.
CREATE TABLE wp_national_budget_scenarios (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 generation INT UNSIGNED NOT NULL,
 request_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 submitted_day DATE NOT NULL,
 effective_day DATE NOT NULL,
 expires_day DATE NOT NULL,
 receipts_bps SMALLINT NOT NULL,
 primary_bps SMALLINT NOT NULL,
 opening_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,country_code,generation,request_key),
 KEY wp_budget_effective(world_id,country_code,effective_day,expires_day),
 FOREIGN KEY(world_id) REFERENCES wp_universes(world_id)
) ENGINE=InnoDB;
