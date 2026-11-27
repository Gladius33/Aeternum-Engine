-- WP102 managed universes. Technical ownership is never a playable office.
INSERT INTO wp_accounts(account_id,username,password_hash,enabled)
 VALUES('acct-00000000000000000000000000000000','universe-service','disabled-service-no-password',0);
CREATE TABLE wp_universe_pool (pool_id TINYINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO wp_universe_pool(pool_id) VALUES(1);
CREATE TABLE wp_universes (
 universe_no BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(world_id) REFERENCES wp_alpha_worlds(world_id)
) ENGINE=InnoDB;
CREATE TABLE wp_universe_slots (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
 generation INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(world_id,country_code),
 UNIQUE KEY wp_one_country_per_account(world_id,account_id),
 FOREIGN KEY(world_id) REFERENCES wp_universes(world_id),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
CREATE TABLE wp_universe_control_periods (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 generation INT UNSIGNED NOT NULL,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 joined_on DATE NOT NULL,
 left_on DATE NULL,
 PRIMARY KEY(world_id,country_code,generation),
 INDEX wp_control_account(account_id,world_id),
 FOREIGN KEY(world_id,country_code) REFERENCES wp_universe_slots(world_id,country_code),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
CREATE TABLE wp_universe_join_receipts (
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 response_json LONGTEXT NOT NULL,
 PRIMARY KEY(account_id,receipt_key),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
