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
