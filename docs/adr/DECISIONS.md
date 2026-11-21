# Architecture decisions

- ADR-001: modular monolith, independent domain core; framework confined to adapter.
- ADR-002: discrete events and lazy simulation rather than global ticks.
- ADR-003: UTC integer timestamps, stable sequence for ties; separate logical time and processing time.
- ADR-004: MariaDB atomic transaction and first-stage world lock; no claims of persistence for memory adapter.
- ADR-005: fixed-point integers, documented overflow bounds and retained production remainders.
- ADR-006: scoped world IDs, database constraints, server-side authorization.
- ADR-007: versioned validated game packs, no eval or user-provided executable code.
- ADR-008: journal and outbox with idempotent consumers, no external side effects in handlers.
- ADR-009: bounded CLI/CRON execution for shared hosting; optional dedicated workers.
- ADR-010: two reference games verify theme independence.
- ADR-011: regression and integration gates before merge.
- ADR-012: ACCL-1.0 unchanged, third-party license inventory and explicit CLA for external contributions.

- ADR-013: MariaDB event transactions lock world first, commit one event at a time, retain completed rows for idempotency, budget checks between transactions. No transaction spans external effects.

- ADR-014: world-locked server-time command intake with distinct durable command IDs, canonical payload comparison and exact prior receipts; pending due events block new commands. Follow-up events commit with the command or not at all. No external I/O inside handlers.
