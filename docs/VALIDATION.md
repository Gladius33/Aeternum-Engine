# Validation and release gates

For each iteration report exact commit SHA, tests actually run, result, open issues, and next increment. Never report unexecuted checks as passed.

V1.0 requires all ROADMAP.md gates: deterministic ordering/replay, no duplicate execution under retries, atomic balances and concurrency tests on MariaDB, world isolation, rule migration, two playable packs, API/UI E2E, authentication and authorization, dependency audit, bounded cron and load tests, backup restoration, deployment guide, and verified license/attribution. Document environment, measured budgets and remaining limitations. Do not claim production readiness until verified. No live payments or third-party deployments in CI.

Iteration 0002 adds disposable MariaDB integration CI, reconnect/rollback tests and independent PHP process races. CI status is the evidence; do not equate handler exceptions with a validated abrupt process-kill test.

Iteration 0003 adds command receipt, backlog barrier, partial follow-up rollback and concurrent overspend tests. Authenticated command IDs and actor authorization are future API work.

Iteration 0004 tests mixed command/event replay, duplicate omission, state and follow-up divergence detection and bounded replay. Verify with original rule handlers; no claim of pre-checkpoint reconstruction, malicious database tamper resistance or pinned rule versions.

Iteration 0005 checks outbox atomicity on accepted/rejected commands, duplicate retries, lease expiry/stale token rejection, delayed retry, world isolation and SIGKILL of a worker while the transaction is open before state writes. Lost acknowledgement after external delivery and ambiguous DB commit responses still require dedicated tests.

Iteration 0006 adds rule binding integration checks and 10,000 partition/reference formula production cases, plus boundary arithmetic. These are not yet persistent economy/ledger or artifact supply-chain validation.

Iteration 0007 verifies persistent reference economy conservation, atomic rejected settlements/transfers, stale retry, capacity, and ledger replay divergence. It does not yet test concurrent real economy transfers or event-generated ledger effects.

Iteration 0008 exercises atomic timed upgrade costs, duplicate/pending/max-level checks, logical catch-up after reconnect, event ledger replay and four competing real resource transfers in independent PHP processes. No public actor authorization is implied.

Iteration 0009 tests JSON schema field/type/limit and economic cross-field validation via the PHP loader, and distinct loaded reference pack upgrades/replay across reconnect. Neither pack has an authenticated UI or full gameplay yet; not counted as two playable games.

Iteration 0010 tests account ownership, revoked membership, nonmember and cross-world denial, separate actor request IDs, private receipt, own-balance access, due-event barrier and replay. Authentication/session proof, HTTP abuse resistance, actor grant auditing and concurrent revoke/command race tests remain open.

Iteration 0011 adds integration cases for entity provisioning retry, conflicting ownership, separate worlds, reconnect and revoked access. Concurrent provisioning and direct SQL constraint tests remain open; check CI for execution evidence.

Iteration 0012 adds component canonical retry, malformed/oversized data, ownership, revocation, reconnect and isolation integration cases. CI evidence required.

Iteration 0013 adds identity integration cases for reconnect, password verification, duplicate login and minimum password length. Argon2id support is required. Timing equalization and abuse controls remain open.

Iteration 0014 adds session persistence, distinct tokens, invalid login, plaintext absence, exact expiry and revocation cases. Cleanup, rate limiting and HTTP transport remain open.

Iteration 0015 adds direct HTTP dispatcher integration cases for authenticated own reads, denied access, method restrictions and revocation. Actual web server transport, rate limits and browser E2E remain unverified.

0016 tests persistent counters, independent buckets, exact window rollover, clock rollback and invalid input. Parallel and cleanup tests remain open.

0017 tests HTTP authentication dispatch, malformed input, session resolution, logout, peer limit and rollover; actual transport and distributed abuse resistance remain unverified.

0018 tests economy HTTP command, retry, balance, authorization and malformed input directly against MariaDB. Network transport remains untested.

0019 checks unknown identity, repeated provisioning, initial balances and journal replay. Crash injection and concurrent administrative operations remain unverified.

0020 integration cases cover future events, due completion, replay-safe retry, invalid limits and unknown world. Real shared-hosting CRON remains unverified.

0021 replaces direct integration schema setup with MigrationRunner and checks full installation, no-op retry and dirty-state refusal. Restore and actual crash tests remain open.

0022 relies on provisioning integration coverage from 0019; subprocess testing of the CLI remains open.

0023 browser UI is not yet browser-E2E validated. Existing database API integration tests exercise its backend operations.

0024 integration tests verify discovery authentication, scope isolation and revocation; browser and deployment tests remain open.

## 0025 browser E2E
Browser CI installs the schema, provisions one distinct identity per pack, starts a loopback-only PHP test server, and drives Chromium through authentication, discovery isolation, stock display, upgrade debit, reload/re-authentication, eventual production increase, and logout. No network/API mocking or accelerated time. The test router emulates the HTTPS server flag exclusively on loopback; real TLS and shared hosting remain unverified. Passing results are required on the exact head SHA.

0025 retry: the first browser run failed before scenarios because ubuntu-latest resolved to Ubuntu 26.04, unsupported by Playwright 1.56.1. Pin the browser runner to Ubuntu 24.04; rerun all gates before merge.

0025 worker correction: the authenticated balance projection intentionally rejects due-event backlog. The browser scenario now invokes the real bounded CLI worker for its configured pack/world before polling the live API, allowing a transient 409 at the due boundary. No simulated clock or mocked transport.

