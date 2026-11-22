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

- ADR-037: one bounded indexed deletion of expired sessions per maintenance invocation; do not delete live rows, including revoked-but-unexpired rows. Separate maintenance permissions from HTTP credentials. Parallel browser cases isolate their worlds; PR checks and main push checks remain mandatory.

- ADR-038: transactional rate-limit retention uses a durable operational clock floor and fixed guard-before-bucket lock ordering to prevent reset after stale bucket deletion. Retain seven days, cap windows at one day, and deny admission below the floor. Separate maintenance privileges; validate contention under load before V1.0.

- ADR-039: apply global admission before expensive password verification in addition to peer and login buckets; unknown well-formed identities receive a public dummy Argon2id verification with the same fixed policy as new accounts. Never authenticate an absent row. Accept fixed-window boundary and denial-of-service tradeoffs pending capacity measurement.

- ADR-040: expose stock and upgrade metadata in one actor-scoped snapshot using the existing world lock and due-event barrier. The pending event is verified against the exact world/account/resource and trusted next rule; no internal event identifiers or foreign state are exposed.

0030 evidence: exact head 94ff65f5fea6a745688d38de656c6919b597f493 passed PHP/MariaDB (44959669342) and Chromium (44959669335), merged as a0f1ade8c3e13dce9e36b4fa9e9cb0614e47c660.

0031: expose existing actor-authorized resource transfers in the reference UI using recipient account ID and integer units. Retry identity includes the full payload. HTTP integration tests verify exact debit/credit, retry and rejection; browser tests exercise rejected transfers against real API in both packs. Recipient discovery and successful browser multiplayer scenarios remain open. CI evidence pending.

0031 evidence: exact head ab917b7f281c24f1b47a6e9184327b5aeb1326dd passed PHP/MariaDB (44969850523) and Chromium (44969850487), merged as fae7b3d0a4ac7755c5b65a8e440cfbb0461d5bf3.

0032: provision two additional disposable medieval players in browser CI; exercise a successful transfer between independent Chromium contexts and reject foreign balance reads using the sender's real bearer. Run scenarios sequentially to avoid world-wide due-event barriers. Exact debit and idempotency remain covered by HTTP integration; browser credit assertion allows lazy production. CI evidence pending. Recipient discovery remains open.

0032 evidence: exact head 7f0cb2dcc4da5f2c551990298b447db4e4a7e0f4 passed PHP/MariaDB (44978838408) and Chromium (44978838426).

0033: authenticated GET /v1/worlds/{world}/recipients lists up to 100 other active account IDs in a configured world, with truncation flag. A single SQL statement enforces active requester and recipient membership; no login, balance or private metadata is returned. Shares discovery quota. This is a bounded directory, not transfer authorization; existing transactional checks remain mandatory. Browser integration and pagination remain open. Integration checks cover revoked requester/recipient, unknown world and unauthenticated access. CI pending.

0033 evidence: exact head e3cecf685a1c8e052b1e31b6ef143c196619b26b passed PHP/MariaDB (44988163480) and Chromium (44988163479).

0034: reference UI loads the authenticated recipient directory on demand, clears it on world change or logout, displays empty/truncated states and preserves manual entry and server-side transfer authorization. Chromium multiplayer test selects a real recipient; industrial scenario checks an empty directory. CI pending. Pagination remains open.

0034 evidence: exact head 34d2e8becc17d8cb1ea41ce23604d7cb9279ae13 passed PHP/MariaDB (44996381709) and Chromium (44996381681).

0035: implement internal actor-scoped fixed-lot barter offers with escrow, immutable terms, cancellation and atomic exchange using the existing world-locked command journal. Migration 015 adds ledger reasons. Tests cover authorization, retries, capacity rollback, replay and competing acceptances. No market HTTP/UI yet; 1,000 lifetime offer IDs per reference world, no expiry/partial fills, cancellation can fail if seller stock is full. CI pending.

- ADR-045: reference barter is an additive command extension with immutable offer IDs, reserved stock represented by open offers, and committed ledger effects. Old command semantics are unchanged; runtime rule artifact versioning remains open. All market mutations share existing actor authorization and world transaction; cancellation requires available capacity. Do not expose raw world state or enable market HTTP routes until bounded actor projection and abuse limits exist.

