# World Politics — WP80 alpha release qualification

Scope: private headless alpha, canonical France investiture 2027. This is NOT a production release or a claim of comprehensive real-world simulation. No UI and no merge into generic engine.

## Acceptance gates

- Fresh dedicated MariaDB installation with migrations 001–025 (014 excluded: maintenance-only).
- All three GitHub jobs green on the exact revision: headless contract, isolated MariaDB, real HTTP.
- Score, deterministic twenty-year synthetic horizon, world-clock recovery, terminal freeze, session scope and SQL integrity regressions.
- Offline `WP_RELEASE_AUDIT=YES php games/world-politics/bin/audit-campaign.php WORLD_ID` using dedicated read-only database credentials; refuses pending/unranked worlds. It verifies domestic, diplomacy, opinion, organizations, economy and seven institutional states using their existing restore rules and recomputes the committed score. It is not a comprehensive journal/receipt, security or production-capacity audit.

## Additional qualification (WP87)

The read-only audit additionally recomputes the experimental household account on worlds with the pinned historical reference; the report includes its SHA-256 digest and committed window. This check does not validate economic calibration or authorize an official leaderboard. Existing worlds without a household reference are not backfilled.

## Bounded operator maintenance

With dedicated credentials limited to the session and HTTP quota tables, run
`WP_ALPHA_MAINTENANCE=YES php games/world-politics/bin/prune-alpha.php`.
At most 500 expired sessions and 500 conservatively obsolete rate-limit
buckets are deleted per invocation. The legacy mixed-duration quota schema
requires a conservative cutoff; this is not a storage capacity guarantee.
Never run with generic engine credentials. This does not prune world journals
or replace a restore drill.

## Existing databases

Backup and restore-test first, stop writers and recover pending ticks; apply migration 025 using a dedicated migration account. Do not use fresh installer against a populated schema. DDL is not globally atomic. Old saves retain score_enabled=0; no silent leaderboard backfill. Verify schema, reopen only after smoke tests; restore backup if migration fails. No database migration has been applied to any user system.

## Grouped finalization — partial delivery WP88–WP97

Rolling tax sensitivities and scenario extrema are diagnostics only; future commitment maturity buckets reconcile fictional outstanding amounts without booking them. Password rotation authenticates the current secret and revokes all existing sessions; this is not account recovery or MFA. No claim of full completion of the ten work packages.

## Outstanding release blockers beyond the private alpha

- Real household disposable income per UC calibration, uncertainty and fair official competition; current ranking experimental.
- Curated historical programme content and mechanical programme effects (creation-time candidate identity selection is implemented), playable foreign constitutional systems, independent upper chambers and presidential races, interim governments.
- Real international obligation/creditor schedules, guarantees, enforceable accounting and diplomatic calibration; synthetic fixtures cannot substitute for contract inventories.
- Corrective budgets and autonomous certified legislative path (ordinary annual series 2028–2047 implemented for new canonical worlds) (the 2028 legal-to-fiscal activation is covered by an isolated DB regression), national service law and administrative timing; no tactical security simulation.
- Capacity and multi-node stress, account recovery, MFA, invite rotation, log/alerting, backup restoration in target environment, independent security review, dedicated repository and versioned engine dependency. No deployment or commercial readiness claimed.
