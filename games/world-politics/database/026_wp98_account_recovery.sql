-- Optional one-time recovery credential, separate from sessions.
CREATE TABLE IF NOT EXISTS wp_account_recovery (
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 secret_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY (account_id) REFERENCES wp_accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB;
