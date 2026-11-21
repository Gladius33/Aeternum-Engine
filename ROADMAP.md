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

## Current iteration 0009
GamePackLoader validates bounded declarative JSON, exact integer values, schema fields and economic cross-references. Raw artifact SHA-256 binds rules to each world. Two distinct reference economy packs exercise upgrades and persistent replay. G04 remains open: user-facing playable games, localization, entity/action schema expansion and runtime artifact/version migration policy. G02 membership/entity/ownership and scale remain open.

## Next
Implement authenticated actor-scoped command admission with persistent world memberships and entity/resource ownership, relational state model and authorization tests. Expand reference packs and add a bounded CLI and user-facing application. Continue fault/release validation.
