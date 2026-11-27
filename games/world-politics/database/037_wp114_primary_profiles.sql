-- Extended masks preserve all existing canonical profile values.
ALTER TABLE wp_universe_slots MODIFY domestic_programme VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'manual';
ALTER TABLE wp_universe_programme_actions MODIFY programme VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
ALTER TABLE wp_universe_programme_direct MODIFY programme VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
