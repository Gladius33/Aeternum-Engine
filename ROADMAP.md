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

## Current iteration 0022
Persistent world memberships and immutable account ownership; actor-scoped economy command IDs, transactional authorization, private receipts and own-balance projection with due-event barrier. Relational locations and immutable actor-owned entities are now implemented. G02 still needs typed component rules, topology and relational balance scaling. Immutable bounded scalar components support trusted composition and private reads. G05 needs actual authenticated HTTP transport and identity lifecycle, rate limits and UI; no public service yet.

Application identities now have unique canonical logins and Argon2id password hashes; Opaque sessions support expiry and revocation; abuse controls remain open.

Initial authenticated read-only HTTP routes for own entities and components are implemented; public login, rate limiting, economy routes, UI and real HTTP E2E remain open.

Persistent atomic rate limiter implemented; public authentication wiring and bounded cleanup remain open.

## Next
Extend HTTP adapter; expose only actor-scoped routes. Add bounded CLI processing and migration runner, then two minimal playable browser reference packs. Continue security, fault and scale validation.

Login and logout HTTP routes enforce peer and login buckets; public registration, global abuse controls, bounded cleanup and real transport tests remain open.

Economy HTTP actor-scoped commands and balances implemented; trusted world initialization, registration and event worker remain open.

Trusted retryable player provisioning implemented. Public registration, atomic onboarding and worker remain open.

Bounded CLI world worker implemented. Migration runner, shared-hosting deployment validation and public onboarding remain open.

Locked checksummed migration runner implemented; legacy schema adoption and backup restore validation remain open.

Trusted account setup CLI available; browser UI and public registration remain open.

0023: initial browser economy client wired to real API; Vue/TypeScript, localization and browser E2E remain open.

0024: authenticated discovery of owned accounts and pack resources, with browser selection controls and corrected industrial pack mapping. Browser E2E remains open.
