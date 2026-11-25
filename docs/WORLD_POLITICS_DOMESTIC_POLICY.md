# WP29A — domestic public policy domain slice

This is a deliberately headless first slice of WP29, not the full public policy workflow.
It pins fictional scenario assumptions and models proposals, externally verified
enactment, effective dates, finite duration, gross daily revenues/expenses, integer
treasury balance and gradual sector pressure changes. No prediction of actual
citizens or governments, no legal conclusion from a 49.3 Assembly adoption, and
no automatic government or regime collapse. A negative balance is possible and
does not represent a calibrated debt model. All coefficients remain hypothetical.

The authority interface must be implemented by trusted server code; it cannot be
constructed from client fields. The domain cannot certify a real promulgation.
Snapshot restoration requires a trusted external digest, pinned assumptions and
full deterministic ledger replay. The finite horizon is 3650 daily rows; numeric
bounds prevent overflow at the supported maximum scenario size.

Not yet implemented: MariaDB transactional adapter and receipts, actual
legislative/enactment evidence provider, world clock coordinator registration,
REST/OpenAPI, concessions through legally adopted amendments, macroeconomic
calibration, and multi-domain crash/recovery tests. No public endpoint exists.
WP29A cannot be treated as completed WP29.


## WP29B — durable standalone adapter candidate

Migration 017 creates game-owned InnoDB state, journal and receipt tables.
PdoDomesticPolicyStore persists the curated assumptions and their semantic
digest, restores the domain ledger by deterministic replay, and writes the
state, before/after journal and actor-scoped idempotency receipt atomically.
Every command requires verified read and operation permissions plus a strong
ETag and a receipt key. Replays recheck permissions and the world fence.

The world clock and fence schema are required. This adapter refuses all
mutations in clock-governed worlds until WP29D can pin and coordinate the
aggregate; only standalone test/scenario worlds may be written. Do not
enable a world clock on an existing domestic aggregate until that milestone.
No authenticated HTTP endpoint or concrete legal evidence verifier is added.
The DB tests cover isolated worlds, reconnection, rollback at receipt insert,
retries, stale revisions, permission revocation and integrity failures.
The journal is append-only at application level, not tamper-proof against
privileged SQL administrators. No scale or concurrent stress claim is made.


## WP29C/D/E — combined final candidate

* C: immutable simulated final-act registry, server-authorized publication,
  optional France 49.3 source snapshot and explicit Assembly-adoption check.
  Adoption alone is never final publication. The final text/review digests
  remain trusted curated inputs; ordinary French legislative navette,
  constitutional review and promulgation workflow are NOT implemented fully.
* D: migration 018 pins an optional domestic clock step at world bootstrap;
  a restarted worker cannot omit it. Reuses existing saga checkpoints and
  recovery; domestic worlds advance one day per tick, not a multi-day jump.
  REST is in-process, without public HTTP or publication/clock endpoint.
* E: optional pinned concessions and their daily cost, aggregate hypothetical
  production/employment indices, and cumulative fiscal summaries. No calibrated
  macroeconomic forecast, tax-base model or demographic research dataset.
  Concessions are pre-curated packages, not an interactive amendment system.
* Legacy WP29A/B snapshots without extended assumptions retain their shape.
  Standalone WP29B test authority remains available only outside coordinated
  enactment. New worlds need migrations 017 and 018 before bootstrap;
  existing domestic standalone worlds can be pinned at first clock seed,
  not automatically migrated into already running clock-governed worlds.

CI contains the previous domain/DB suite plus extended domain and coordinated
crash/recovery tests. The final candidate is not production-ready.
