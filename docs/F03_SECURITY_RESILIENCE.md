# F03 — Security, resilience and recovery

## Boundary review and changes

| Boundary | Control and verification |
|---|---|
| TLS and ingress | Trusted server HTTPS only; spoofed proxy TLS headers do not grant access; bounded canonical request target, method and body |
| CSRF/CORS | Same-origin browser fetch metadata; JSON mutations only except bodyless logout; no CORS grant; bearer authentication, cookies omitted by client |
| XSS and encoding | Existing restrictive HTML/JSON CSP, textContent/Vue escaping, strict JSON duplicates and typed schema; parser fuzz retained |
| Auth and enrollment | Argon2id slots, peer/global limits, verified invitation or mailbox; proof reservation persists across configuration changes |
| Sessions and recovery | Hashed credentials, atomic credential reset/revocation, pending/unverified reservations cannot use even inconsistent historical ready tokens |
| World access | Entity route now requires explicit configured world and own-actor authorization with read quota; economy, social, rank and premium retain independent locked membership checks |
| Operator and premium | No public admin write routes; scoped operator roles and test-only cosmetic entitlement audit; existing real MariaDB role/replay tests retained |
| Config and deployment | Data-only immutable pack/module digests, off-webroot registries, no arbitrary executable path; existing least-privilege runtime denial of DDL retained |
| Diagnostics | Structured low-cardinality API error category, no raw SQL, exception message, credentials or request payload returned |
| Supply chain | Locked npm audit at high severity, CycloneDX client dependency inventory, declared PHP runtime inventory; no PHP package dependency accepted silently |

Handled PHP diagnostics are converted to generic errors at the HTTP boundary; server access logs and uncatchable engine-level failures retain their own operational policy. The HTTP request policy runs before database setup. It is defense in depth,
not a substitute for authorization. Native clients may omit Fetch Metadata;
browser cross-origin requests are denied and no CORS grants are emitted.
The approved API uses application/json (optional UTF-8 charset); bodyless
logout remains compatible. URI and declared content lengths are bounded;
stream reads remain bounded independently of declared sizes.

Login and bearer resolution reject an unverified mailbox reservation even
if its onboarding ready marker is inconsistent. Missing required database
schema fails closed. The runtime requires the complete current migrations.
Historical pack identities and journals remain unchanged.

Transient SQL lock timeout, deadlock and connection loss are classified as
503 with retryable indication and no raw exception. The client automatically
retries GET and exact durable economy command IDs only; authentication,
password/recovery and other writes are not automatically repeated. A lost
response is not proof of rollback; reconcile using original command identity. A retryable due barrier also preserves the pending client command after its bounded automatic attempts.

## Real fault-injection acceptance

The M12 workflow gains a separate MariaDB 11.4 job, requiring explicit
disposable flag and an empty database named aeternum_f03. The test creates
its own schema, world, four actors and real authenticated sessions. No
production code contains a fault switch. Test-only triggers hold an advisory
barrier immediately before an outbox or notification insert; an independent
connection verifies invisibility of uncommitted rows, then interrupts the
PHP worker or its SQL connection. The barrier is removed in cleanup.

Required cases:

- Connection killed after a trade has written tentative state, receipt,
  journal and score projection. Exact table fingerprints must match the
  pre-attempt snapshot; retry produces one trade and two earned scores.
- Worker process terminated during due construction, with its SQL
  connection cancelled to force prompt cleanup. Four independent restarted
  workers compete; exactly one event commits. Repeated worker is inert.
- Connection killed between message and notification writes. Both roll
  back; repeat request delivers one message and one notification.
- Actor request already admitted but waiting on the world lock while
  membership is revoked. Recheck rejects the mutation; private score and
  entitlement reads fail, revoked bearer cannot replay the old receipt.
- Actual loopback HTTP connection abandoned after sending a complete
  command, followed by reconnect and exact request identity recovery.
- Clock regression denied without state change.
- Seeded 512-case transfer/conservation and typed-input property tests,
  canonical path rejection and existing duplicate-JSON fuzz.

The harness compares canonical state, command receipts, events, journal,
ledger, outbox, stock projection, score projection/watermark, buildings,
messages and notifications. It checks exact replay, conservation, score
oracle, zero remaining events and one outbox envelope per journal entry.
Artifacts contain aggregate counts, fault names and source/checkout SHA,
not credentials, actor identifiers, raw requests or database dumps.

## Scope and operational notes

CI acceptance is scoped to the stated fault boundaries and fixtures. The
separate F02 load profile and F03 four-process recovery test cover different
dimensions; this is not a combined long-running 32-client fault storm.
Membership is rechecked under transactional locks; session revocation
prevents subsequent bearer admissions, not cancellation of every operation
already admitted before revocation. External ingress, log retention,
infrastructure secrets, physical backup policy and target capacity require
operator configuration and acceptance. Review the dependency artifact and
all ten exact-head workflows before merging. Any release authorization
remains separate.

## Observed F03 qualification

PR head `caa7f43bcb62cd1f99ebfbd6bb7206a1b64d60ff`,
[M12 run 45607286491](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45607286491).
Defensive HTTP job 139181338657, resilience job 139181339011 and dependency
audit job 139181339186 passed. 512 deterministic transfers: 224 accepted,
288 correctly denied. All six fault cases passed; 4 restarting workers,
18 journal entries and 18 outbox entries, one notification, 0 pending
events, 2 score rows and verified conservation/projection. Checkout
`846448a87f675c24d3c29bfe4b1eab2fa8e952e5`. All ten pre-merge workflows passed,
merged at `5c6b93fa`.