0025 result: exact head d0ac7f2ec17a71e0ce7b6f67a5c007d9d2d8368b passed PHP/MariaDB and both real Chromium scenarios (PR run 44955461687, push run 44955456003); merged as 59359c5613e2efec8e872e2a3df754d8aa4b5f4b. This is browser evidence, not production TLS or shared-hosting acceptance.

0026 tests bounded expiry, current and revoked session preservation, retry, invalid limits, transaction guard and two independent concurrent cleanup processes. CI must confirm these tests. The two independent browser scenarios run in parallel against distinct worlds; all required gates remain on PRs and main.

0026 result: exact head 561fa88e212bb141eda9c868152e93746f1b421a passed PHP/MariaDB (44956071186) and Chromium (44956071245). MariaDB logs confirm expiry boundary, live/revoked retention, concurrent cleanup, retry and invalid limit tests. Merged as ca024adf08e4c500ce5c9bab261b59f724696f74.

0027 exercises bounded stale bucket deletion, preservation of active exhausted buckets, durable rollback floor, invalid inputs, simultaneous admit/purge and stale admission races with independent PHP processes. Both maintenance CLIs execute against the disposable CI database with real operational time after fixed-clock tests. CI on the exact head is required; no production load or clock-drift certification is claimed.

0027 result: exact head 823908db8d1ae838c8cadbf1b3ae641c83e83579 passed PHP/MariaDB (44956530388) and Chromium (44956530516), merged as 3832bac60062a088848d3d6b261f6dc775333cea. The logs confirm rollback floor, concurrent quota enforcement, and both production CLI entry points against disposable MariaDB.

0028 integration tests cover dummy non-authentication, stored Argon2id parameters, separate-peer global quota, exact rollover, malformed requests, logout, invalid configuration bounds, and four parallel independent login requests with a budget of two. Timing indistinguishability and production capacity are not asserted; measure separately.

0028 result: exact head daee5e12285910702925f2a02a50e032bc2f841b passed PHP/MariaDB (44956967641) and Chromium (44956967691), merged as 1ae12e943b771c2a48b71b0c06a6b2b7431afc1c.

0029 tests authentication, foreign account and world denial, initial next rule, pending debit and persisted due time, the due-event barrier, completed level and revocation. Both Chromium scenarios additionally verify rendered next, pending and maximum levels and the real due timestamp across reloads. Require exact-head CI evidence.

0030: clear private browser state after HTTP 401, clear stale overview before refresh, and prevent selection changes during requests. Real browser test revokes a bearer via the live API and verifies return to sign-in. CI evidence pending.

0030 first CI: PHP passed, both economic browser scenarios returned 404 because disabled selects are omitted from FormData; the revocation scenario passed. Read explicit select values while controls are locked; retain both original economy assertions and require new exact-head CI.

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

0039 acceptance: live MariaDB/Chromium must validate real two-player barter create/accept, own-offer and foreign-cancellation UI denials, conflicting idempotency IDs, exact duplicate receipt, world isolation and post-login offer closure. Lazy stock growth is an inequality, not an exact ledger assertion. CI result must be checked on exact PR SHA.


## M01 signup qualification (pending CI)
Integration suite exercises disabled route, validation/methods/unknown world, session resolution, first-world initial stock, duplicate credentials, immutable world claim, operator identity exclusion, crash recovery from committed pending claim, bounded peer quota and four independent concurrent signup processes returning one actor and one set of initial credits. Real Chromium covers public world listing, creating a new industrial player without CLI, automatic login, stock, sign-out/re-login, second independent user and foreign balance rejection. All tests run against disposable MariaDB. Production email/activation/recovery, rate-capacity measurement and external HTTPS remain unverified; do not mark M01 complete until then.


M01 recovery follow-up: MariaDB suite covers stored token hashing, invalid and replayed codes, atomic password change, session revocation, authenticated replacement and four simultaneous attempts to spend one token. Chromium creates a public player, reconnects, recovers with an offline code, checks old password denial and authenticates with the replacement password. This is not email verification. CI on exact head required.


M01 password reset race hardening: session login and code rotation now serialize with account reset via identity row locks. Existing session and multi-process tests are mandatory regression gates; dedicated login/reset concurrency tests and load benchmarks remain part of final M01 sign-off.


M01 controlled-beta invitation checks: MariaDB tests rejection without code, malformed/unknown codes, exactly one actor per consumed invitation, orphan-free rollback, same-actor retry, wrong-actor denial and recovery of an invitation claim committed before world provisioning. A verified operator-run issuance CLI is still a deployment acceptance item.


M01 invitation expiry/revocation: integration tests reject 59-second or over-30-day TTL, revoked unused codes, expired unused codes, repeated revocation and identity creation after expiry. Operator CLI tests issue and revoke a concrete code; a previously consumed code remains bound to the original actor for interrupted-provision retry.

Invitation admission also refuses a timestamp earlier than creation (clock rollback); the invitation race worker uses the same logical clock as issuance.


M01 opt-in browser qualification: browser.yml starts an additional 127.0.0.1:8766 server with AETERNUM_SIGNUP_REQUIRE_INVITE=1, issues one real token via operator CLI, checks invite form visibility and required attribute, enrolls a player in the configured industrial world and refuses a second actor using the consumed token. Both loopback servers share disposable MariaDB; the invitation secret is discarded at teardown, never committed. E2E remains a lab approximation and does not certify real TLS.
