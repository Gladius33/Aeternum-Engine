# Aeternum — Finalization roadmap F01–F07

This is the active, consolidated engineering backlog after G25. Historical M01–M16 and G01–G25 records are retained for traceability. The nine release gates in [ROADMAP.md](../ROADMAP.md) remain normative. All implementation, automated validation, reproducible evidence, packaging and documentation below are repository development tasks.

## Execution contract
- G25 merged after all ten exact-head workflows succeeded (PR #86).
- Resume the first incomplete milestone and any open PR before starting another. Deliver large coherent changes, including tests and documentation, not one iteration per fix.
- Inspect source and CI, implement, test, open/update PR, fix failures, require all ten exact-head workflows green, merge and check main. Record PR, SHA, runs, results and residual work here. A PR alone is not completion.
- Preserve ACCL-1.0, attribution, transaction boundaries, immutable rules, old-world replay, isolation and resource conservation. No secrets, live payment or external action without permission. New balance rules require new pinned versions.

## F01 — Modular engine and SDK (G01–G04)
- [ ] Inventory and remove remaining thematic coupling from generic core, including GamePack rule references and compatibility dispatch, without changing legacy identities.
- [ ] Complete trusted command/event composition across HTTP, provisioning, workers, registry and replay; strict schema and executable identity checks, no arbitrary manifest code loading.
- [ ] Define explicit version upgrades/migrations/rollback; test old-world replay, multi-account authorization, bounded delayed events, transactional side effects and races.
- [ ] Deliver SDK scaffolding, an independent sample pack, validation tooling and developer/API documentation.
- [ ] Prove a new pack/module works without editing generic economic dispatch or routing, with real MariaDB tests.

## F02 — Scalability and indexed projections (G01–G03, G06, G09)
- [ ] Replace the 128-account rank limit with indexed, bounded, privacy-safe rank/score projections; deterministic ties, rebuild, membership, migration, idempotence.
- [ ] Profile world locks, snapshots, journals, pagination, SQL plans, memory and quotas; remove measured bottlenecks without weakening consistency.
- [ ] Reproducible HTTP/MariaDB mixed read/write workloads at 1/4/16/32 concurrent clients: builds, trade, due events, login, social and rank.
- [ ] Define budgets before runs; report throughput, p50/p95/p99, CPU/RAM/I/O, SQL connections/lock waits, queue lag, errors, saturation and recovery, runner and dataset.
- [ ] Add overload guards, long-duration smoke, conservation/concurrency checks and sanitized machine-readable evidence.

## F03 — Security, resilience and recovery (G01–G03, G05–G09)
- [ ] Review authentication, enrollment, recovery, sessions, revocation, CSRF/CORS/CSP/TLS, encoding/XSS, configuration, authorization, economy, social, admin, premium and dependencies.
- [ ] Expand property/fuzz, negative and real multi-process MariaDB tests: stale permissions, quotas, isolation, races and idempotency.
- [ ] Inject worker termination, dropped replies/connections, interrupted transactions, clock regression, duplicate events and restart under load; compare ledger, journal, stocks, notifications, projections and replay.
- [ ] Verify secrets, least privilege, log redaction, SBOM and advisories; correct known critical/high defects; publish scoped security and recovery evidence.

## F04 — Reference-game completion and optional mechanics (G02–G06)
- [ ] Medieval: complete modern-UI server-confirmed victory/reconnect, deepen construction, research, territory and optional units.
- [ ] Industrial: complete modern-UI delivery/victory/reconnect, deepen chains, logistics, capacity and research.
- [ ] Guild: new pinned lab worlds with approved progression, engagement and truce modules; expose supported commands, complete authoritative trade campaign and victory.
- [ ] Add independent optional research, claims, movement/transport and combat modules as appropriate; reauthorize delayed engagements at execution, enforce novice shield, cooldown and diplomacy.
- [ ] Improve bots: goals, economic planning, fairness, bounded runtime, retry, pause/resume and clear identification; no privileged state writes.
- [ ] Version balance fixtures and progression curves; test independent players, loss, insufficiency, concurrent actions, replay and historical compatibility.

## F05 — Player experience and graphics (G04–G06)
- [ ] Inspect actual G24/G25 captures of all themes and both viewports; correct concrete defects.
- [ ] Improve original terrain, architecture, map readability, icons, responsive layout, animation/audio and feedback; bounded rendering and no fictitious authoritative state.
- [ ] Consolidate client modules and typed contracts, French/English dynamic strings, accessible focus, keyboard, contrast, touch, reduced motion and error/reconnect paths.
- [ ] Collect real browser captures and frame/interaction/DOM metrics; provide human/physical-device checklist.
- [ ] Complete modern UI E2E in three packs with independent actors, trades, alliances, messages, moderation and reconnect.

## F06 — Portable installation, operations and distribution (G07–G09)
- [ ] Vendor-neutral PHP 8.4/MariaDB shared-hosting and server guides, preflight, secure webroot, permissions and runtime requirements.
- [ ] Repeatable installation, populated-world upgrades, migration, backup/restore, rollback, worker/CRON, metrics, alerts, TLS and secret configuration.
- [ ] Automate clean installation and populated-world migration/restore on isolated CI infrastructure; compare canonical data, digests, replay and existing player access.
- [ ] Reproducible checksum-verified archive without tests, lab credentials or secrets; document dependencies, deployment contents and operator handoff.
- [ ] Verify simulated payment-event idempotence, entitlements/revocation and attribution; document optional secure payment integration without initiating payments.

## F07 — V1.0 engineering acceptance (G01–G09)
- [ ] Refresh player, developer, API, SDK, install and operational docs; reconcile stale historical statuses and links.
- [ ] Reconcile each normative gate with exact-SHA tests, traceable evidence, limitations and remaining defects.
- [ ] Execute complete multi-player campaigns, security, capacity/failure, migration, restore, package, browser and bot acceptance.
- [ ] Verify all ten workflows on exact candidate and main; assemble reproducible release-candidate package, evidence dossier, changelog and compatibility notes.
- [ ] Prepare independent evidence manifest and acceptance checklist under [M13](M13_RELEASE_ACCEPTANCE.md); close engineering blockers only on evidence. Maintainer release authorization is a separate decision.

## Operator handoff
Provide archive, preflight/configuration instructions, validation commands, security/capacity evidence, rollback and device-review checklist. The operator selects target configuration and acceptance budgets, authorizes external access, reviews evidence and decides publication. Do not manufacture approval or modify the release manifest to force success.

## Progress
| Milestone | Status | Evidence |
|---|---|---|
| G25 | Merged | PR #86, 10/10 pre-merge workflows, merge df37cc0 |
| F01 | Merged | #88 and #89, each 10/10 pre-merge CI; final merge 09a2bd36; [SDK and compatibility](F01_SDK.md) |
| F02 | Merged | #90, 10/10 pre-merge CI, merge 31e845ad; [capacity protocol and measured results](F02_CAPACITY.md) |
| F03 | CI qualification | [Security and resilience acceptance](F03_SECURITY_RESILIENCE.md) |
| F04 | Planned | — |
| F05 | Planned | — |
| F06 | Planned | — |
| F07 | Planned | — |

This is a scope and evidence contract, not a percentage or time estimate. Additional commercial-game content beyond the reference-game scope may be prioritized separately.
