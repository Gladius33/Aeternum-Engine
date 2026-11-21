# Architecture

Aeternum is a framework-independent PHP 8.4 domain engine, with a planned Laravel HTTP/CLI adapter, Vue/TypeScript frontend and MariaDB/InnoDB persistence. Use a modular monolith with ports and adapters, not mandatory services or daemons.

Game packs define resources, entities, actions, progression, localization, presentation and versioned rules. Core never depends on themes. Multiple games and worlds use explicit world IDs in repositories and authorization. Domain modules: Core, World, Entities, Economy, Actions, Progression, Interactions, Market, Social, Rules, Identity, Ranking, Monetization, Administration, SDK.

Simulation uses effective event timestamps, stable ordering by (due_at, sequence), server-authoritative commands, and lazy fixed-point production. Persist state, event journal and outbox atomically. Start with per-world SQL row locks and unique idempotency constraints; never advance a world past pending older events. Bound CRON batches, retain failures, and alert on backlog. External side effects only after commit through a transactional outbox. No arbitrary executable code in untrusted game packs; approved extensions are trusted code.

Iterations 0001–0004 implement in-memory and MariaDB scheduling with integer UTC epoch seconds, explicit world identity, fixed cutoff per batch and pure handler contract. The MariaDB adapter locks one world row per transaction and atomically commits state and completion; persisted event records retain identity. Commands now atomically mutate state, enqueue follow-up events and store durable receipts; pending due work blocks new commands. A journal captures committed command/event order and canonical state and follow-up digests; bounded diagnostic replay starts at the migration checkpoint. Rule pinning and outbox remain future work. See [persistence](PERSISTENCE.md).

Deployment: standard HTTPS PHP application, MariaDB, short CRON jobs; cache optional; dedicated workers optional later. Shared-hosting approval and load validation are separate acceptance prerequisites.
