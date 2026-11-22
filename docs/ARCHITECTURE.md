# Architecture

Aeternum is a framework-independent PHP 8.4 domain engine, with a planned Laravel HTTP/CLI adapter, Vue/TypeScript frontend and MariaDB/InnoDB persistence. Use a modular monolith with ports and adapters, not mandatory services or daemons.

Game packs define resources, entities, actions, progression, localization, presentation and versioned rules. Core never depends on themes. Multiple games and worlds use explicit world IDs in repositories and authorization. Domain modules: Core, World, Entities, Economy, Actions, Progression, Interactions, Market, Social, Rules, Identity, Ranking, Monetization, Administration, SDK.

Simulation uses effective event timestamps, stable ordering by (due_at, sequence), server-authoritative commands, and lazy fixed-point production. Persist state, event journal and outbox atomically. Start with per-world SQL row locks and unique idempotency constraints; never advance a world past pending older events. Bound CRON batches, retain failures, and alert on backlog. External side effects only after commit through a transactional outbox. No arbitrary executable code in untrusted game packs; approved extensions are trusted code.

Iterations 0001–0012 implement in-memory and MariaDB scheduling with integer UTC epoch seconds, explicit world identity, fixed cutoff per batch and pure handler contract. The MariaDB adapter locks one world row per transaction and atomically commits state and completion; persisted event records retain identity. Commands now atomically mutate state, enqueue follow-up events and store durable receipts; pending due work blocks new commands. A journal captures committed command/event order and canonical state and follow-up digests; bounded diagnostic replay starts at the migration checkpoint. A transactional outbox records transition metadata and supports leased at-least-once handoff outside simulation transactions. Worlds may now pin an explicitly registered trusted rule artifact identity; legacy worlds remain unbound. A bounded integer production primitive supports lazy settlement. Reference economy commands persist bounded scalar-map balances and resource ledger movements in the journal transaction. Timed upgrades atomically pay resource costs and change production rates at logical completion; optional event ledger effects commit with the journal. A strict data-only JSON loader now validates reference packs and computes the content artifact digest. World membership and account ownership now gate actor-facing economy commands within the world transaction; receipts omit full state. Relational locations and immutable actor-owned entities share the world lock; they are administrative metadata, not yet journaled simulation state. Relational balance scaling, identity/session authentication, runtime artifact verification and external consumers remain future work. See [persistence](PERSISTENCE.md).

Deployment: standard HTTPS PHP application, MariaDB, short CRON jobs; cache optional; dedicated workers optional later. Shared-hosting approval and load validation are separate acceptance prerequisites.

Entity components are bounded immutable administrative scalar maps under the world lock; game-specific validation and mutable simulation components remain open.

Application identity is independent of world membership; registering does not grant world access. No public login endpoint until abuse controls and sessions exist.

Opaque sessions use operational time and SHA-256 token hashes. Session resolution is a request admission check; revocation does not cancel already admitted requests. No public login endpoint yet.

Initial HTTP adapter resolves opaque bearer sessions and exposes only own entity/component reads. The front controller requires server-indicated HTTPS and returns generic errors. Public login, economy commands, rate limiting and UI are future work.

Rate limiting uses independent hashed policy buckets and locked rows, without world locks. Fixed windows permit bursts at boundaries. Not yet wired to HTTP.

AuthHttpApi exposes POST login/logout with server-derived peer and per-login rate buckets. No trust in forwarded IP headers. Public registration and global throttling remain open.

Economy HTTP uses a trusted world-to-rules registry and existing actor transaction checks; clients cannot supply actor identity or rules. Commands are bounded and allowlisted.

PlayerProvisioner is a trusted multi-transaction administrative workflow, not atomic onboarding; retry identical arguments after interruption, and never invoke concurrently with revocation.

WorldWorker wraps the pinned scheduler for one configured world. Each event commits independently. Budget is cooperative, not a hard deadline; database waits can extend runtime.

DDL is not transactional in MariaDB. MigrationRunner records dirty markers before execution and verifies the immutable migration prefix under a connection-owned advisory lock.

Trusted provisioning CLI authenticates existing identities or registers new ones, then runs PlayerProvisioner. Do not offer this administrative tool over HTTP.

Initial static browser client uses memory-only bearer tokens and same-origin API; no client-side economy simulation. Vue/TypeScript migration remains open.

Discovery reads only accounts attached to active memberships in explicitly configured worlds; the returned metadata is advisory, and every economy operation still checks authorization independently.

