-- Additive WP120 public experimental ranking, no V1 changes.
CREATE TABLE wp_career_score_pins (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 manifest_json LONGTEXT NOT NULL CHECK(JSON_VALID(manifest_json)),
 signature_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 FOREIGN KEY(world_id) REFERENCES wp_career_public_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE wp_career_score_events (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 series_id VARCHAR(72) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sequence_no BIGINT UNSIGNED NOT NULL,
 evidence_json LONGTEXT NOT NULL CHECK(JSON_VALID(evidence_json)),
 signature_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,series_id,sequence_no),
 FOREIGN KEY(world_id) REFERENCES wp_career_score_pins(world_id)
) ENGINE=InnoDB;
CREATE TABLE wp_career_score_latest (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 series_id VARCHAR(72) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sequence_no BIGINT UNSIGNED NOT NULL,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 cohort_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 qualified TINYINT UNSIGNED NOT NULL CHECK(qualified IN (0,1)),
 PRIMARY KEY(world_id,series_id),
 INDEX wp_career_score_cohort(cohort_sha,qualified,account_id),
 FOREIGN KEY(world_id,series_id,sequence_no) REFERENCES wp_career_score_events(world_id,series_id,sequence_no),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
