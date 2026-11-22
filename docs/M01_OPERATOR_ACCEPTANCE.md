# M01 — Operator acceptance and recovery checklist (NOT a deployment authorization)

This is a **manual qualification protocol**, not evidence that an external environment has been tested. Run only on a disposable clone or an explicitly authorized host. The source of truth for V1.0 remains `ROADMAP.md`; M01 remains incomplete until the real acceptance gates pass.

## Before changing a persistent installation

1. Record the deployed commit SHA, PHP version, MariaDB version, enabled worlds, current schema migration ledger and application/worker configuration **without copying credentials into logs**.
2. Stop new registration requests and scheduled workers. Confirm no database migration or world event worker is running.
3. Take a consistent, restorable backup of the **entire application database**, including identity, membership, event journal, receipts, sessions, invitations and migration metadata. Preserve a separately stored copy of the matching application code and configuration. Protect and restrict backup access.
4. Restore that backup into an isolated database and verify its table counts, existing account login, representative balances, journal replay and migration ledger before touching production. A successful backup command alone is insufficient.
5. Apply migrations 016–018 **only** after the restored copy passes the migration runner's checksum/dirty-state checks. Re-run the migration command and confirm a no-op; test legacy login and replay again. Record commands, exit codes and evidence privately.
6. If the trial fails, **do not** edit migration checksums or clear a dirty marker manually. Investigate the failure, discard the disposable clone and restore from the original backup before attempting another trial.

## Authorized HTTPS and ingress acceptance

- Confirm a valid certificate, HTTPS redirect, HSTS policy appropriate to the actual domain, and absence of mixed-content requests using an independent browser. The CI loopback TLS proxy is **not** proof of production TLS.
- Restrict `AETERNUM_TRUSTED_PROXY_IPS` to the actual reverse-proxy peer addresses; reject untrusted or malformed forwarded chains. Verify that the ingress **overwrites** client-supplied forwarding headers, not merely appends to them.
- From separate test clients, prove that forged `X-Forwarded-For` values cannot evade peer quotas. Ensure the actual backend is not directly reachable around the proxy.
- Verify PHP workers, MariaDB connections, bounded CRON execution, rate-limit maintenance, error logs, database privileges and alerting under measured concurrent signups and authentication. Record p50/p95 latency, peak memory, HTTP 429/503 rates and recovery after saturation; do not assert capacity from the default semaphore limit.

## Functional and authorization acceptance

Using **test accounts and test worlds** only:

1. Enroll a user through the public browser, select an approved world, produce a resource, log out and sign back in; verify the same actor and persistent resource state.
2. Create an independent user and verify that foreign account resources, world memberships, receipts and credentials remain inaccessible.
3. Exercise duplicate enrollment, conflicting world claim, interrupted pending provisioning and idempotent retry. Verify exactly one actor, one initial grant and no duplicate ledger entries.
4. Exercise invitation-required mode, expiry, revocation and one-use claims, including interrupted recovery; never publish invitation secrets.
5. Exercise offline recovery-code rotation and replay rejection, password change, logout-all and cross-session revocation; check no tokens or recovery codes appear in application logs.
6. Test admission quotas, database semaphore exhaustion, PHP worker failure and subsequent recovery without orphaned identities.
7. Record the exact source SHA, test environment, results, failures and reviewer approval. Never use a passing localhost browser test as evidence of a successful external deployment.

## Rollback decision

If migration or onboarding validation fails, disable new registration and stop workers, preserve sanitized diagnostics, and restore **database and matching code/configuration together** from the verified pre-upgrade snapshot. Do not attempt to roll back by running reverse SQL without a separately reviewed migration. Revalidate legacy login, balances, journal replay and session behavior before reopening access. Any post-snapshot writes require an explicit reconciliation plan; restoring an old snapshot can discard them.

## Release gate

The M01 pull request must remain unmerged until required PHP 8.4, MariaDB and Chromium checks are green on the **same exact HEAD SHA**, the restored nonempty-schema drill is verified, and an authorized operator signs off the real ingress, capacity and end-user journey. No external deployment or live payment is authorized by this document.


## Repeatable lab proof now automated in CI
The MariaDB CI runs tests/signup-load.php (eight independent PHP processes with measured latency and approximate RSS) and an actual mariadb-dump -> separate MariaDB schema restore -> tests/restore-verify.php comparison of every table's rows and a legacy-world deterministic journal replay. Only after the exact SHA's CI is green may those lab gates be marked acquired. **Real host recovery still requires a backup stored off-instance and an operator-performed full restoration in isolation.**
