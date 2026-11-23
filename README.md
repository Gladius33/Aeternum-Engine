# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status — M07 client web en qualification (V1.0 non publiée)

Les macro-itérations **M01 à M06 sont intégrées** sur `main` : inscription et identité, SDK/registre de packs, économie persistante, monde stratégique, jeu médiéval et jeu industriel. **M07 reste en qualification dans la [PR #47](https://github.com/Gladius33/Aeternum-Engine/pull/47)** tant que les tests du SHA exact et les critères fonctionnels ne sont pas tous acceptés. La [feuille de route M01–M13](docs/MACRO_ROADMAP_V1.md) et les exigences normatives [G01–G09](ROADMAP.md) restent applicables ; **la V1.0 n'est pas publiée**.

Le client actuel donne accès aux comptes, mondes, stocks, production, transferts, marché, cartes, fondations, constructions et objectifs des jeux de référence. Le nouveau module Vue 3 / TypeScript fournit navigation, aide, feedback d'activité, indications de reprise et confirmations, avec une interface bilingue et responsive. Les commandes et l'authentification restent traitées par l'adaptateur navigateur existant et l'API PHP, jamais directement par des contrôles de confiance dans le frontend. Voir [le dossier de qualification M07](docs/M07_WEB_CLIENT.md).

Les vérifications GitHub Actions sur PHP 8.4, MariaDB et Chromium sont des **preuves de laboratoire**. Aucune publication externe, délivrance de vrais courriels ou recette O2Switch/VPS n'a été exécutée ni autorisée. L'inscription publique doit exiger une invitation ou la vérification d'une adresse : le contournement local de test ne doit jamais être utilisé sur un hébergement accessible.

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
