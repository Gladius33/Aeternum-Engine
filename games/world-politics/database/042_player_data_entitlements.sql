-- Player API entitlement, separate from normal game sessions.
CREATE TABLE wp_player_api_entitlements (
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 paid_until BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
CREATE TABLE wp_player_api_keys (
 key_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 expires_at BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
