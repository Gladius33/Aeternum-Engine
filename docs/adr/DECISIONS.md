# Architecture decisions

- ADR-001: modular monolith, independent domain core; framework confined to adapter.
- ADR-002: discrete events and lazy simulation rather than global ticks.
- ADR-003: UTC integer timestamps, stable sequence for ties; separate logical time and processing time.
- ADR-004: MariaDB atomic transaction and first-stage world lock; no claims of persistence for memory adapter.
- ADR-005: fixed-point integers, documented overflow bounds and retained production remainders.
- ADR-006: scoped world IDs, database constraints, server-side authorization.
- ADR-007: versioned validated game packs, no eval or user-provided executable code.
- ADR-008: journal and outbox with idempotent consumers, no external side effects in handlers.
- ADR-009: bounded CLI/CRON execution for shared hosting; optional dedicated workers.
- ADR-010: two reference games verify theme independence.
- ADR-011: regression and integration gates before merge.
- ADR-012: ACCL-1.0 unchanged, third-party license inventory and explicit CLA for external contributions.

- ADR-013: MariaDB event transactions lock world first, commit one event at a time, retain completed rows for idempotency, budget checks between transactions. No transaction spans external effects.

- ADR-014: world-locked server-time command intake with distinct durable command IDs, canonical payload comparison and exact prior receipts; pending due events block new commands. Follow-up events commit with the command or not at all. No external I/O inside handlers.

- ADR-015: commit journal entries with the same world transaction as state, use canonical scalar-map state digests and full follow-up schedule digests. Migration establishes an explicit checkpoint for any existing state; earlier history is not reconstructed. Replay uses caller-supplied original trusted handlers until version pinning is implemented; diagnostic replay holds the world lock and is bounded by entry count.

- ADR-016: outbox messages are committed with journal transitions, contain metadata only and use world/journal number as stable deduplication key. Workers claim with expiring random tokens; external delivery is at-least-once, never claimed exactly-once. No backfill of existing journal.

- ADR-017: pin world to a trusted application rule-set key derived from explicit ID/version/artifact digest; bound scheduler checks handler instance and stored identity. Legacy rows stay explicitly unbound until a tested migration policy exists. Digest is declared by trusted configuration, not proof of the PHP binary contents by itself.
- ADR-018: production uses integer smallest units, rational rate per interval, carry, and saturation; max capacity/rate 10^12 units and interval 2,592,000 seconds keep intermediate products within signed int64. Discard carry at capacity. Never multiply elapsed time until saturation has been ruled out.

- ADR-019: initial economy persists bounded scalar-map resource accounts with trusted immutable definitions, ledger lines typed by account/resource/reason and committed with journal. Transfers settle both balances before conserved debit/credit. Ledger effects are verified on replay. No ownership authorization or efficient relational balance storage yet.

- ADR-020: trusted contiguous resource upgrades strictly improve amount at fixed interval/capacity, cost and duration configured by rules; one pending per account/resource. Settle old rate at scheduled due time, retain fractional carry since denominator unchanged, activate new level. EventEffectsHandler is optional and uses typed ledger movements in persistent execution/replay; existing scalar event handlers remain compatible.

- ADR-021: API 1 game packs are bounded JSON data (not executable rules), loaded from application-approved bytes; exact-byte SHA-256 contributes to RuleSet identity. Runtime handler version compatibility must still be maintained separately. JSON Schema documents structure; the PHP loader is authoritative for cross-field and byte/type checks. PHP JSON parsing uses last-key-wins for duplicate object keys; producers must not emit duplicates.

- ADR-022: trusted membership administration and actor command admission share the world lock; actor identity is supplied only by a future authenticated adapter. Player command IDs are scoped by actor and request ID, command audit records retain both. Current membership is checked even on retry, private world state never returned in actor receipts, and read projections enforce due-event chronology. Ownership immutable until explicit audited transfer policy is designed.

- ADR-023: relational locations and entities use composite world-scoped foreign keys; trusted provisioning is immutable and idempotent under the world lock. Own projections require active membership. Administrative metadata is outside simulation replay until entity commands are designed.

- ADR-024: immutable component attachments use composite entity foreign keys and canonical scalar maps, max 64 components, 64 fields and 8192 encoded bytes per component. Trusted provisioning only, private reads; no dynamic simulation mutation yet.

- ADR-025: global random actor IDs and unique lowercase login, Argon2id hashes; registering never grants membership. Legacy actor IDs are not automatically linked. No public authentication route yet.

- ADR-026: 256-bit random opaque bearer tokens stored only as SHA-256 digests, absolute TTL 60–86400 seconds, idempotent revocation; resolve at request admission, no cancellation of in-flight requests.

- ADR-027: initial HTTP surface is read-only authenticated entity/component projection; unauthorized ownership and absent resources map to the same 404, invalid sessions to 401. No public credential intake before abuse controls.

- ADR-028: fixed-window operational rate limits use transactional hashed buckets; trusted server policy only. Denied requests do not increment counters. HTTP integration and cleanup pending.

- ADR-029: login/logout use POST, bounded body and peer/login fixed-window limits. Peer is REMOTE_ADDR unless trusted server rewrites it. No public registration yet.

- ADR-030: HTTP economy routes reuse actor transactions and pinned rules; domain conflicts share a generic 409. No implicit world provisioning.

- ADR-031: trusted provisioning uses actor-derived account/location/entity identifiers and stable resource command IDs. Multi-transaction workflow requires operator retries and coordination with revocation.

- ADR-032: CLI worker processes one explicit world with pinned rules, bounded batches and cooperative time budget. No implicit provisioning.

- ADR-033: forward-only trusted SQL migrations use SHA-256 ledger and advisory lock; implicit DDL commits require manual recovery of dirty state, never automatic replay.

- ADR-034: initial reference-player creation is operator-only CLI, passwords supplied through stdin rather than process arguments.

- ADR-035: expose configured world and owned-account discovery through an actor-scoped read API, not arbitrary world enumeration.

- ADR-036: verify the reference client against real MariaDB and the actual HTTP controller in Chromium; test-only HTTPS termination emulation does not certify production TLS.

- ADR-036 clarification: browser E2E invokes the bounded world worker to process due events; HTTP reads must not implicitly mutate simulation state.
