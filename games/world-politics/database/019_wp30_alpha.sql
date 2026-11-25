-- WP30 private alpha accounts, hashed sessions, game ownership and quotas.
CREATE TABLE IF NOT EXISTS wp_accounts (
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 username VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS wp_sessions (
 token_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at BIGINT UNSIGNED NOT NULL,
 INDEX wp_session_account (account_id),
 FOREIGN KEY (account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS wp_alpha_worlds (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 owner_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 create_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 label VARCHAR(100) NOT NULL,
 scenario VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY wp_alpha_create (owner_id,create_key),
 FOREIGN KEY (owner_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS wp_alpha_members (
 world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 role ENUM('owner','viewer') NOT NULL,
 PRIMARY KEY(world_id,account_id),
 INDEX wp_alpha_member_account(account_id),
 FOREIGN KEY(world_id) REFERENCES wp_alpha_worlds(world_id),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS wp_http_limits (
 bucket_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 window_id BIGINT UNSIGNED NOT NULL,
 hits INT UNSIGNED NOT NULL,
 PRIMARY KEY(bucket_sha,window_id),
 INDEX wp_http_limits_expiry(window_id)
) ENGINE=InnoDB;
