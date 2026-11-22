# Aeternum Engine

A modular engine for persistent multiplayer browser strategy games.

By Sébastien TOUILLEUX · Source Available · ACCL-1.0

## Status
Early development — iteration 0038 (CI pending). The PHP core includes in-memory and MariaDB event schedulers with per-world locking and transactional completion, plus transactional commands and durable idempotency receipts, a deterministic replay journal, and a transactional outbox with expiring delivery leases, trusted rule-set pinning and integer lazy production, and a reference economy with atomic resource transfers a persistent movement ledger, and timed resource-production upgrades, and two validated data-only reference economy packs with world membership and account-scoped command admission. Relational locations and immutable actor-owned entities are available through trusted provisioning. Bounded immutable scalar components support entity composition. Persistent application identities use Argon2id passwords. Persistent expiring and revocable opaque sessions store token hashes only. Initial read-only authenticated HTTP API for own entities and components; rate-limited login/logout routes available, two minimal reference economies with real browser validation in 0025, not full games. See the [roadmap](ROADMAP.md) and CI results for the exact state.

## Vision
Theme-agnostic persistent worlds, asynchronous actions, deterministic simulation, lazy resource production, transactional economy, composable entities, versioned game packs, multiplayer social features and scalable shared-hosting-compatible deployment.

Planned stack: PHP 8.4, Laravel, Vue/TypeScript, MariaDB/InnoDB, REST and bounded CRON/CLI. The core does not depend on Laravel.

## Run the prototype tests
PHP 8.4 is required; no dependencies are needed.
```sh
php tests/run.php
```
For MariaDB integration and concurrency tests, see [persistence](docs/PERSISTENCE.md). This is not an installation procedure for a playable game.

## Documentation
[Architecture](docs/ARCHITECTURE.md) · [ADRs](docs/adr/DECISIONS.md) · [Validation](docs/VALIDATION.md) · [Roadmap](ROADMAP.md) · [Changelog](CHANGELOG.md).

## Contributions
See [CONTRIBUTING.md](CONTRIBUTING.md), [CLA.md](CLA.md) and [SECURITY.md](SECURITY.md).

## License
Custom Aeternum Community & Commercial License v1.0 (ACCL-1.0); source-available, not OSI-approved open source. Non-commercial use is free with attribution. Commercial use is royalty-free below EUR 10,000 annual attributable revenue. At or above that threshold, the royalty is 10% of the entire annual attributable revenue, not only the excess. Reporting obligations apply. Required attribution: Powered by Aeternum Engine — Created by Sébastien TOUILLEUX.

The binding French [LICENSE.md](LICENSE.md) prevails over this informational summary. Contact the maintainer privately through GitHub for licensing inquiries.

Copyright © 2026 Sébastien TOUILLEUX. All rights not expressly granted are reserved.

Internal persistent fixed-window rate limiter available; HTTP wiring pending.

Actor-scoped economy HTTP command and balance routes available for explicitly configured worlds.

Trusted player provisioning initializes membership, settlement and economy resources; retries use stable command IDs.

Bounded CLI event worker: php bin/worker.php PACK WORLD.

Schema installation: php bin/migrate.php on a fresh database.

Trusted CLI account setup: php bin/provision.php PACK WORLD LOGIN (password on stdin).

Initial browser client at /play.html: sign in, inspect stock, settle and upgrade. Operator-provisioned accounts only.

Player world discovery is available in the reference UI. Configure AETERNUM_MEDIEVAL_WORLD and AETERNUM_INDUSTRIAL_WORLD for the two bundled packs.

Browser E2E CI: real Chromium, MariaDB and both bundled games; see tests/browser/.

Schedule the bounded world worker for each configured world; browser stock reads do not process due events.

Bounded maintenance: `php bin/maintenance.php 500` deletes expired sessions only; configure a separate maintenance DB credential with DELETE on engine_sessions.

Rate-limit maintenance: apply migration 014 before deploying iteration 0027, then run `php bin/rate-maintenance.php 500` under distinct maintenance credentials. Stale buckets are deleted in a bounded transaction; a durable clock floor prevents reopening old windows after cleanup.

