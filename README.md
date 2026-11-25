# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status — F01–F06 merged; F07 release-candidate qualification

Aeternum is a generic persistent browser-game engine with three playable
reference packs: medieval settlements, industrial networks and Guild
Caravans. The runtime composes immutable manifests with explicitly
reviewed PHP command/event modules. It includes transactional economy,
scoring, research, expeditions, multiplayer market/social features,
non-destructive guild challenges, mutual truces, bounded autonomous bots
and a bilingual modern browser UI.

F01–F06 have passed ten exact-head workflows each and are merged.
F07 consolidates evidence, documentation and the reproducible package.
The release is not authorized merely by a green CI. See the
[final dossier and acceptance boundaries](docs/F07_RELEASE_DOSSIER.md).

API-1 preserves historical settlement and industry compatibility.
New modules do not require a thematic branch in generic economic dispatch.
Physical cargo transport, destructive combat and territorial conquest are
outside the reference-game delivery. No real payment integration is enabled.

## Active development plan

[Finalization roadmap F01–F07](docs/FINALIZATION_ROADMAP.md) — remaining engineering work and acceptance evidence, in execution order.

## Vision
Theme-agnostic persistent worlds, asynchronous actions, deterministic simulation, lazy resource production, transactional economy, composable entities, versioned game packs, multiplayer social features and scalable shared-hosting-compatible deployment.

Implemented laboratory stack: PHP 8.4, MariaDB/InnoDB, REST, bounded CRON/CLI and a progressively enhanced Vue 3/TypeScript browser client. Laravel remains a possible future adapter; the engine does not depend on it.

## Run the engineering tests

PHP 8.4 is required. Run `php tests/run.php` for dependency-free core regressions. For the MariaDB integration, concurrency and browser suites, consult [persistence](docs/PERSISTENCE.md), the GitHub Actions workflows and the tests in the selected commit. These are not installation instructions for a publicly accessible game. Do not put database credentials, activation tokens or recovery codes into logs.

## V1.0 release candidate documentation (M13, not yet approved)
[Player guide](docs/M13_PLAYER_GUIDE.md) · [API reference](docs/API_V1.md) · [SDK](docs/F01_SDK.md) · [Installation and distribution](docs/F06_DISTRIBUTION.md) · [Operator runbook](docs/M13_RUNBOOK.md) · [Measured load](docs/F02_CAPACITY.md) · [G01–G09 acceptance](docs/M13_RELEASE_ACCEPTANCE.md) · [Final dossier](docs/F07_RELEASE_DOSSIER.md).

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

## Laboratoire local trois jeux (G08)
[Installation locale privée avec Docker Compose, comptes de démonstration et worker](docs/G08-LOCAL-PLAYTEST.md). Cette configuration n'est pas destinée à la production.
