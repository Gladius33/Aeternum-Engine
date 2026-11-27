-- WP105 unresolved national succession must not be claimed.
ALTER TABLE wp_universe_slots ADD COLUMN closed_on DATE NULL;