Browser E2E uses a test-only loopback router outside public/ to invoke the actual front controller; production HTTPS enforcement is unchanged.

Browser test polling invokes the production CLI worker on the test world; HTTP queries retain their due-event barrier and do not advance simulation state.

Session maintenance deletes only rows expired at its operational clock, using one bounded statement and no world locks. Live revoked rows remain until expiry. A separate least-privilege maintenance database credential is recommended; application HTTP credentials need no DELETE grant.

Rate limiter retention uses a durable single-row operational clock floor (migration 014). Admissions take a shared guard lock before any bucket mutation; cleanup takes the exclusive guard lock before bounded deletion and raises the floor atomically when rows are removed. Seven-day retention is conservative relative to the maximum one-day fixed window. An admission with clock below the floor is denied even for a missing bucket. No world locks are acquired. Shared guard contention needs load measurement.

Authentication performs one password verification for syntactically valid, bounded login requests admitted by all three rate-limit buckets (peer, login and global verification). New hashes use Argon2id with 64 MiB, four iterations, one thread; unknown identities use a public dummy hash under the same policy. Legacy hash costs may differ. The global fixed-window quota mitigates distributed expensive verification requests but is not a hard concurrent-memory cap, and exhaustion may deny legitimate login.

Resource overview uses the same world transaction, ownership/membership checks, pinned rules and due-event barrier as the existing balance projection. It reads pending event metadata by world and event ID and checks its resource, account, resulting level and type; inconsistent internal state fails closed. Only level, due time and production amount are returned, not internal event IDs. The original balance API remains compatible.

Browser requests lock selection controls until completion. Explicit select values are used because disabled controls are excluded from FormData. HTTP 401 clears memory-only credentials and private projections.

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

0035 evidence: exact head 0e256178bec78e669a8cb577043a59cd688ba665 passed PHP/MariaDB (45002262563) and Chromium (45002262545), merged as 8c217774451eeb53606e834b65525c54db404d48.

0036: add read-only market projection, active requester and seller membership checks under the existing world lock, bytewise offer-ID cursor pagination (maximum 100 per page), public terms only, no state mutation. Pages reflect independently committed snapshots; not a stable snapshot across requests. Bounded reference state; relational market storage and HTTP/UI remain open. PHP and MariaDB tests added; CI pending.

0036 evidence: exact head 1d9abd77c8c338f31feead0afd44f9363735325a passed PHP/MariaDB (45002721036) and Chromium (45002721081), merged as 1bd7c18914a8410eb78ac5474c688d7d6ac87e89.

0037: expose authenticated GET /v1/worlds/{world}/economy/market and /market/{after} (50 terms per page), and allow the three market mutations through the existing economy command route. Shared economy admission limit 120/minute plus 20/minute per actor for market writes, including retries. All existing transactional authorization remains mandatory. Real MariaDB dispatcher tests cover sessions, membership, immutable terms, retry, capacity and quota. Reference-market lifetime limit and refund capacity restriction remain production blockers; operator-only onboarding, no public deployment or browser UI. CI pending.

0037 evidence: exact head 13a5007c4059a0621c84602736df0f4599644b3f passed PHP/MariaDB (45003152191) and Chromium (45003152334), merged as 65c2a32914d45e81feac81bfc51b8c4c31cb67f9.

0038: reference browser market panel uses the authenticated live API for paging and create/accept/cancel. Shared in-page command retry identity generates the offer ID once with its command ID, preserves pending payload across ambiguous failures and clears private market state on world change/logout. Successful mutations invalidate displayed stock and terms until reloaded; no optimistic balance changes. Industrial Chromium smoke creates, finds and cancels a real offer; multiplayer browser exchange and lost acknowledgement remain open. CI pending.

0038 review correction: preserve the original world and entire pending command; expose a dedicated in-session retry control independent of current selection or open offer list. The real Chromium industrial scenario drops the cancellation response after it commits, reloads the now-empty market, retries the saved command, and checks the identical durable receipt. Logout discards pending session state. CI pending on corrected head.

0038 accessibility correction: initial Chromium run 45003624969 failed locating the exact label of a nested select. Use explicit for/id associations for market resource, quantity and offer controls. Reproduced the original exact-label miss and verified the corrected labels/selection in local real Chromium. Bound market smoke action/response waits to 15 seconds; full corrected CI remains required.

0039 E2E: independent bearer sessions share a world but cannot authorize another actor's accounts or a foreign world's market. Market mutations flow through existing world lock, receipt and ledger. The browser never supplies trusted rule configuration.
