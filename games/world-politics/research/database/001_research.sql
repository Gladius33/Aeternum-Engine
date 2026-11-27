-- Dedicated research database only. Never install as a gameplay migration.
CREATE TABLE wp_research_tenants (
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
 plan ENUM('standard','pro') NOT NULL DEFAULT 'standard',
 paid_until BIGINT UNSIGNED NOT NULL,
 share_dataset TINYINT UNSIGNED NOT NULL DEFAULT 0,
 billing_mode ENUM('monthly','payg') NOT NULL DEFAULT 'monthly',
 admin_exempt TINYINT UNSIGNED NOT NULL DEFAULT 0,
 included_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
 prepaid_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
 allow_overage TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE wp_research_keys (
 key_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at BIGINT UNSIGNED NOT NULL,
 revoked TINYINT UNSIGNED NOT NULL DEFAULT 0,
 FOREIGN KEY(tenant_id) REFERENCES wp_research_tenants(tenant_id)
) ENGINE=InnoDB;
CREATE TABLE wp_research_rate (
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 minute_no BIGINT UNSIGNED NOT NULL,
 requests INT UNSIGNED NOT NULL,
 FOREIGN KEY(tenant_id) REFERENCES wp_research_tenants(tenant_id)
) ENGINE=InnoDB;
CREATE TABLE wp_research_worlds (
 world_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY world_owner(world_id,tenant_id),
 UNIQUE KEY world_request(tenant_id,request_key),
 FOREIGN KEY(tenant_id) REFERENCES wp_research_tenants(tenant_id)
) ENGINE=InnoDB;
CREATE TABLE wp_research_jobs (
 job_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 world_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_json LONGTEXT NOT NULL CHECK(JSON_VALID(request_json)),
 manifest_json LONGTEXT NOT NULL CHECK(JSON_VALID(manifest_json)),
 request_signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status ENUM('queued','complete','failed','cancelled') NOT NULL DEFAULT 'queued',
 created_at BIGINT UNSIGNED NOT NULL,
 completed_at BIGINT UNSIGNED NULL,
 result_json LONGTEXT NULL CHECK(result_json IS NULL OR JSON_VALID(result_json)),
 result_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 result_signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(40) NULL,
 UNIQUE KEY tenant_request(tenant_id,request_key),
 INDEX worker_queue(status,created_at,job_id),
 INDEX tenant_created(tenant_id,created_at),
 FOREIGN KEY(world_id,tenant_id) REFERENCES wp_research_worlds(world_id,tenant_id),
 FOREIGN KEY(tenant_id) REFERENCES wp_research_tenants(tenant_id)
) ENGINE=InnoDB;

CREATE TABLE wp_research_dataset (
 seq BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 FOREIGN KEY(job_id) REFERENCES wp_research_jobs(job_id)
) ENGINE=InnoDB;

-- Unit balances are accounting credits, not bearer credentials or currency.
CREATE TABLE wp_research_billing_events (
 event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_kind ENUM('renewal','topup','usage') NOT NULL,
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 included_delta BIGINT NOT NULL,
 prepaid_delta BIGINT NOT NULL,
 waived_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
 operation VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY billing_idempotency(tenant_id,event_key),
 UNIQUE KEY global_payment_reference(event_key),
 INDEX billing_history(tenant_id,event_id),
 FOREIGN KEY(tenant_id) REFERENCES wp_research_tenants(tenant_id)
) ENGINE=InnoDB;
CREATE TABLE wp_research_capacity (
 scope_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 period_seconds INT UNSIGNED NOT NULL,
 window_no BIGINT UNSIGNED NOT NULL,
 requests INT UNSIGNED NOT NULL,
 units BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(scope_key,period_seconds)
) ENGINE=InnoDB;

CREATE TABLE wp_research_game_dataset (
 seq BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_alias CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 committed_day DATE NOT NULL,
 envelope_json LONGTEXT NOT NULL CHECK(JSON_VALID(envelope_json)),
 UNIQUE KEY world_day(world_alias,committed_day)
) ENGINE=InnoDB;

-- Shared, live operator-configurable capacity profiles. Zero stops admission.
CREATE TABLE wp_research_capacity_profiles (
 profile ENUM('standard','pro','global') NOT NULL,
 period_seconds INT UNSIGNED NOT NULL,
 max_requests INT UNSIGNED NOT NULL,
 max_units BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(profile,period_seconds)
) ENGINE=InnoDB;
INSERT INTO wp_research_capacity_profiles(profile,period_seconds,max_requests,max_units) VALUES
 ('standard',1,30,300),('standard',60,120,600),('standard',3600,1200,6000),('standard',86400,5000,30000),
 ('pro',1,40,800),('pro',60,120,2400),('pro',3600,3000,30000),('pro',86400,20000,150000),
 ('global',1,100,2000),('global',60,1000,10000),('global',3600,20000,200000),('global',86400,150000,1000000);

-- Isolated persistent research model, no gameplay schema or credentials.
CREATE TABLE wp_research_simulations (
 world_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 0,
 pin_json LONGTEXT NOT NULL CHECK(JSON_VALID(pin_json)),
 state_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 last_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 head_signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 FOREIGN KEY(world_id,tenant_id) REFERENCES wp_research_worlds(world_id,tenant_id)
) ENGINE=InnoDB;
CREATE TABLE wp_research_simulation_events (
 seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
 world_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision INT UNSIGNED NOT NULL,
 command_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_json LONGTEXT NOT NULL CHECK(JSON_VALID(event_json)),
 signature_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(world_id,revision),
 UNIQUE KEY simulation_command(world_id,command_sha),
 FOREIGN KEY(world_id) REFERENCES wp_research_simulations(world_id)
) ENGINE=InnoDB;

-- Permanent withdrawal tombstones prevent re-import of old signed batches.
CREATE TABLE wp_research_game_dataset_withdrawn (
 world_alias CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 withdrawn_at BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;

-- Durable incremental synchronization checkpoint in Research only.
CREATE TABLE wp_research_dataset_sync (
 stream_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 last_seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
 last_success_epoch BIGINT UNSIGNED NULL,
 last_run_epoch BIGINT UNSIGNED NULL
) ENGINE=InnoDB;

-- Stripe test-mode checkout and settlement, separate from gameplay.
CREATE TABLE wp_stripe_research_orders (
 order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 offer VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 plan ENUM('standard','pro') NOT NULL,
 mode ENUM('payment','subscription') NOT NULL,
 price_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 units BIGINT UNSIGNED NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 checkout_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 checkout_url VARCHAR(2048) NULL,
 customer_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 subscription_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 period_end BIGINT UNSIGNED NOT NULL DEFAULT 0,
 settled_at BIGINT UNSIGNED NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY tenant_checkout(tenant_id,request_sha),
 FOREIGN KEY(tenant_id) REFERENCES wp_research_tenants(tenant_id)
) ENGINE=InnoDB;
CREATE TABLE wp_stripe_research_receipts (
 payment_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 order_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(order_id) REFERENCES wp_stripe_research_orders(order_id)
) ENGINE=InnoDB;

CREATE TABLE wp_stripe_environment (
 singleton_id TINYINT UNSIGNED PRIMARY KEY,
 deployment_env ENUM('development','staging','production') NOT NULL
) ENGINE=InnoDB;
CREATE TABLE wp_stripe_research_holds (
 payment_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 reason VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(payment_id) REFERENCES wp_stripe_research_receipts(payment_id)
) ENGINE=InnoDB;

CREATE TABLE wp_research_checkout_rate (
 scope_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 minute_no BIGINT UNSIGNED NOT NULL,
 requests INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
