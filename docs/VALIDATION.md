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
