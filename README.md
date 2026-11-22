# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status
Early development — iteration 0038 (CI pending). The PHP core includes in-memory and MariaDB event schedulers with per-world locking and transactional completion, plus transactional commands and durable idempotency receipts, a deterministic replay journal, and a transactional outbox with expiring delivery leases, trusted rule-set pinning and integer lazy production, and a reference economy with atomic resource transfers a persistent movement ledger, and timed resource-production upgrades, and two validated data-only reference economy packs with world membership and account-scoped command admission. Relational locations and immutable actor-owned entities are available through trusted provisioning. Bounded immutable scalar components support entity composition. Persistent application identities use Argon2id passwords. Persistent expiring and revocable opaque sessions store token hashes only. Initial read-only authenticated HTTP API for own entities and components; rate-limited login/logout routes available, two minimal reference economies with real browser validation in 0025, not full games. See the [roadmap](ROADMAP.md) and CI results for the exact state.

## Vision
Theme-agnostic persistent worlds, asynchronous actions, deterministic simulation, lazy resource production, transactional economy, composable entities, versioned game packs, multiplayer social features and scalable shared-hosting-compatible deployment.

Planned stack: PHP 8.4, Laravel, Vue/TypeScript, MariaDB/InnoDB, REST and bounded CRON/CLI. The core does not depend on Laravel.

## Run the prototype tests
PHP 8.4 is required; no dependencies are needed.
```sh
php tests/run.php
```
For MariaDB integration and concurrency tests, see [persistence](docs/PERSISTENCE.md). This is not an installation procedure for a playable game.

## Documentation
[Architecture](docs/ARCHITECTURE.md) · [ADRs](docs/adr/DECISIONS.md) · [Validation](docs/VALIDATION.md) · [Roadmap](ROADMAP.md) · [Changelog](CHANGELOG.md).

## Contributions
See [CONTRIBUTING.md](CONTRIBUTING.md), [CLA.md](CLA.md) and [SECURITY.md](SECURITY.md).

## License
Custom Aeternum Community & Commercial License v1.0 (ACCL-1.0); source-available, not OSI-approved open source. Non-commercial use is free with attribution. Commercial use is royalty-free below EUR 10,000 annual attributable revenue. At or above that threshold, the royalty is 10% of the entire annual attributable revenue, not only the excess. Reporting obligations apply. Required attribution: Powered by Aeternum Engine — Created by Sébastien TOUILLEUX.

The binding French [LICENSE.md](LICENSE.md) prevails over this informational summary. Contact the maintainer privately through GitHub for licensing inquiries.

Copyright © 2026 Sébastien TOUILLEUX. All rights not expressly granted are reserved.

Internal persistent fixed-window rate limiter available; HTTP wiring pending.

Actor-scoped economy HTTP command and balance routes available for explicitly configured worlds.

Trusted player provisioning initializes membership, settlement and economy resources; retries use stable command IDs.

Bounded CLI event worker: php bin/worker.php PACK WORLD.

Schema installation: php bin/migrate.php on a fresh database.

Trusted CLI account setup: php bin/provision.php PACK WORLD LOGIN (password on stdin).

Initial browser client at /play.html: sign in, inspect stock, settle and upgrade. Operator-provisioned accounts only.

Player world discovery is available in the reference UI. Configure AETERNUM_MEDIEVAL_WORLD and AETERNUM_INDUSTRIAL_WORLD for the two bundled packs.

Browser E2E CI: real Chromium, MariaDB and both bundled games; see tests/browser/.

Schedule the bounded world worker for each configured world; browser stock reads do not process due events.

Bounded maintenance: `php bin/maintenance.php 500` deletes expired sessions only; configure a separate maintenance DB credential with DELETE on engine_sessions.

Rate-limit maintenance: apply migration 014 before deploying iteration 0027, then run `php bin/rate-maintenance.php 500` under distinct maintenance credentials. Stale buckets are deleted in a bounded transaction; a durable clock floor prevents reopening old windows after cleanup.

Authentication verification uses fixed Argon2id registration parameters, dummy verification for unknown well-formed logins, and a shared per-minute verification budget (AETERNUM_AUTH_GLOBAL_LIMIT, default 120). This is a configurable abuse-control mitigation, not a production capacity guarantee.

The reference client displays stock, current production level, next upgrade cost/duration and persisted pending upgrade due time. It uses the actor-scoped read-only overview API; the CLI worker remains responsible for completing due events.

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

0038 accessibility correction: initial Chromium run 45003624969 failed locating the exact label of a nested select. Use explicit for/id associations for market resource, quantity and offer controls. Reproduced the original exact-label miss and verified the corrected labels/selection in local real Chromium. Bound market smoke action/response waits to 15 seconds; full corrected CI remains required.

Iteration 0039 (CI pending): medieval multiplayer Chromium scenario exercises a complete real browser-to-browser barter, exact retry receipt, foreign-world rejection and persistence after login. This is reference functionality, not a public deployment or a completed G06 milestone.