Authentication verification uses fixed Argon2id registration parameters, dummy verification for unknown well-formed logins, and a shared per-minute verification budget (AETERNUM_AUTH_GLOBAL_LIMIT, default 120). This is a configurable abuse-control mitigation, not a production capacity guarantee.

The reference client displays stock, current production level, next upgrade cost/duration and persisted pending upgrade due time. It uses the actor-scoped read-only overview API; the CLI worker remains responsible for completing due events.

0030: clear private browser state after HTTP 401, clear stale overview before refresh, and prevent selection changes during requests. Real browser test revokes a bearer via the live API and verifies return to sign-in. CI evidence pending.

0030 evidence: exact head 94ff65f5fea6a745688d38de656c6919b597f493 passed PHP/MariaDB (44959669342) and Chromium (44959669335), merged as a0f1ade8c3e13dce9e36b4fa9e9cb0614e47c660.

0031: expose existing actor-authorized resource transfers in the reference UI using recipient account ID and integer units. Retry identity includes the full payload. HTTP integration tests verify exact debit/credit, retry and rejection; browser tests exercise rejected transfers against real API in both packs. Recipient discovery and successful browser multiplayer scenarios remain open. CI evidence pending.

0031 evidence: exact head ab917b7f281c24f1b47a6e9184327b5aeb1326dd passed PHP/MariaDB (44969850523) and Chromium (44969850487), merged as fae7b3d0a4ac7755c5b65a8e440cfbb0461d5bf3.

0032: provision two additional disposable medieval players in browser CI; exercise a successful transfer between independent Chromium contexts and reject foreign balance reads using the sender's real bearer. Run scenarios sequentially to avoid world-wide due-event barriers. Exact debit and idempotency remain covered by HTTP integration; browser credit assertion allows lazy production. CI evidence pending. Recipient discovery remains open.

0032 evidence: exact head 7f0cb2dcc4da5f2c551990298b447db4e4a7e0f4 passed PHP/MariaDB (44978838408) and Chromium (44978838426).

0033: authenticated GET /v1/worlds/{world}/recipients lists up to 100 other active account IDs in a configured world, with truncation flag. A single SQL statement enforces active requester and recipient membership; no login, balance or private metadata is returned. Shares discovery quota. This is a bounded directory, not transfer authorization; existing transactional checks remain mandatory. Browser integration and pagination remain open. Integration checks cover revoked requester/recipient, unknown world and unauthenticated access. CI pending.

0033 evidence: exact head e3cecf685a1c8e052b1e31b6ef143c196619b26b passed PHP/MariaDB (44988163480) and Chromium (44988163479).

0034: reference UI loads the authenticated recipient directory on demand, clears it on world change or logout, displays empty/truncated states and preserves manual entry and server-side transfer authorization. Chromium multiplayer test selects a real recipient; industrial scenario checks an empty directory. CI pending. Pagination remains open.

0034 evidence: exact head 34d2e8becc17d8cb1ea41ce23604d7cb9279ae13 passed PHP/MariaDB (44996381709) and Chromium (44996381681).

0035: implement internal actor-scoped fixed-lot barter offers with escrow, immutable terms, cancellation and atomic exchange using the existing world-locked command journal. Migration 015 adds ledger reasons. Tests cover authorization, retries, capacity rollback, replay and competing acceptances. No market HTTP/UI yet; 1,000 lifetime offer IDs per reference world, no expiry/partial fills, cancellation can fail if seller stock is full. CI pending.

0035 evidence: exact head 0e256178bec78e669a8cb577043a59cd688ba665 passed PHP/MariaDB (45002262563) and Chromium (45002262545), merged as 8c217774451eeb53606e834b65525c54db404d48.

0036: add read-only market projection, active requester and seller membership checks under the existing world lock, bytewise offer-ID cursor pagination (maximum 100 per page), public terms only, no state mutation. Pages reflect independently committed snapshots; not a stable snapshot across requests. Bounded reference state; relational market storage and HTTP/UI remain open. PHP and MariaDB tests added; CI pending.

