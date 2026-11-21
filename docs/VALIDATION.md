# Validation and release gates

For each iteration report exact commit SHA, tests actually run, result, open issues, and next increment. Never report unexecuted checks as passed.

V1.0 requires all ROADMAP.md gates: deterministic ordering/replay, no duplicate execution under retries, atomic balances and concurrency tests on MariaDB, world isolation, rule migration, two playable packs, API/UI E2E, authentication and authorization, dependency audit, bounded cron and load tests, backup restoration, deployment guide, and verified license/attribution. Document environment, measured budgets and remaining limitations. Do not claim production readiness until verified. No live payments or third-party deployments in CI.

Iteration 0002 adds disposable MariaDB integration CI, reconnect/rollback tests and independent PHP process races. CI status is the evidence; do not equate handler exceptions with a validated abrupt process-kill test.
