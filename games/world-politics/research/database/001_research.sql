-- Dedicated research database only. Never install as a gameplay migration.
CREATE TABLE wp_research_tenants (
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
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
CREATE TABLE wp_research_jobs (
 job_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 tenant_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
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
 FOREIGN KEY(tenant_id) REFERENCES wp_research_tenants(tenant_id)
) ENGINE=InnoDB;
