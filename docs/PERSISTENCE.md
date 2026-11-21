# MariaDB persistence (iteration 0002)

Requires 64-bit PHP 8.4, PDO MySQL with native prepares and exceptions, and MariaDB/InnoDB. Apply the numbered SQL files in database/migrations/ in order, once each to a new database with a migration account. Use an application account with only required SELECT/INSERT/UPDATE permissions on these tables. Never expose DB credentials in Git.

Use PdoScheduler::initialize() to provision a world, schedule() to enqueue immutable events, processDue() to process due work and state() to read the state. The caller supplies trusted world IDs and pure handlers; this is not an authenticated public API.

Every write obtains the world row lock first and commits the sequence or state and event completion in the same transaction. One event per transaction limits lock duration and makes failures retryable. Duplicate IDs are compared by content even after completion. The time budget is cooperative between transactions; lock waits/handler runtime may exceed it. Configure a bounded DB lock timeout and short handlers; retry failed calls after reconnect, not arbitrary external effects. A lost commit response must be reconciled from durable state and IDs. A replay journal is available after migration 003 and an outbox after migration 004.

Tests: use a dedicated EMPTY disposable database (the test refuses a nonempty one). Set AETERNUM_TEST_DSN, AETERNUM_TEST_USER, AETERNUM_TEST_PASSWORD and run php tests/integration.php. It checks reconnect persistence, isolation, rollback/retry and concurrent duplicate submissions and processing using independent PHP subprocesses. Never run against production.

## Command intake (0003)
Apply 002_commands.sql after 001_core.sql. PdoScheduler::execute accepts a trusted Command and pure CommandHandler, returns a durable CommandReceipt. A retry with matching ID/type/payload returns the original state snapshot and planned event IDs; conflicting requests fail. A command cannot overtake any due event, and state/receipt/follow-ups commit together. Follow-up IDs must be unique, in the same world, and due no earlier than the authoritative effective time. Command IDs must be bound to authenticated actors by future API adapters; receipts may contain private world state and must never be sent to untrusted clients directly. Use application SELECT/INSERT/UPDATE only. No external payments or notifications inside transactions.

## Journal and diagnostic replay (0004)
Stop all writers and take a backup before applying 003_journal.sql; MariaDB DDL is not transactional as a whole. Existing worlds checkpoint their state and watermark; only subsequent committed entries are replayable. Newly provisioned worlds start from an empty checkpoint. Each state transition and its journal record commit atomically. PdoScheduler::verifyReplay receives the original pure command/event handlers and an explicit limit (default 10,000 entries), returns count/effective time/state digest or throws on divergence. It holds the world row lock during replay: run only in maintenance, not an HTTP request. Follow-up event definitions and state are compared, but external direct scheduling is not independently replayed. Digests detect divergence, not malicious DB administrator rewrites. Rule identity pinning is available after migration 005; rule upgrade policy and large-journal checkpoint rotation remain open.

## Transactional outbox (0005)
Apply 004_outbox.sql with writers stopped. Existing journal entries are not backfilled. New journal transitions insert an envelope in the same transaction. It includes schema, kind, ID, type and effective time only, not private state or command payload. PdoOutbox::claim uses an operational UTC clock (SystemClock in production; keep hosts synchronized), not simulation time, and returns one expiring lease. Perform external I/O after claim commits, pass idempotencyKey() to a deduplicating consumer, then acknowledge. On failure release with bounded delay; if a worker dies, the lease expires. A stale token cannot acknowledge or release. A worker may send successfully and die before acknowledgement, so delivery is at least once and consumers must deduplicate durably. Concurrent delivery is not globally ordered. No live messaging/payment integration, pruning or dead-letter policy yet.

## Rule binding (0006)
Apply 005_rules.sql with writers stopped. Existing worlds retain the legacy key; new trusted application code should construct a RuleSet with ID, immutable version and verified artifact digest, register it once, and supply it to PdoScheduler. The scheduler rejects mismatched persisted identities and alternate handler instances. This is not a PHP sandbox or an automatic code hash: the application is responsible for verifying the artifact, registering deterministic immutable handlers and preserving older artifacts. No live upgrade or legacy conversion is implemented. Outbox metadata access is internal and not an authorization boundary.

## Resource ledger (0007)
Apply 006_resource_ledger.sql. CommandResult movements are committed atomically alongside the journal, state, receipt and outbox. Replay compares persisted ledger lines with deterministic handler results. Existing entries without movements stay empty. EventHandler remains compatible with scalar state; after iteration 0008 EventEffectsHandler may also return typed ledger effects. The ledger is internal diagnostic data, not a public accounting API.

## Event ledger effects (0008)
Apply migration 007 before enabling upgrade commands. Persistent event resolution and replay use EventEffectsHandler::resolve when available, otherwise the scalar EventHandler::apply. Effects are inserted atomically; no network calls are permitted. In-memory scheduler observes state only. No upgrade cancellation or refunds yet.

