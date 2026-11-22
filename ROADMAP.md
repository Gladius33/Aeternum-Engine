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

0027: PHP/MariaDB and Chromium passed on exact head 823908db8d1ae838c8cadbf1b3ae641c83e83579 (runs 44956530388 and 44956530516), merged as 3832bac60062a088848d3d6b261f6dc775333cea. 0028 adds fixed password hashing and a global verification budget; public onboarding, capacity and remaining G05 acceptance are open.

0028: exact head daee5e12285910702925f2a02a50e032bc2f841b passed PHP/MariaDB (44956967641) and Chromium (44956967691), merged as 1ae12e943b771c2a48b71b0c06a6b2b7431afc1c. 0029 adds an owned-resource progression overview and browser rendering. Full gameplay, distinct game mechanics, public onboarding and remaining G03–G05 gates are open.

0030: clear private browser state after HTTP 401, clear stale overview before refresh, and prevent selection changes during requests. Real browser test revokes a bearer via the live API and verifies return to sign-in. CI evidence pending.

0030 evidence: exact head 94ff65f5fea6a745688d38de656c6919b597f493 passed PHP/MariaDB (44959669342) and Chromium (44959669335), merged as a0f1ade8c3e13dce9e36b4fa9e9cb0614e47c660.

0031: expose existing actor-authorized resource transfers in the reference UI using recipient account ID and integer units. Retry identity includes the full payload. HTTP integration tests verify exact debit/credit, retry and rejection; browser tests exercise rejected transfers against real API in both packs. Recipient discovery and successful browser multiplayer scenarios remain open. CI evidence pending.

0031 evidence: exact head ab917b7f281c24f1b47a6e9184327b5aeb1326dd passed PHP/MariaDB (44969850523) and Chromium (44969850487), merged as fae7b3d0a4ac7755c5b65a8e440cfbb0461d5bf3.

0032: provision two additional disposable medieval players in browser CI; exercise a successful transfer between independent Chromium contexts and reject foreign balance reads using the sender's real bearer. Run scenarios sequentially to avoid world-wide due-event barriers. Exact debit and idempotency remain covered by HTTP integration; browser credit assertion allows lazy production. CI evidence pending. Recipient discovery remains open.

0032 evidence: exact head 7f0cb2dcc4da5f2c551990298b447db4e4a7e0f4 passed PHP/MariaDB (44978838408) and Chromium (44978838426).

0033: authenticated GET /v1/worlds/{world}/recipients lists up to 100 other active account IDs in a configured world, with truncation flag. A single SQL statement enforces active requester and recipient membership; no login, balance or private metadata is returned. Shares discovery quota. This is a bounded directory, not transfer authorization; existing transactional checks remain mandatory. Browser integration and pagination remain open. Integration checks cover revoked requester/recipient, unknown world and unauthenticated access. CI pending.

0033 evidence: exact head e3cecf685a1c8e052b1e31b6ef143c196619b26b passed PHP/MariaDB (44988163480) and Chromium (44988163479).

0034: reference UI loads the authenticated recipient directory on demand, clears it on world change or logout, displays empty/truncated states and preserves manual entry and server-side transfer authorization. Chromium multiplayer test selects a real recipient; industrial scenario checks an empty directory. CI pending. Pagination remains open.

0034 evidence: exact head 34d2e8becc17d8cb1ea41ce23604d7cb9279ae13 passed PHP/MariaDB (44996381709) and Chromium (44996381681).

0035: implement internal actor-scoped fixed-lot barter offers with escrow, immutable terms, cancellation and atomic exchange using the existing world-locked command journal. Migration 015 adds ledger reasons. Tests cover authorization, retries, capacity rollback, replay and competing acceptances. No market HTTP/UI yet; 1,000 lifetime offer IDs per reference world, no expiry/partial fills, cancellation can fail if seller stock is full. CI pending.

0035 evidence: exact head 0e256178bec78e669a8cb577043a59cd688ba665 passed PHP/MariaDB (45002262563) and Chromium (45002262545), merged as 8c217774451eeb53606e834b65525c54db404d48.

0036: add read-only market projection, active requester and seller membership checks under the existing world lock, bytewise offer-ID cursor pagination (maximum 100 per page), public terms only, no state mutation. Pages reflect independently committed snapshots; not a stable snapshot across requests. Bounded reference state; relational market storage and HTTP/UI remain open. PHP and MariaDB tests added; CI pending.

0036 evidence: exact head 1d9abd77c8c338f31feead0afd44f9363735325a passed PHP/MariaDB (45002721036) and Chromium (45002721081), merged as 1bd7c18914a8410eb78ac5474c688d7d6ac87e89.

0037: expose authenticated GET /v1/worlds/{world}/economy/market and /market/{after} (50 terms per page), and allow the three market mutations through the existing economy command route. Shared economy admission limit 120/minute plus 20/minute per actor for market writes, including retries. All existing transactional authorization remains mandatory. Real MariaDB dispatcher tests cover sessions, membership, immutable terms, retry, capacity and quota. Reference-market lifetime limit and refund capacity restriction remain production blockers; operator-only onboarding, no public deployment or browser UI. CI pending.

0037 evidence: exact head 13a5007c4059a0621c84602736df0f4599644b3f passed PHP/MariaDB (45003152191) and Chromium (45003152334), merged as 65c2a32914d45e81feac81bfc51b8c4c31cb67f9.

0038: reference browser market panel uses the authenticated live API for paging and create/accept/cancel. Shared in-page command retry identity generates the offer ID once with its command ID, preserves pending payload across ambiguous failures and clears private market state on world change/logout. Successful mutations invalidate displayed stock and terms until reloaded; no optimistic balance changes. Industrial Chromium smoke creates, finds and cancels a real offer; multiplayer browser exchange and lost acknowledgement remain open. CI pending.

0038 review correction: preserve the original world and entire pending command; expose a dedicated in-session retry control independent of current selection or open offer list. The real Chromium industrial scenario drops the cancellation response after it commits, reloads the now-empty market, retries the saved command, and checks the identical durable receipt. Logout discards pending session state. CI pending on corrected head.
