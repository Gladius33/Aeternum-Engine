-- Additive opt-in public career pool; isolated from legacy national slots.
CREATE TABLE wp_career_public_worlds (
 universe_no BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 capacity TINYINT UNSIGNED NOT NULL DEFAULT 8 CHECK(capacity=8),
 tick_seconds INT UNSIGNED NOT NULL DEFAULT 3600 CHECK(tick_seconds=3600),
 next_tick_epoch BIGINT UNSIGNED NULL,
 next_game_day DATE NULL,
 INDEX wp_career_public_due(next_tick_epoch),
 FOREIGN KEY(world_id) REFERENCES wp_career_ledgers(world_id)
) ENGINE=InnoDB;
CREATE TABLE wp_career_public_receipts (
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 response_json LONGTEXT NOT NULL CHECK(JSON_VALID(response_json)),
 PRIMARY KEY(account_id,receipt_key),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
