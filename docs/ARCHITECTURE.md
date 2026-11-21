# Architecture

Aeternum is a framework-independent PHP 8.4 domain engine, with a planned Laravel HTTP/CLI adapter, Vue/TypeScript frontend and MariaDB/InnoDB persistence. Use a modular monolith with ports and adapters, not mandatory services or daemons.

Game packs define resources, entities, actions, progression, localization, presentation and versioned rules. Core never depends on themes. Multiple games and worlds use explicit world IDs in repositories and authorization. Domain modules: Core, World, Entities, Economy, Actions, Progression, Interactions, Market, Social, Rules, Identity, Ranking, Monetization, Administration, SDK.

Simulation uses effective event timestamps, stable ordering by (due_at, sequence), server-authoritative commands, and lazy fixed-point production. Persist state, event journal and outbox atomically. Start with per-world SQL row locks and unique idempotency constraints; never advance a world past pending older events. Bound CRON batches, retain failures, and alert on backlog. External side effects only after commit through a transactional outbox. No arbitrary executable code in untrusted game packs; approved extensions are trusted code.

Iteration 0001 implements only an in-memory scheduling prototype with integer UTC epoch seconds, explicit world identity, fixed cutoff per batch and pure handler contract. The scheduler instance is not a persistent or multi-process transaction boundary. Next: MariaDB adapter, persistent sequence, commands and integration tests.

Deployment: standard HTTPS PHP application, MariaDB, short CRON jobs; cache optional; dedicated workers optional later. Shared-hosting approval and load validation are separate acceptance prerequisites.
