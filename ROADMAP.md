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

## Current iteration 0005
Transactional outbox for committed transition metadata, leased at-least-once delivery, retry delay, stale-token rejection, and abrupt process termination before commit test. Remaining G01: rule version binding, deeper commit-ambiguity fault tests and release validation.

## Next
Pin trusted rule sets to worlds and test incompatible handlers. Implement fixed-point resource production, ledger and game packs. Delivery consumers and monitoring remain future platform work.