## Actor boundary (0010)
Apply 008_memberships.sql. PdoMemberships is a trusted admin service; it must never be directly exposed as a player route. Existing worlds/accounts are not automatically assigned to anyone. Explicitly grant active membership and assign account IDs. executeForActor requires pinned economy rules and an identity derived from trusted authentication, not a value asserted by the client. It checks membership/account ownership under the same world row lock as mutations and stores actor/request metadata. A revoked actor cannot retrieve old receipts until reactivated. balanceForActor exposes only an owned account projection and blocks behind due events. The raw scheduler methods and world state are trusted internal interfaces. This is not a complete authentication system; authorization endpoints, admin audit log, rate limiting and session lifecycle remain open.

## Entities (0011)
Apply 009_entities.sql with writers stopped. PdoEntities::addLocation and provision are trusted administrative calls; own requires an authenticated actor supplied by the future adapter. Location and entity IDs are immutable within a world. No movement, component simulation, public provisioning or journal replay of administrative metadata yet.

## Components (0012)
Apply 010_components.sql. Trusted attach is immutable and idempotent; ownComponent requires an active authenticated owner. Components are administrative metadata outside simulation replay. No public attachment endpoint.

## Identity (0013)
Apply 011_identity.sql. PHP must support PASSWORD_ARGON2ID. PdoIdentity is an internal registration/authentication service, not a public endpoint. Duplicate login raises a database exception; adapters must map failures safely. No password reset or session lifecycle yet.

## Sessions (0014)
Apply 012_sessions.sql. PdoSessions uses an operational clock, never simulation time. Protect returned bearer tokens, never log them; transport must use HTTPS. No cleanup job or public login route yet.

## HTTP (0015)
Run composer install and configure the HTTPS web server document root to public/ with /v1/ routes rewritten to index.php and Authorization forwarded. Supply AETERNUM_DSN, AETERNUM_DB_USER and AETERNUM_DB_PASSWORD via server environment. GET /v1/worlds/{world}/entities/{entity} and its /components/{component} suffix require Authorization: Bearer TOKEN. Tokens are issued only by trusted internal service. Never expose configuration or logs; deployment and rate limiting remain unverified. This is not a playable installation.

## Rate limiting (0016)
Apply 013_rate_limits.sql. Use operational clock and trusted bucket keys. Retention and HTTP integration are not yet implemented; do not expose arbitrary bucket creation to clients.

## Authentication HTTP (0017)
POST /v1/auth/login accepts JSON login/password and returns token and expires_in; POST /v1/auth/logout uses the bearer token. Configure HTTPS and trusted REMOTE_ADDR. Use trusted provisioning for accounts. Do not log credentials or tokens. Cleanup, global limits and transport tests remain open.

## Economy HTTP (0018)
Configure AETERNUM_MEDIEVAL_WORLD for an existing medieval world. POST /v1/worlds/{world}/economy/commands takes id,type,payload JSON; GET /v1/worlds/{world}/economy/balances/{account}/{resource}. Bearer token required. World and account provisioning remain trusted operations.

## Player provisioning (0019)
Trusted operators call PlayerProvisioner::provision with an existing identity, world ID and validated GamePack. Retry identical arguments if interrupted. This grants membership and must never be exposed directly to players; coordinate with revocation. CLI and public onboarding remain open.

## Worker (0020)
With the database environment configured, run php bin/worker.php reference-medieval WORLD. Schedule via CRON after provisioning. Default 100 events and 5000 ms cooperative budget; individual database waits may extend runtime. Exit 1 on failure. Do not expose bin/ through the web root.

## Migration runner (0021)
Back up and stop application traffic before php bin/migrate.php. Use a fresh database for initial install; existing schemas without a ledger require explicit operator migration planning. A dirty entry signals possible partial DDL: restore a verified backup or reconcile manually. Do not delete dirty markers to bypass the guard. SQL files use simple semicolon-delimited DDL without routines or quoted semicolons.

## Provisioning CLI (0022)
After migration, use php bin/provision.php reference-medieval WORLD LOGIN and supply a password on stdin (avoid shell history and terminal echo). The JSON output includes account and entity identifiers. Retry identical credentials and world after interruption. This trusted administrative operation can reactivate membership; never expose it publicly.

Serve public/ as the HTTPS document root, rewrite /v1/ URLs to index.php and open /play.html. Enter identifiers returned by provisioning. Tokens and pending retry IDs live only in page memory; reload loses them. Browser E2E pending.

Configure AETERNUM_MEDIEVAL_WORLD and AETERNUM_INDUSTRIAL_WORLD to match provisioned world IDs. Use reference-medieval and reference-industrial as CLI pack arguments. The play client discovers active owned accounts automatically.

Browser CI uses a disposable schema and public/ document root with a dedicated loopback test router. Never deploy tests/ or expose the test server. Production must enforce HTTPS with its actual web server.

## Session cleanup (0026)
Schedule `php bin/maintenance.php 500` with a dedicated CLI database credential using the same environment variable names as the worker (separate values). Grant only the session-table SELECT and DELETE privileges necessary for cleanup; do not add DELETE to the web credential. Limit 1–1000 (one statement per invocation); expired rows are selected by expires_at and token_hash, including revoked rows only once expired. Run regularly with synchronized UTC clocks; repeat batches via CRON, not an unbounded loop. The JSON count is safe to log; no token is logged. Connection errors or lock timeout exit nonzero. Rate-limit retention is separate future work.
