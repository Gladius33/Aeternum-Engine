# MariaDB persistence (iteration 0002)

Requires 64-bit PHP 8.4, PDO MySQL with native prepares and exceptions, and MariaDB/InnoDB. Apply database/migrations/001_core.sql once to a new database with a migration account. Use an application account with only required SELECT/INSERT/UPDATE permissions on these tables. Never expose DB credentials in Git.

Use PdoScheduler::initialize() to provision a world, schedule() to enqueue immutable events, processDue() to process due work and state() to read the state. The caller supplies trusted world IDs and pure handlers; this is not an authenticated public API.

Every write obtains the world row lock first and commits the sequence or state and event completion in the same transaction. One event per transaction limits lock duration and makes failures retryable. Duplicate IDs are compared by content even after completion. The time budget is cooperative between transactions; lock waits/handler runtime may exceed it. Configure a bounded DB lock timeout and short handlers; retry failed calls after reconnect, not arbitrary external effects. A lost commit response must be reconciled from durable state and IDs. No outbox, general command journal or replay implemented yet.

Tests: use a dedicated EMPTY disposable database (the test refuses a nonempty one). Set AETERNUM_TEST_DSN, AETERNUM_TEST_USER, AETERNUM_TEST_PASSWORD and run php tests/integration.php. It checks reconnect persistence, isolation, rollback/retry and concurrent duplicate submissions and processing using independent PHP subprocesses. Never run against production.
