-- WP31: immutable reference payloads pinned by world at creation.
CREATE TABLE IF NOT EXISTS wp_reference_bundles (
 bundle_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 payload_json LONGTEXT NOT NULL CHECK(JSON_VALID(payload_json)),
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
ALTER TABLE wp_alpha_worlds ADD COLUMN IF NOT EXISTS reference_bundle_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;
