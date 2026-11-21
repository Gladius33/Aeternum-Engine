# MariaDB persistence (iteration 0002)

Requires 64-bit PHP 8.4, PDO MySQL with native prepares and exceptions, and MariaDB/InnoDB. Apply the numbered SQL files in database/migrations/ in order, once each to a new database with a migration account. Use an application account with only required SELECT/INSERT/UPDATE permissions on these tables. Never expose DB credentials in Git.

Use PdoScheduler::initialize() to provision a world, schedule() to enqueue immutable events, processDue() to process due work and state() to read the state. The caller supplies trusted world IDs and pure handlers; this is not an authenticated public API.

Every write obtains the world row lock first and commits the sequence or state and event completion in the same transaction. One event per transaction limits lock duration and makes failures retryable. Duplicate IDs are compared by content even after completion. The time budget is cooperative between transactions; lock waits/handler runtime may exceed it. Configure a bounded DB lock timeout and short handlers; retry failed calls after reconnect, not arbitrary external effects. A lost commit response must be reconciled from durable state and IDs. No outbox, general command/event replay journal or replay implemented yet.

Tests: use a dedicated EMPTY disposable database (the test refuses a nonempty one). Set AETERNUM_TEST_DSN, AETERNUM_TEST_USER, AETERNUM_TEST_PASSWORD and run php tests/integration.php. It checks reconnect persistence, isolation, rollback/retry and concurrent duplicate submissions and processing using independent PHP subprocesses. Never run against production.

## Command intake (0003)
Apply 002_commands.sql after 001_core.sql. PdoScheduler::execute accepts a trusted Command and pure CommandHandler, returns a durable CommandReceipt. A retry with matching ID/type/payload returns the original state snapshot and planned event IDs; conflicting requests fail. A command cannot overtake any due event, and state/receipt/follow-ups commit together. Follow-up IDs must be unique, in the same world, and due no earlier than the authoritative effective time. Command IDs must be bound to authenticated actors by future API adapters; receipts may contain private world state and must never be sent to untrusted clients directly. Use application SELECT/INSERT/UPDATE only. No external payments or notifications inside transactions.
