# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status — M01 public onboarding in qualification (not released)

The current `main` branch contains the historical prototype and the V1.0 macro roadmap. Public onboarding is under active development in [PR #41](https://github.com/Gladius33/Aeternum-Engine/pull/41); **the PR is not merged, M01 is not accepted, and V1.0 is not available**. See [the 13 macro-iterations](docs/MACRO_ROADMAP_V1.md) and the normative [G01–G09 acceptance criteria](ROADMAP.md). Do not assume that features described below exist on `main` until the PR is merged.

On the M01 development branch, the reference browser client supports signup, approved-world selection, login, production, reconnect, password rotation, one-use recovery and logout. Persistent MariaDB registration provisions the player and initial resources transactionally with idempotent recovery. Invitation and optional email-activation flows, hashed activation tokens, bounded admission and concurrency controls, and operator diagnostics are implemented. **Open HTTP registration without an invitation or verified mailbox is rejected**; test-only bypasses and the local mail sink must never be enabled on a host. No real email delivery or external deployment has been verified.

The branch's PHP 8.4/MariaDB tests include concurrent signup and a dump/restore into a separate schema; Chromium exercises real local browser flows, including loopback TLS. These prove laboratory behavior only. Real TLS/proxy ingress, mailbox delivery, host capacity, off-instance recovery and operator acceptance remain outstanding. The [M01 operator checklist](docs/M01_OPERATOR_ACCEPTANCE.md) is available on the development branch and is not deployment authorization. **Do not merge or deploy M01 solely because its CI is green.**

## Vision
Theme-agnostic persistent worlds, asynchronous actions, deterministic simulation, lazy resource production, transactional economy, composable entities, versioned game packs, multiplayer social features and scalable shared-hosting-compatible deployment.

Planned stack: PHP 8.4, Laravel, Vue/TypeScript, MariaDB/InnoDB, REST and bounded CRON/CLI. The core does not depend on Laravel.

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

