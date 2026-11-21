# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status
Early development — iteration 0022. The PHP core includes in-memory and MariaDB event schedulers with per-world locking and transactional completion, plus transactional commands and durable idempotency receipts, a deterministic replay journal, and a transactional outbox with expiring delivery leases, trusted rule-set pinning and integer lazy production, and a reference economy with atomic resource transfers a persistent movement ledger, and timed resource-production upgrades, and two validated data-only reference economy packs with world membership and account-scoped command admission. Relational locations and immutable actor-owned entities are available through trusted provisioning. Bounded immutable scalar components support entity composition. Persistent application identities use Argon2id passwords. Persistent expiring and revocable opaque sessions store token hashes only. Initial read-only authenticated HTTP API for own entities and components; rate-limited login/logout routes available, no playable game yet. See the [roadmap](ROADMAP.md) and CI results for the exact state.

## Vision
Theme-agnostic persistent worlds, asynchronous actions, deterministic simulation, lazy resource production, transactional economy, composable entities, versioned game packs, multiplayer social features and scalable shared-hosting-compatible deployment.

Planned stack: PHP 8.4, Laravel, Vue/TypeScript, MariaDB/InnoDB, REST and bounded CRON/CLI. The core does not depend on Laravel.

## Run the prototype tests
PHP 8.4 is required; no dependencies are needed.
```sh
php tests/run.php
```
For MariaDB integration and concurrency tests, see [persistence](docs/PERSISTENCE.md). This is not an installation procedure for a playable game.

## Documentation
[Architecture](docs/ARCHITECTURE.md) · [ADRs](docs/adr/DECISIONS.md) · [Validation](docs/VALIDATION.md) · [Roadmap](ROADMAP.md) · [Changelog](CHANGELOG.md).

## Contributions
See [CONTRIBUTING.md](CONTRIBUTING.md), [CLA.md](CLA.md) and [SECURITY.md](SECURITY.md).

## License
Custom Aeternum Community & Commercial License v1.0 (ACCL-1.0); source-available, not OSI-approved open source. Non-commercial use is free with attribution. Commercial use is royalty-free below EUR 10,000 annual attributable revenue. At or above that threshold, the royalty is 10% of the entire annual attributable revenue, not only the excess. Reporting obligations apply. Required attribution: Powered by Aeternum Engine — Created by Sébastien TOUILLEUX.

The binding French [LICENSE.md](LICENSE.md) prevails over this informational summary. Contact the maintainer privately through GitHub for licensing inquiries.

Copyright © 2026 Sébastien TOUILLEUX. All rights not expressly granted are reserved.

Internal persistent fixed-window rate limiter available; HTTP wiring pending.

Actor-scoped economy HTTP command and balance routes available for explicitly configured worlds.

Trusted player provisioning initializes membership, settlement and economy resources; retries use stable command IDs.

Bounded CLI event worker: php bin/worker.php PACK WORLD.

Schema installation: php bin/migrate.php on a fresh database.

Trusted CLI account setup: php bin/provision.php PACK WORLD LOGIN (password on stdin).

Initial browser client at /play.html: sign in, inspect stock, settle and upgrade. Operator-provisioned accounts only.
