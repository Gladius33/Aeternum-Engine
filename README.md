# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status
Early development — iteration 0004. The PHP core includes in-memory and MariaDB event schedulers with per-world locking and transactional completion, plus transactional commands and durable idempotency receipts, and a deterministic replay journal. No HTTP API or playable game yet. See the [roadmap](ROADMAP.md) and CI results for the exact state.

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