0036 evidence: exact head 1d9abd77c8c338f31feead0afd44f9363735325a passed PHP/MariaDB (45002721036) and Chromium (45002721081), merged as 1bd7c18914a8410eb78ac5474c688d7d6ac87e89.

0037: expose authenticated GET /v1/worlds/{world}/economy/market and /market/{after} (50 terms per page), and allow the three market mutations through the existing economy command route. Shared economy admission limit 120/minute plus 20/minute per actor for market writes, including retries. All existing transactional authorization remains mandatory. Real MariaDB dispatcher tests cover sessions, membership, immutable terms, retry, capacity and quota. Reference-market lifetime limit and refund capacity restriction remain production blockers; operator-only onboarding, no public deployment or browser UI. CI pending.

0037 evidence: exact head 13a5007c4059a0621c84602736df0f4599644b3f passed PHP/MariaDB (45003152191) and Chromium (45003152334), merged as 65c2a32914d45e81feac81bfc51b8c4c31cb67f9.

0038: reference browser market panel uses the authenticated live API for paging and create/accept/cancel. Shared in-page command retry identity generates the offer ID once with its command ID, preserves pending payload across ambiguous failures and clears private market state on world change/logout. Successful mutations invalidate displayed stock and terms until reloaded; no optimistic balance changes. Industrial Chromium smoke creates, finds and cancels a real offer; multiplayer browser exchange and lost acknowledgement remain open. CI pending.

0038 review correction: preserve the original world and entire pending command; expose a dedicated in-session retry control independent of current selection or open offer list. The real Chromium industrial scenario drops the cancellation response after it commits, reloads the now-empty market, retries the saved command, and checks the identical durable receipt. Logout discards pending session state. CI pending on corrected head.

0038 accessibility correction: initial Chromium run 45003624969 failed locating the exact label of a nested select. Use explicit for/id associations for market resource, quantity and offer controls. Reproduced the original exact-label miss and verified the corrected labels/selection in local real Chromium. Bound market smoke action/response waits to 15 seconds; full corrected CI remains required.

Iteration 0039 (CI pending): medieval multiplayer Chromium scenario exercises a complete real browser-to-browser barter, exact retry receipt, foreign-world rejection and persistence after login. This is reference functionality, not a public deployment or a completed G06 milestone.


### Public signup (M01, **beta; not yet production-ready**)
Public signup is OFF unless `AETERNUM_PUBLIC_REGISTRATION=1` is set by the operator. Configure one or both approved world IDs using `AETERNUM_MEDIEVAL_WORLD` / `AETERNUM_INDUSTRIAL_WORLD` and apply migration `016_public_onboarding.sql` with the locked migration runner before enabling it. `GET /v1/public/worlds` discloses only approved world names and IDs; `POST /v1/auth/register` accepts a canonical lowercase login (3–64 characters), a 12–1024-byte password, and a configured world ID. The route is HTTPS-only in the actual front controller, uses distinct hourly per-peer and global quotas, and returns an opaque one-hour session after completed provisioning. Never trust a client-supplied world or pack path.

An identity plus its pending claim are committed atomically, then established durable world-locked provisioning operations are retried on an identical signup after interruptions. A completed account cannot use repeated signup to switch worlds; existing operator-only identities cannot enroll. The UI provides world selection and confirm-password fields. This milestone is **in progress**: verified email/activation, abuse monitoring, operational production capacity, and real-host HTTPS have not yet been certified. Avoid enabling open registration on a production server until those are complete.


### Offline account recovery (M01)
A successful registration returns a **one-time 256-bit recovery code**. Store it offline; it is not persisted in plaintext and cannot be retrieved from the server. POST /v1/auth/recover takes login, code and newPassword, atomically changes the password, revokes existing sessions and rotates the recovery code. POST /v1/auth/recovery/rotate requires a valid bearer session and the current password to issue a replacement code. Both flows enforce rate quotas; recovery consumes a code at most once even with competing processes. The UI supports recovery and code rotation. A recovery code is NOT proof of email ownership: external activation and production bot defense remain open. Save each newly rotated code immediately.


