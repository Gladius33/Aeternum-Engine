-- WP50: append scenario-only decision operation to existing journal.
ALTER TABLE wp_domestic_journal MODIFY COLUMN operation ENUM('propose','enact','clock','crisis') NOT NULL;
