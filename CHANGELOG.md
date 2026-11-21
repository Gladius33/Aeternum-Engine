# Changelog

## Unreleased — iteration 0023
- Add initial browser economy client using authenticated API and stable retry IDs during the page session.

## Unreleased — iteration 0022
- Add trusted account provisioning CLI with password on stdin, existing identity authentication and retryable world setup.

## Unreleased — iteration 0021
- Add locked migration runner, checksummed ledger, dirty-state refusal and CLI.

## Unreleased — iteration 0020
- Add bounded world event worker and CRON-compatible CLI entrypoint.

## Unreleased — iteration 0019
- Add trusted retryable player provisioning and integration checks for initial resources and replay.

## Unreleased — iteration 0018
- Expose actor-scoped economy commands and balances over authenticated HTTP, with trusted rules registry.

## Unreleased — iteration 0017
- Add rate-limited HTTP login/logout and bounded JSON credential intake.
- Add integration tests for token issuance, revocation and peer throttling.

## Unreleased — iteration 0016
- Add persistent transactional fixed-window rate limiter with bounded policy and rollback protection.

## Unreleased — iteration 0015
- Add initial HTTPS JSON front controller and authenticated read-only entity/component routes.
- Test unauthorized, foreign-world, foreign-owner, revoked membership/session and successful access.

## Unreleased — iteration 0014
- Add random opaque sessions with hashed token storage, bounded TTL and idempotent revocation.
- Add integration tests for persistence, expiry, invalid credentials and revocation.

## Unreleased — iteration 0013
- Add persistent application identities, unique canonical login and Argon2id password verification.
- Add integration cases for reconnect, duplicate login and invalid credentials.

## Unreleased — iteration 0012
- Persist bounded immutable entity components, canonical scalar maps and owner-only reads.
- Test conflicts, malformed and oversized data, revocation, reconnect and cross-world isolation.

## Unreleased — iteration 0011
- Add relational world-scoped locations and immutable actor-owned entities, with composite foreign keys and world-locked trusted provisioning.
- Add own-entity projection and integration cases for isolation, retry, conflicts and revoked membership.

## Unreleased — iteration 0010
- Add persistent world membership, trusted grant/revoke, immutable player-account assignment, actor-scoped idempotency and audit metadata.
- Authorize economy command and own-balance projection inside world transaction, return receipts without private full world state.
- Test nonmembers, cross-account access, revoked members, actor retries, due-event read barrier and replay.

## Unreleased — iteration 0009
- Add bounded data-only JSON game-pack loader and published JSON schema, plus cross-field economic validation.
- Derive rule artifact digest from exact manifest bytes and load two distinct reference economy packs without theme-specific PHP.
- Test malformed/invalid packs, changed artifact rejection, and persistent upgrades/replay for both packs.

## Unreleased — iteration 0008
- Add trusted configurable timed resource-production upgrades with atomic cost and scheduled completion; settle old rate at logical due time.
- Extend persistent event handling with optional typed ledger effects, retaining compatibility with scalar handlers.
- Verify upgrade lifecycle, exact delayed catch-up, maximum level, replay and multi-process competing real transfers.

## Unreleased — iteration 0007
- Add reference economy commands for resource accounts, lazy settlement and transfers with trusted definitions.
- Commit resource ledger movements with journal, state, command receipt and outbox; verify movements during replay.
- Tests cover production carry, conservation, duplicate retry, atomic insufficient/capacity rejection and ledger divergence.

## Unreleased — iteration 0006
- Add trusted RuleSet and registry, pin rule identity to a world, reject mismatched identities and handlers in the scheduler.
- Add immutable lazy production primitive with integer rational rates, fractional carry, explicit caps, overflow-safe saturation and balance checks.
- Add MariaDB rule binding tests and 10,000 deterministic partition/reference-formula unit cases.

## Unreleased — iteration 0005
- Add transactional outbox for committed transition metadata, claim/ack/release with expiring leases and stable deduplication key.
- Test expiry, stale acknowledgement, retry delay, world scope, and abrupt worker termination before commit.
- No automatic historic delivery backfill or external delivery consumer yet.

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
