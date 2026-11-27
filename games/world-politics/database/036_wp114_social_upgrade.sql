-- Explicit social model upgrade journal entries; no automatic data rewrite.
ALTER TABLE wp_domestic_journal MODIFY COLUMN operation ENUM('propose','enact','clock','crisis','corporation','model_upgrade') NOT NULL;
