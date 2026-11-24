-- WP27 OPTIONAL OPERATOR-INITIATED BACKFILL. NEVER RUN WHILE WRITERS ARE ACTIVE.
-- (1) Stop player/API writers and all world-clock and outbox workers.
-- (2) Snapshot/backup dedicated World Politics MariaDB.
-- (3) Apply 013_world_mutation_fences.sql first.
-- (4) Audit each un-fenced world. This statement deliberately handles ONLY
--     coherent worlds; pending worlds remain blocked for manual recovery.
-- (5) Verify no pending world is left un-fenced before re-enabling services.
-- Never run against generic Aeternum Engine tables.
INSERT INTO wp_world_mutation_fences(world_id,state,generation)
SELECT c.world_id,'idle',0
FROM wp_world_clock AS c
LEFT JOIN wp_world_mutation_fences AS f ON f.world_id=c.world_id
WHERE f.world_id IS NULL
  AND c.pending_day IS NULL AND c.pending_key IS NULL;

-- OPERATOR CHECK: This query MUST return no rows before unfreezing.
-- All rows returned require an individually audited recovery procedure.
SELECT c.world_id,c.committed_day,c.pending_day,c.pending_key
FROM wp_world_clock AS c
LEFT JOIN wp_world_mutation_fences AS f ON f.world_id=c.world_id
WHERE f.world_id IS NULL
ORDER BY c.world_id;
