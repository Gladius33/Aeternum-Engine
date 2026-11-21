# Roadmap — V1.0 release scope

A checklist item is complete only when implemented, documented and supported by passing tests. No percentage based on file counts.

- [ ] G01 Core: injected clock, stable event ordering, bounded processing, transactional persistent commands, idempotency and deterministic replay.
- [ ] G02 World/entities: strict world isolation, memberships, locations, composable entities and versioned immutable rules.
- [ ] G03 Economy/actions: fixed-point production, capacity, ledger, atomic transfers, delayed actions, progression and interactions.
- [ ] G04 Game packs: schema validation, SDK, two genuinely distinct playable reference packs; version pins and migration policy.
- [ ] G05 Platform: authenticated HTTP API, Vue UI, accessibility, internationalization, sessions, rate limiting and authorization.
- [ ] G06 Multiplayer: organizations, messaging/moderation, markets, ranking and notifications.
- [ ] G07 Commercial readiness: optional premium entitlements, safe payment integration (test mode), documentation and attribution.
- [ ] G08 Operations: MariaDB migrations, bounded CRON runtime, backup/restore drill, observability, o2switch-compatible package and VPS instructions.
- [ ] G09 Release: integration/concurrency/replay/security/performance/E2E tests, dependency audit, documented capacity and signed-off V1.0 acceptance; no known critical/high defects.

## Current iteration 0008
Trusted delayed resource-production upgrades with atomic costs, old-rate settlement at logical completion, event ledger effects, replay, duplicate/pending/max-level checks and four-process real transfer test. G01 still needs fault/release validation. G03 remains open for broader actions, entities, relational scaling, interactions and authorization.

## Next
Design persistent world membership and entity ownership with actor-scoped command admission. Build validated game-pack manifests and trusted data-driven resource/upgrade definitions; test immutable artifact identity and two distinct packs. Add relational balance scaling and operational migration policy.
