-- Existing universes retain their original pinned slot roster.
ALTER TABLE wp_universes ADD COLUMN roster_version VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'wp102-pilot-v1';
