# Changelog

## Unreleased — iteration 0002
- MariaDB schema and PDO scheduler with world-scoped sequence and transactional state/event completion.
- Reconnect, rollback/retry and multi-process concurrency integration tests; dedicated MariaDB CI service.
- Cooperative wall-time batch limit; no general command journal or outbox yet.

## Unreleased — iteration 0001
- Establish contribution, attribution, security and release governance.
- Add framework-independent clocks, immutable scheduled events, and a single-world in-memory scheduler prototype with chronological processing and duplicate detection.
- Add executable regression tests and PHP CI configuration.
- Persistence, HTTP, game packs and production deployment are not implemented.
