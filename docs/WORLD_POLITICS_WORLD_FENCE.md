# WP27 — Command admission, durable world fence and crash-safe recovery

## Status, safety scope and non-negotiable constraints

This game-owned code is on the isolated `feat/world-politics-wp01-identity-government` branch. **Do not merge it into the generic Aeternum Engine main branch.** There is no frontend and no running HTTP service.

WP27 solves one concrete concurrency risk in the world-clock saga: previously, while a coordinated day advancement had already moved some domain aggregates, independent cabinet, diplomatic, organizational, economic and AI writers could still mutate the same world's intermediate state.

A world configured with `wp_world_clock` now has an **obligatory durable command-admission barrier**. The barrier is not a network session lock. It is a MariaDB InnoDB row for each game world, referenced by all game-owned persistent domain adapters.

## Database design

Apply `database/013_world_mutation_fences.sql` after migration 007. The table `wp_world_mutation_fences` stores:

- `world_id` — dedicated primary key, foreign key to the game world's authoritative clock;
- `state` — `idle` or `advancing`;
- `pending_key` — fingerprint of the authorized world-clock command, never the raw user's key;
- `target_day` — target date of the game world;
- `controller_id` — authenticated service identity owning the pending transition;
- `generation` — monotonic counter for transition/fence changes;
- `modified_at` — audit-friendly timestamp, **not** a lease expiry.

A crash does **not** automatically expire the barrier. The transition remains blocked until a trusted worker successfully resumes all steps and publishes the new day.

### Ordering guarantee

Every domain write:

1. starts its own PDO transaction;
2. issues `SELECT ... FOR UPDATE` on the world's fence row **before** locking the domain aggregate and before replaying idempotency receipts;
3. checks that the fence and `wp_world_clock` agree;
4. admits ordinary writes only while the fence is idle, or admits **exactly one privileged `clock` action** if the actor matches the pending controller and the game-day matches the pending target;
5. commits the domain snapshot, journal and receipt as before.

The coordinator starts with the same fence row lock, then locks the authoritative `wp_world_clock` row. It writes both pending-intent and fence state **inside one InnoDB transaction**. The end-of-day publication also clears the pending intent and the fence inside one transaction.

**Important:** the coordinator must NOT hold the fence row lock across the independent domain clocks. It deliberately commits the pending fence first, so individual domain stores can acquire that row lock and complete their authenticated `clock` operations without a cross-connection deadlock.

A writer already in progress before the coordinator acquires the lock commits or aborts first. Once the coordinator commits the pending intent, subsequent writers are rejected. This serializes admission versus worldwide time progression without locking all domain rows for the entire saga.

### Protection coverage

- political roster creation, nominations and other authenticated game political decisions;
- diplomacy proposals, responses and automated decisions;
- international organization membership, voting and AI decisions;
- economy proposals and country-specific authorization;
- country-specific institutional decisions and clocks;
- French constitutional acts, spontaneous censure, confidence and day progression;
- socioeconomic assessments and bootstrap configurations;
- French parliamentary sitting calendar scheduling;
- trusted sanction bridge outbox acknowledgments;
- initial creation of domain aggregates by a trusted scenario service.

The exact authorization of each action is still evaluated by the corresponding domain store; **the fence does not grant any permission**. Having the controller's actor ID is insufficient to perform an unrelated government, commercial or player action while a tick is pending.

Pending worlds with no `wp_world_mutation_fences` row, missing WP27 migration or inconsistent clock/fence flags fail closed. Legacy standalone domains without a world clock remain separately testable, but are **not guaranteed to have worldwide coordination**.

### Durable recovery without the original HTTP receipt key

`WorldClockCoordinator::resumePending(world)` reads the original **hashed** pending command identifier and target day from MariaDB, revalidates the trusted scheduler's authority and continues the same saga. It returns `null` if there is nothing to recover.

`WorldTickRecoveryWorker::runOnce(maxWorlds)` is a bounded **internal service-side** queue scanner (1–100 worlds per batch). It accepts a trusted coordinator factory so each game world can be provisioned with its own constitution, registry, national roster and service account. It is not a publicly exposed endpoint.

No server scheduler or background service is automatically deployed by this code. The host's operator must provision and run the worker, monitor its results and handle domain failures. A blocked world retains its pending intent while other worlds may continue.

### Explicit limitations and next steps

This WP27 barrier is a database/application-level solution for cooperating game adapters, **not** a globally atomic distributed transaction. External writers that bypass the adapters or have unrestricted database write permissions may circumvent it; production database roles and audit triggers must be restricted and tested. Reads may see a partly progressed saga, but the authoritative world-clock state explicitly reports `advancing` until coherent publication.

A trusted scheduler can still possess command-level clock capabilities during pending time. Additional cryptographic/domain binding of specific per-subsystem command IDs and checkpoints, optimistic generation fencing in downstream workers and full outbox admission verification are future hardening tasks.

Before scaling to many game worlds: test transactional isolation levels, deadlock retries, worker crashes during every checkpoint, large-world throughput, concurrent queues, telemetry, backups and migration rollback.

### Upgrading existing worlds

**Never apply a blind backfill while games are running.** Freeze all game writers and clock workers, back up the database, install migration 013 and examine active transitions.

The optional `database/014_wp27_backfill_coherent_worlds_maintenance_only.sql` inserts an idle fence only for existing clocks with no pending intent. It leaves all non-coherent worlds un-fenced and blocked for individual review. The operator query at the bottom must return **zero rows** before unfreezing.

Reopening an old world whose clock was previously stuck requires reconstructing its controller and pending target from trusted receipts and checkpoints, rather than forging an idle fence.

### Test coverage

`tests/wp27-world-mutation-fence-db.php` uses two independent MariaDB PDO connections to demonstrate actual InnoDB row lock contention, rollback safety, pending-writer denial, exact scheduler/target exemptions, recovery by the original controller, world isolation and corrupt clock/fence fail-closed behavior.

`tests/wp25-fr-parliament-world-clock-db.php` now exercises WP27 inside the **real multi-domain world saga**: independent diplomacy, organization, economy and French Government commands are rejected during partial progression; a bounded trusted worker recovers the pending world without its original client receipt key; final coherent publication reopens admission to ordinary game commands.

Other existing domain, REST, French constitutional, economic, diplomatic and outbox tests remain on the same GitHub Actions workflow.

**Verdict:** WP27 is a technically meaningful concurrency and crash-recovery milestone, not a completed production orchestrator or a publicly playable backend.
