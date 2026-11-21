Aeternum Engine
A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0
Overview
Aeternum Engine is a theme-agnostic game engine designed to power persistent, asynchronous multiplayer strategy games. Its goal is to separate game mechanics, content and presentation from a reusable simulation core.

A single engine is intended to support multiple independent games and worlds: medieval kingdoms, economic simulations, science-fiction civilizations, industrial empires and other persistent universes.
Development status
Early development — architecture v0.1. The architecture is documented; the runtime, API and playable games are not yet implemented or production-ready. The features below describe the intended architecture and roadmap, not capabilities already delivered.
Design goals
- Deterministic, event-driven simulation and lazy resource production.
- Persistent worlds, asynchronous actions and chronological event processing.
- Data-driven game packs, composable entities and versioned rules.
- Transactional economy, idempotency and strict world isolation.
- Multiplayer interactions, markets, organizations and rankings.
- HTTP/CRON execution without mandatory long-running workers.
- Self-hosting on compatible shared hosting or dedicated infrastructure.
- Extensible SDK and independently themed web interfaces.
Planned technology stack
Layer	Technology
Engine	PHP 8.4, framework-independent domain core
Application	Laravel
Frontend	Vue.js, TypeScript
Persistence	MariaDB / InnoDB
Scheduling	CRON / CLI; optional workers
API	REST / JSON / OpenAPI
Validation	JSON Schema, PHPUnit, PHPStan


Architecture
Game packs → Application/API → Domain engine → Persistence and scheduling.

The engine is designed as a modular monolith with explicit domain boundaries and infrastructure adapters. Games define resources, entities, actions, rules and visual identity independently of the core.

See [architecture](docs/ARCHITECTURE.md), [architecture decisions](docs/adr/DECISIONS.md) and [validation criteria](docs/VALIDATION.md).
Roadmap
- [x] Architecture and initial design decisions.
- [ ] Core clock, transactional commands and event scheduler.
- [ ] Persistent economy, resource transfers and delayed actions.
- [ ] Web application, authentication and two reference game packs.
- [ ] Social features, markets, rankings and administration.
- [ ] Security, concurrency, performance and deployment validation.
- [ ] First production-ready game release.
Installation
No runnable release or installation procedure is available yet. Instructions will be published with the first executable release.
Contributing
Issues and proposals are welcome. Before submitting code for integration, discuss contribution terms with the maintainer. Contributions are not automatically assigned to the project author; separate written permission may be required for alternative licensing.
License
Aeternum Engine is distributed under the custom Aeternum Community & Commercial License v1.0 (ACCL-1.0). It is source-available, not OSI-approved open source.

Non-commercial use is free with attribution. Commercial use is royalty-free below EUR 10,000 in annual attributable revenue. Once annual attributable revenue reaches EUR 10,000 (or its currency equivalent), a royalty of 10% of the entire annual attributable revenue applies, not just the excess. Commercial users must comply with the reporting terms in the license.

Required attribution: Powered by Aeternum Engine — Created by Sébastien TOUILLEUX.

See [LICENSE.md](LICENSE.md) for the binding French license text. This English summary is informational only. Commercial licensing inquiries may be addressed to the maintainer using the contact channel published in the repository or the maintainer's GitHub profile.

Copyright © 2026 Sébastien TOUILLEUX. All rights not expressly granted are reserved.
