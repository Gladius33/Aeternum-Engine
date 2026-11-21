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
