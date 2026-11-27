-- Test-mode billing separated from Research.
CREATE TABLE wp_stripe_environment (
 singleton_id TINYINT UNSIGNED PRIMARY KEY,
 deployment_env ENUM('development','staging','production') NOT NULL
) ENGINE=InnoDB;
CREATE TABLE wp_stripe_game_orders (
 order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 price_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 checkout_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 checkout_url VARCHAR(2048) NULL,
 customer_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 subscription_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 period_end BIGINT UNSIGNED NOT NULL DEFAULT 0,
 held TINYINT UNSIGNED NOT NULL DEFAULT 0,
 ended_at BIGINT UNSIGNED NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY account_checkout(account_id,request_sha),
 INDEX active_order(account_id,ended_at),
 FOREIGN KEY(account_id) REFERENCES wp_accounts(account_id)
) ENGINE=InnoDB;
CREATE TABLE wp_stripe_game_receipts (
 payment_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(order_id) REFERENCES wp_stripe_game_orders(order_id)
) ENGINE=InnoDB;
CREATE TABLE wp_stripe_game_holds (
 payment_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 reason VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 disputed TINYINT UNSIGNED NOT NULL DEFAULT 0,
 resolved_at BIGINT UNSIGNED NULL,
 FOREIGN KEY(payment_id) REFERENCES wp_stripe_game_receipts(payment_id)
) ENGINE=InnoDB;

CREATE TABLE wp_stripe_game_resolutions (
 resolution_ref VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 payment_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 evidence_json LONGTEXT NOT NULL CHECK(JSON_VALID(evidence_json)),
 created_at BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(payment_id) REFERENCES wp_stripe_game_holds(payment_id)
) ENGINE=InnoDB;

CREATE TABLE wp_stripe_payment_fence (
 payment_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 reversed TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