0035 evidence: exact head 0e256178bec78e669a8cb577043a59cd688ba665 passed PHP/MariaDB (45002262563) and Chromium (45002262545), merged as 8c217774451eeb53606e834b65525c54db404d48.

0036: add read-only market projection, active requester and seller membership checks under the existing world lock, bytewise offer-ID cursor pagination (maximum 100 per page), public terms only, no state mutation. Pages reflect independently committed snapshots; not a stable snapshot across requests. Bounded reference state; relational market storage and HTTP/UI remain open. PHP and MariaDB tests added; CI pending.

- ADR-046: read only committed immutable terms of open offers under the world lock, with active requester and seller membership; cursor pages are independently evaluated, not a cross-request snapshot. The projection does not process events or promise acceptance; transactional command authorization and due-event checks remain authoritative.

0036 evidence: exact head 1d9abd77c8c338f31feead0afd44f9363735325a passed PHP/MariaDB (45002721036) and Chromium (45002721081), merged as 1bd7c18914a8410eb78ac5474c688d7d6ac87e89.

0037: expose authenticated GET /v1/worlds/{world}/economy/market and /market/{after} (50 terms per page), and allow the three market mutations through the existing economy command route. Shared economy admission limit 120/minute plus 20/minute per actor for market writes, including retries. All existing transactional authorization remains mandatory. Real MariaDB dispatcher tests cover sessions, membership, immutable terms, retry, capacity and quota. Reference-market lifetime limit and refund capacity restriction remain production blockers; operator-only onboarding, no public deployment or browser UI. CI pending.

- ADR-047: market HTTP uses the bounded economy adapter and pinned configured world registry, with opaque sessions and an additional actor-scoped mutation quota. Cursor is a validated ASCII path segment; page length is server-controlled. Clients provide immutable offer IDs and retry IDs. Reference lifetime cap remains unsuitable for unbounded public onboarding.

0037 evidence: exact head 13a5007c4059a0621c84602736df0f4599644b3f passed PHP/MariaDB (45003152191) and Chromium (45003152334), merged as 65c2a32914d45e81feac81bfc51b8c4c31cb67f9.

0038: reference browser market panel uses the authenticated live API for paging and create/accept/cancel. Shared in-page command retry identity generates the offer ID once with its command ID, preserves pending payload across ambiguous failures and clears private market state on world change/logout. Successful mutations invalidate displayed stock and terms until reloaded; no optimistic balance changes. Industrial Chromium smoke creates, finds and cancels a real offer; multiplayer browser exchange and lost acknowledgement remain open. CI pending.

- ADR-048: reference market UI uses the existing bearer session and single in-page retry slot for all commands. The create offer ID is generated once with the request ID. No automatic retries across reload/logout and no optimistic stock mutation; server admission remains authoritative. Clear market and balances after successful mutation, require explicit fresh reads.

0038 review correction: preserve the original world and entire pending command; expose a dedicated in-session retry control independent of current selection or open offer list. The real Chromium industrial scenario drops the cancellation response after it commits, reloads the now-empty market, retries the saved command, and checks the identical durable receipt. Logout discards pending session state. CI pending on corrected head.

- ADR-048 amendment: pending retry stores its original world and command body and can be submitted explicitly even after the offer disappears or selection changes. Do not derive retry identity from the refreshed list.

0038 accessibility correction: initial Chromium run 45003624969 failed locating the exact label of a nested select. Use explicit for/id associations for market resource, quantity and offer controls. Reproduced the original exact-label miss and verified the corrected labels/selection in local real Chromium. Bound market smoke action/response waits to 15 seconds; full corrected CI remains required.

- ADR-049: require two independent browser sessions to cover the complete marketplace exchange, in addition to lower-level SQL concurrency tests. The client must use server-authoritative terms and preserve the transactional receipt on duplicate accept; a closed offer disappears from public projection. This is E2E evidence, not proof of production capacity or public onboarding.