### Optional invitation-only enrollment (M01)
For a controlled beta, set AETERNUM_PUBLIC_REGISTRATION=1 and AETERNUM_SIGNUP_REQUIRE_INVITE=1, then apply migration 018. Issue a bounded batch of one-time codes from a secured administrator terminal with php bin/invite.php APPROVED_WORLD COUNT (1–50); distribute each code privately. The public signup UI requests the code only when this mode is enabled. Each random 256-bit code is tied to one approved world and claimed atomically with the new identity and pending signup. A retry with the same credentials and same invitation resumes interrupted provisioning. A consumed code cannot create a second identity; a rejected claim leaves no orphan identity. Store generated codes safely; only SHA-256 digests persist. This is beta access control, not an email verification or a production-grade Sybil defense.


**Beta invitation expiry/revocation:** invitation codes expire seven days after issue by default (server time, maximum supported validity 30 days) and may be revoked while unused. Operators can revoke a specific code by piping it to `php bin/invite-revoke.php APPROVED_WORLD` through stdin (avoid the shell history and process arguments). A code already consumed for an identity remains available for an idempotent resume of pending world initialization; revocation is not an account-ban mechanism.


### Authenticated password changes (M01)
A signed-in player may use the **Change password** form or send `POST /v1/auth/password/change` with an opaque bearer and exactly `{"currentPassword":"...","newPassword":"..."}`. The current password must be correct, the replacement must differ and contain 12–1024 bytes, and the existing peer/global verification limits remain enforced. The endpoint never accepts a client-supplied actor ID.

Within one InnoDB identity-row-locked transaction it updates the Argon2id password digest, revokes **all** existing sessions (including the calling session) and rotates the 256-bit offline recovery credential. A successful response contains `{"data":{"recoveryCode":"...","sessionsRevoked":true}}`, **not** an automatically authenticated replacement bearer. Save the new code offline, then sign in with the new password. The UI clears local authorization and private projections before displaying the new code. Trying to reuse an older recovery code fails; a rejected change leaves credentials and sessions intact.

A lost response to a committed change does not restore the old password or sessions. Reconnect using the **new** password and rotate the code again if it was not saved. Verification, concurrent changes, recovery replay and browser sign-in must pass in CI; code merged to this PR is not itself proof of operational readiness. Email ownership and production anti-bot controls remain outside the current verified scope.


### Sign out on all devices (M01)
`POST /v1/auth/logout` revokes only the submitted bearer. `POST /v1/auth/logout/all` requires an active bearer, derives the account identity server-side, and atomically deletes **all** its session rows under the identity lock. It responds with `{"data":{"revokedAll":true}}`; the caller's bearer becomes invalid and cannot repeat the operation. The browser's **Log out all devices** button clears its private state after success. Another account's tokens and this account's password/recovery code are unaffected; a legitimate new sign-in remains possible. The endpoint retains the existing server-side peer rate limit and does not accept a target actor ID.


### M01 — Signup traffic shaping (beta)
All authentication endpoints use the socket peer address supplied by the HTTP server (not client-supplied X-Forwarded-For headers). IPv4-mapped IPv6 is normalized to IPv4, and IPv6 privacy addresses share a single /64 rate bucket; distinct IPv4 addresses retain separate buckets. Reverse proxies must be configured to supply a **trusted, canonical** client IP at the server boundary, or all users may share the proxy's quota. Never trust a forged request header directly.

Public registration remains explicitly opt-in, and optional one-use invitations are recommended for a controlled beta. Set AETERNUM_SIGNUP_WORLD_HOURLY_LIMIT to a strict positive integer between 1 and 100000 (default **30**) to bound the number of registration attempts per approved world per hour, independent of the global 100/hour and peer 6/hour limits. Invalid configuration fails closed. These counters use the persistent MariaDB limiter and are transactional across PHP workers; switching IP addresses does not circumvent the per-world cap. Existing CLI provisioned players and already-authenticated commands are not counted against signup capacity.

