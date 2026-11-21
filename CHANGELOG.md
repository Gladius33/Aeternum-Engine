# Changelog

## Unreleased — iteration 0004
- Add transactional journal of committed commands and events, canonical state/follow-up digests and bounded diagnostic replay.
- Migration checkpoint preserves pre-journal worlds without inventing past history.
- Add divergence, retry and replay size-limit tests. Rule version binding and outbox remain open.

## Unreleased — iteration 0003
- Add world-scoped durable command receipts and pure command handlers with an authoritative effective time.
- Commit state, planned events and command receipt atomically, refuse new commands while due work is pending.
- Integration tests for duplicate receipts, rollback of partially planned actions and concurrent overspend rejection.

## Unreleased — iteration 0002
- MariaDB schema and PDO scheduler with world-scoped sequence and transactional state/event completion.
- Reconnect, rollback/retry and multi-process concurrency integration tests; dedicated MariaDB CI service.
- Cooperative wall-time batch limit; no general command journal or outbox yet.

## Unreleased — iteration 0001
- Establish contribution, attribution, security and release governance.
- Add framework-independent clocks, immutable scheduled events, and a single-world in-memory scheduler prototype with chronological processing and duplicate detection.
- Add executable regression tests and PHP CI configuration.
- Persistence, HTTP, game packs and production deployment are not implemented.
