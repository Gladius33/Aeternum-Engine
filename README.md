# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status — M11 integrated; M12 security and resilience in progress (V1.0 not released)

Macro-iterations **M01–M11 are merged into main**: identity/onboarding,
version-pinned packs/SDK, persistent economy and strategic worlds, two reference
games, Vue client, multiplayer market/social features, simulated administration
and premium, lab operations/backup/restore. **M12 remains in qualification**
under [PR #52](https://github.com/Gladius33/Aeternum-Engine/pull/52);
M13 release acceptance has not started. The
[macro roadmap](docs/MACRO_ROADMAP_V1.md) and [G01–G09 gates](ROADMAP.md)
remain authoritative.

**This repository is an engine, not two products:** medieval and industrial
games under games/ are representative packs to validate reuse. Rules and
handlers must continue moving toward a generic, trusted plug-in boundary;
existing SettlementRules and IndustryRules in the core are documented
architectural debt. Optional scoring/protection/bot capabilities are specified
but **not implemented** in [the extension design](docs/OPTIONAL_GAMEPLAY_MODULES.md).

CI on PHP 8.4, MariaDB and Chromium is laboratory evidence, not external
hosting or release certification. Production registration requires approved
identity proof; never expose local testing bypasses. No real payments, outside
deployment or public production claim has been approved.

## Vision
Theme-agnostic persistent worlds, asynchronous actions, deterministic simulation, lazy resource production, transactional economy, composable entities, versioned game packs, multiplayer social features and scalable shared-hosting-compatible deployment.

Implemented laboratory stack: PHP 8.4, MariaDB/InnoDB, REST, bounded CRON/CLI and a progressively enhanced Vue 3/TypeScript browser client. Laravel remains a possible future adapter; the engine does not depend on it.

## Run the prototype tests

PHP 8.4 is required. Run `php tests/run.php` for dependency-free core regressions. For the MariaDB integration, concurrency and browser suites, consult [persistence](docs/PERSISTENCE.md), the GitHub Actions workflows and the tests in the selected commit. These are not installation instructions for a publicly accessible game. Do not put database credentials, activation tokens or recovery codes into logs.

## Documentation
[Architecture](docs/ARCHITECTURE.md) · [ADRs](docs/adr/DECISIONS.md) · [Validation](docs/VALIDATION.md) · [Roadmap](ROADMAP.md) · [Changelog](CHANGELOG.md).

## Contributions
See [CONTRIBUTING.md](CONTRIBUTING.md), [CLA.md](CLA.md) and [SECURITY.md](SECURITY.md).

## License
Custom Aeternum Community & Commercial License v1.0 (ACCL-1.0); source-available, not OSI-approved open source. Non-commercial use is free with attribution. Commercial use is royalty-free below EUR 10,000 annual attributable revenue. At or above that threshold, the royalty is 10% of the entire annual attributable revenue, not only the excess. Reporting obligations apply. Required attribution: Powered by Aeternum Engine — Created by Sébastien TOUILLEUX.

The binding French [LICENSE.md](LICENSE.md) prevails over this informational summary. Contact the maintainer privately through GitHub for licensing inquiries.

Copyright © 2026 Sébastien TOUILLEUX. All rights not expressly granted are reserved.


## Packs et mondes configurables (M02 intégrée)

Le registre de mondes approuvés et le SDK déclaratif à schémas typés sont documentés dans [docs/M02_SDK_AND_REGISTRY.md](docs/M02_SDK_AND_REGISTRY.md). Les mondes historiques restent épinglés aux octets exacts de leurs règles ; aucune migration silencieuse n'est autorisée. La validation d'une PR ne vaut pas recette de production.