The world quota is an **admission** limit, not a count of successful new people: failed or repeated signups consume slots, deliberately preventing unbounded expensive password-hash/provisioning attempts. It may also cause a legitimate retry after a lost response to receive HTTP 429 until the window rolls over. Neither network bucketing nor these quotas prove real-world uniqueness or email ownership; operator-vetted invitations, monitoring and capacity tests remain required before production public enrollment.


### M01 invitation retention maintenance
After migration 018, run php bin/invite-maintenance.php 500 from a restricted operator shell (MariaDB credentials through the existing AETERNUM_DSN, AETERNUM_DB_USER and AETERNUM_DB_PASSWORD environment variables). Each invocation deletes at most the requested 1–1000 **unused** expired/revoked invitation rows and outputs a JSON deletion count. Repeat in a scheduled maintenance job if needed. Live unused invitations and **every used invitation**, even expired, are retained to preserve interrupted signup replay and account binding. This command never prints invitation codes, never attempts to revoke already-spent credentials, and cannot delete a concurrent claim that has committed. Test first against a disposable copy of a database and preserve normal database backups; this is an irreversible cleanup of already-invalid unused credentials, not an enrollment reset.


### M01 peak signup concurrency (beta)
Configure AETERNUM_SIGNUP_PARALLEL_LIMIT as an integer from 1 to 32 (default 8). This restricts the number of simultaneously active expensive Argon2id public signup/provisioning requests over the whole installation, across independent PHP workers, using MariaDB advisory locks scoped to the selected database. A saturated pool returns HTTP 503 registration_busy **before** starting a new identity transaction. The browser displays a retry message. A normal completion or exception releases the slot; broken database connections release their locks automatically. A rejected admission still counts against the existing rate quota, so aggressive retries can lead to 429. Operator CLI provisioning is separate.

Choose a smaller parallel limit for memory-constrained PHP hosting only after load testing. This is a defensive burst bound rather than an estimate of available RAM, protection against Sybil identity creation, or certified O2Switch/VPS capacity. No new schema migration is required.


### M01 trusted reverse-proxy client IP policy
By default all auth quotas bind to the actual TCP REMOTE_ADDR and **ignore X-Forwarded-For**. When the HTTPS front controller is behind a specifically authorized proxy which appends (or overwrites) X-Forwarded-For, set AETERNUM_TRUSTED_PROXY_IPS to an explicit comma-separated list of exact proxy addresses, e.g. 192.0.2.10,192.0.2.11. The resolver walks the header from the right and selects the first untrusted address; a forged leftmost prefix is not accepted as the client. The immediate socket peer must be in the trusted list before any forwarding header is examined. Trust is exact IP, even for IPv6, while quota buckets still group IPv6 privacy addresses by /64.

**Important deployment prerequisite:** only authorize proxies you control and that append the real peer address or strip incoming forwarded headers. Do not whitelist entire ranges or use an upstream that passes user-controlled headers without appending. Invalid headers or invalid trusted-proxy configuration fail closed. The web server is solely responsible for setting the HTTPS server variable after real TLS termination; forwarded scheme headers are never trusted by the PHP entrypoint. The code and CI prove resolution rules, not a real internet TLS/proxy deployment. Verify the actual chain, rate buckets and logging with operator-approved host-side smoke tests before enabling public enrollment.


### M01 read-only enrollment health monitoring
Run php bin/onboarding-status.php APPROVED_WORLD from an authorized operator terminal with the normal AETERNUM_DSN, AETERNUM_DB_USER and AETERNUM_DB_PASSWORD environment variables and configured approved reference world. It prints **only numerical aggregates**: ready/pending enrollments, pending claims older than 15 minutes, oldest pending age, counts of available/claimed/revoked/expired invitations, and active nonrevoked unexpired sessions. It never returns usernames, actor IDs or invitation/recovery/session secrets. The tool is CLI-only and rejects unknown worlds. A nonzero stale-pending count warrants inspecting provisioning failures and database health, **not** blindly deleting pending accounts or reissuing starting resources. The snapshots are near-live SELECTs, not a transactional consistency guarantee, and are not network-reachable APIs. Run the command in a read-only DB account if permitted. These metrics help detect stuck registrations; they do not replace mail validation, abuse alerts, alerting integration or load qualification.
