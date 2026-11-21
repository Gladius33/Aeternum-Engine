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

## Current development
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

0025 adds automated Chromium end-to-end scenarios for both packs. Results must be checked on the exact commit before claiming browser validation; full production deployment remains open.

0025 E2E exercises the real bounded CLI worker; it does not assume stock reads process due events.

0025: both reference browser economy scenarios passed against real MariaDB and the CLI worker on exact head d0ac7f2ec17a71e0ce7b6f67a5c007d9d2d8368b (runs 44955461687 and 44955456003). G04 remains open for full gameplay and SDK. 0026 adds bounded expired-session maintenance and race coverage; rate limiter retention, public onboarding, and real deployment remain open.

0026: exact head 561fa88e212bb141eda9c868152e93746f1b421a passed PHP/MariaDB (run 44956071186) and Chromium (run 44956071245), merged as ca024adf08e4c500ce5c9bab261b59f724696f74. 0027 implements bounded rate-limit retention, rollback protection and real CLI integration coverage; no G01–G09 gate is marked complete by this maintenance work alone.
