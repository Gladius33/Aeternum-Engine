-- WP27: per-world durable write-admission barrier.
-- This is a World Politics game-owned migration; never apply to generic Aeternum main.
-- Existing worlds must be quiesced, audited and backfilled explicitly before enabling writes.
CREATE TABLE IF NOT EXISTS wp_world_mutation_fences (
  world_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state ENUM('idle','advancing') NOT NULL DEFAULT 'idle',
  pending_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  target_day DATE NULL,
  controller_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
  generation BIGINT UNSIGNED NOT NULL DEFAULT 0,
  modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (world_id),
  CONSTRAINT wp_mutation_fence_world_fk FOREIGN KEY(world_id)
    REFERENCES wp_world_clock(world_id),
  CONSTRAINT wp_mutation_fence_consistency CHECK (
    (state='idle' AND pending_key IS NULL AND target_day IS NULL AND controller_id IS NULL)
    OR
    (state='advancing' AND pending_key IS NOT NULL AND target_day IS NOT NULL AND controller_id IS NOT NULL)
  )
) ENGINE=InnoDB;
