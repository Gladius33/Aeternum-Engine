-- WP28 existing constitutional worlds: enable certified Assembly requirements
-- only when the verified roster has been successfully installed.
ALTER TABLE wp_fr_constitutional_states
  ADD COLUMN IF NOT EXISTS parliament_required TINYINT(1) NOT NULL DEFAULT 0;
