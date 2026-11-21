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
