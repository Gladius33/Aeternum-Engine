-- WP78: only newly created canonical campaigns enter the experimental league.
ALTER TABLE wp_world_clock ADD COLUMN IF NOT EXISTS score_enabled TINYINT(1) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS wp_campaign_scores (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 cohort_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 score_version VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 committed_day DATE NOT NULL,
 clock_revision BIGINT UNSIGNED NOT NULL,
 score BIGINT UNSIGNED NOT NULL,
 elapsed_days INT UNSIGNED NOT NULL,
 gain_bps INT NOT NULL,
 terminal TINYINT(1) NOT NULL,
 report_json LONGTEXT NOT NULL CHECK(JSON_VALID(report_json)),
 report_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 INDEX wp_score_cohort (cohort_sha,score,elapsed_days,gain_bps),
 FOREIGN KEY(world_id) REFERENCES wp_alpha_worlds(world_id) ON DELETE CASCADE
) ENGINE=InnoDB;
