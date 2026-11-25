# F07 — Release-candidate engineering dossier

Status: candidate preparation, not publication or operator signoff.
All seven F milestones are delivered as scoped engineering increments
when their exact-head workflows pass and they merge. The final M13
G01–G09 acceptance remains an independent decision. No gate is waived.

## Verified previous candidate baseline

F06 PR #94 exact head 5f2a8ec53c9ea3aafc75546323d257cd806f517e:
all ten PR workflows succeeded; merged at 3d43c00399bea0130c4eb16e985b85afc3321018.
These are historical observations, not evidence for a future SHA.

| Workflow | Exact F06 run |
|---|---|
| PHP core CI | [45681843784](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681843784) |
| Browser E2E | [45681843767](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681843767) |
| M10 isolated acceptance | [45681843914](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681843914) |
| M11 isolated operations acceptance | [45681844096](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681844096) |
| M12 security and supply-chain | [45681843901](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681843901) |
| M13 release readiness contract | [45681844099](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681844099) |
| G08 private local playtest | [45681844019](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681844019) |
| M14 third-pack browser acceptance | [45681844206](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681844206) |
| M15 progression browser acceptance | [45681843797](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681843797) |
| M16 isolated bot acceptance | [45681843965](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45681843965) |

Observed F02 capacity job 139444128545: 583 mixed operations, 768 reads
during soak, zero unhandled errors. 32 clients: 57.241 op/s, p95 1131.265 ms,
p99 2173.668 ms; soak p95 88.848 ms. 18 due retries, four intentional quota
429s, 367 replay entries, 54 filled trades, 53 completed buildings.
F03 job 139444128623: all six fault scenarios, 18 journal/outbox entries,
four restarted workers, one notification, zero pending events, conserved
ledger and verified projection. F06 job 139444128544: 42 restored tables,
132 identical row fingerprints, 13 replay entries, zero pending events,
existing login and research restored, runtime DDL denied, test grant/revoke
and replay. The G08 job succeeded and retained artifact 15055482319.
The F06 distribution artifact 15053414872 contains the PR-checkout package;
it is not the final main artifact. Retention expiry requires rerunning
the matching workflow, never substituting unrelated proof.

## Gate-by-gate engineering evidence and residual review

| Gate | Automated evidence required on final SHA | Remaining independent review / scope |
|---|---|---|
| G01 | Core transactions, replay; F03 killed transactions, worker restart and duplicate prevention | Canonical history bounded; quiesced large-world audit strategy |
| G02 | Registry pins, historical replay, ownership, cross-world denial, populated migration | No automatic incompatible world transformation; retain old artifacts |
| G03 | Ledger, escrow, projection and score consistency; F02 mixed load | Per-world write serialization and canonical snapshot growth |
| G04 | Reviewed SDK sample, third pack, three server-confirmed victories | Trusted PHP not sandboxed; frozen legacy API-1 compatibility |
| G05 | Auth, negative HTTP, browser FR/EN, keyboard, contrast, touch and retry | Independent security/accessibility and actual device review |
| G06 | Real independent trade, alliance, message, block/report, moderation, rank, bots | Bounded directories, no global opponent-score export; no destructive combat |
| G07 | Scoped operator roles, simulated cosmetic entitlement replay/revoke, attribution | No actual payment provider; legal/commercial review before enabling |
| G08 | Reproducible extracted package, migration, backup/restore, pinned health, restored login | Authorized target HTTPS, secrets, CRON, restoration and rollback drill |
| G09 | Ten workflows on exact candidate and exact main, dependency audit, F02/F03/F05/F06 reports | Target budgets, independent security review, operator acceptance and publication decision |

F02 and F03 cover bounded load and fault injection separately, not a
combined prolonged fault storm at 32 clients. F04 does not implement
territorial conquest, destructive combat or physical cargo movement.
F05 measures Chromium virtual frame intervals, not physical hardware FPS.
F06 uses isolated databases and test-only loopback ingress in the package
fixture. Target-specific capacity, HTTPS and recovery objectives require
actual authorized environment review. No maximum player count is certified.

## Reproduce and collect the final package

The existing ten workflows remain mandatory. M13 tests dossier rejection
conditions using synthetic fixtures only. G08 now requires all nine
campaign/experience/legacy report files, all twelve relevant PNG captures
and six measured desktop/mobile viewports before writing its exact-SHA
browser summary. F02, F03, M11 and M12 retain their separate artifacts.
M11 builds the package twice, checks full file inventory and exercises
the extracted package. CHANGELOG and current documentation ship with it.

After F07 merges and **both** sets of ten workflows finish, obtain a
clean checkout of the exact two-parent merge commit from main. Install
PHP 8.4, Git and authenticated GitHub CLI with read access to Actions
artifacts. Use a new destination inside a private directory (mode 0700)
outside the checkout. Example (replace both SHA values):

```sh
mkdir -m 700 "$HOME/aeternum-review"
php tools/f07-dossier.php PR_HEAD_SHA MAIN_MERGE_SHA "$HOME/aeternum-review/f07"
```

The collector verifies the merge parent and identical source tree,
exact PR-triggered and main-push workflow sets, and downloads the
specific artifacts of the successful main runs with gh. It requires
exact main report identities, load/recovery/restore/browser conditions,
a high/critical-free npm audit and CycloneDX inventory. It checks the
main archive digest against its build JSON and sidecar. It produces
dossier.json, unsigned-review.json, the main archive and retained
reports/screenshots. Run the [F06 extraction verifier](F06_DISTRIBUTION.md)
before installation. A failed collection leaves an incomplete directory;
use a fresh directory for a new attempt. Never treat a partial directory
as a release package. Artifacts can expire, and the API can be unavailable;
do not synthesize missing evidence. Re-run the specific eligible workflow
and check its latest status before recollecting.

The collector does not independently authenticate a downloaded file beyond
GitHub artifact provenance and checksum consistency, nor certify that
test code is correct. Review actual run logs, source and evidence and
retain the dossier outside the commit, with an independent trusted hash
or signature. The committed template stays deliberately unsigned.

## Operator review and decision

1. Review all nine gates and the actual evidence from the dossier. Record
   exact package SHA-256, target version, environment and reviewer.
2. Define target performance, availability, RPO/RTO and security budgets.
   Obtain explicit staging authorization; use dedicated credentials and
   a disposable database. Follow [deployment](F06_DISTRIBUTION.md) and
   [M13 runbook](M13_RUNBOOK.md); record actual results.
3. Complete the physical-device and human checklist in
   [F05](F05_PLAYER_EXPERIENCE.md). Review dependency inventory and all
   security boundaries independently; resolve known high/critical issues.
4. Review licensing and attribution, registration/privacy policy, support,
   backups and rollback. Keep payment mode simulated unless separately
   reviewed and approved.
5. Only after review, update a COPY of unsigned-review.json outside the
   source tree with verified gate results, cross-cutting checks and
   operator identity, SHA and UTC timestamp. Run:
   
   ```sh
   php tools/m13-release-gate.php /private/reviewed-evidence.json --enforce
   ```

A zero exit checks declarative completeness only. Independent authorization
to publish is a separate, explicit decision. No tag, release, external
deployment or payment is created by F07 or its dossier collector.

## Current guides

[Player](M13_PLAYER_GUIDE.md) · [API](API_V1.md) · [SDK](F01_SDK.md) ·
[Architecture](ARCHITECTURE.md) · [Deployment](F06_DISTRIBUTION.md) ·
[Acceptance](M13_RELEASE_ACCEPTANCE.md) · [Changelog](../CHANGELOG.md).
