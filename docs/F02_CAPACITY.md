# F02 — Indexed progression and reproducible capacity qualification

## Score projection

Migration 032 adds sparse positive score rows indexed by
(world, global_score, account) plus a journal watermark. Zero-score
accounts need no score row. Rank is exact competition rank (1 + active
accounts with strictly greater score); participation counts active owned
accounts, including zero-score ties. Revocation and reactivation are
visible through the existing membership join. No opponent IDs or scores
are returned. The indexed path removes the old 128-account and global
100,000-state-key read caps. The legacy pure projector remains the oracle
and the pre-032 fallback.

Commands, CAS commits and due events update the derived score index in
the same InnoDB transaction as canonical state, receipts, ledger and
journal. Only changed weighted building/upgrade keys and filled trade
participants contribute deltas. Identical receipts never increment the
index twice. A stale/missing journal watermark triggers a transactional
rebuild from canonical state; the normal read path uses a shared lock and
small world metadata, not the JSON snapshot. Scores are bounded to the
existing integer range. The exact count may still scan an index range,
but no roster is enumerated in PHP. The canonical JSON/journal remains
the authoritative state and its write size still grows with world history.

## Upgrade and operator audit

Quiesce writers, retain a verified coherent backup, then run the normal
migration runner. The schema is additive; no existing migration or pack
digest changes. A populated pre-032 world is exercised in the capacity
fixture, including its original journal replay and a completed trade.
The first authorized score read or new mutation backfills the index.
Rollback keeps original historical artifacts and matching code; older
executables may advance the journal, so returning to this executable
rebuilds an out-of-date index. Never edit canonical history to repair scores.

```sh
php bin/score-index.php audit WORLD
php bin/score-index.php rebuild WORLD
php bin/score-index.php audit WORLD
```

Rebuild is an operator operation with the approved registry, world lock and
backup policy. Audit detects even same-watermark corruption; rebuild does
not change canonical journal, stock or membership. Restore verification
fingerprints the new tables with the other tables.

## Capacity protocol and fixed budgets

The PHP Core workflow includes a separate capacity job with isolated
MariaDB 11.4, PHP 8.4, Ubuntu 24.04 and a loopback multi-process HTTP
server using seven PHP workers. This exercises the actual HTTP front
controller with the CI-only trusted-ingress adapter, authentication,
sessions, quotas and pinned registry. No external service is contacted.
Synthetic test credentials and temporary configuration remain private.

The dataset contains 33 provisioned identities in one scored world with
real pre-032 state. The same growing world is used across profiles of
1, 4, 16 and 32 concurrent client threads. Per client: login, private rank,
balance, market read, social read, market creation, foundation placement,
barter acceptance, duplicate receipt, own score, construction start.
A separate bounded worker drains due construction events and records observed lock retries. Expected mixed
operations: 583, excluding intentional overload probes and soak.
The final audit expects 54 filled trades (including the migration trade),
53 completed constructions, no pending events, ledger/stock conservation,
and exact deterministic replay. The larger rank regression uses 258
active accounts, including zero-score ties and revocation/reactivation.

Predeclared isolated-runner guards (not service commitments):
- p95 <= 12,000 ms and p99 <= 20,000 ms; per-request timeout 25 s.
- Each mixed profile <= 180 s, no unhandled errors, due lag <= 10 s.
- Rate-limit saturation must return 429, another actor remains available,
  same actor recovers in the next minute window.
- Six rounds of 32-client read smoke, 15 seconds apart, 768 reads,
  same latency guard, no errors.
- Duplicate receipts, due retries and transient InnoDB contention are
  bounded; 1205/1213 failures return retryable 503 only after rollback
  and clients retain the exact original request ID.

The sanitized JSON artifact records source HEAD and checkout SHA, runner, workload, p50/p95/p99
per operation and aggregate, throughput, response status counts, retry
counts, runner CPU and sampled RAM, MariaDB I/O and lock counter deltas,
connections, observed due lag, snapshot size and journal length, rank
EXPLAIN, soak, overload recovery and final verification. Incomplete reports
remain explicitly incomplete. The database service has its own disposable
schema and is never shared with the integration job.

## Scope and remaining calibration

This is reproducible CI qualification for the declared dataset and
concurrency, not a maximum supported player population. Rank read work
is now indexed, while canonical JSON writes, journal replay, per-world
serialization and exact aggregate counts remain scaling constraints.
The evidence must be read before selecting operational capacity targets.
Long-running fault injection and recovery under interrupted writes are
tracked in F03; deployment-specific sizing and acceptance remain in F06–F07.
No release-evidence flag is promoted by this change alone.
