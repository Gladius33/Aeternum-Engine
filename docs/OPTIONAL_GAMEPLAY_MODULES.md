# Optional gameplay modules — approved architecture (NOT yet delivered)

Decision: 2026-11-23. These requirements are future implementation work, not
V1.0 features silently marked as complete. Aeternum is a persistent asynchronous
multiplayer **strategy** engine, not a real-time FPS engine.

## Kernel / pack separation
- Generic engine: world-pinned immutable rules, scheduler, ledger, auth,
  transactions, replay, deterministic events, quotas, migration and audit.
- Trusted game packs: theme-specific formulas, scoring weights, protection
  rules and bot strategies; no executable PHP/filesystem/network path imported
  directly from an untrusted manifest.
- The two reference games are examples, never mandatory dependencies.
- Historical SettlementRules and IndustryRules in core are architectural debt:
  migrate to approved plug-in strategies with replay compatibility and stable
  old-world behavior, not via a breaking rename.

## Independent opt-in capabilities

**Progression & Scoring**: deterministic integer/fixed-point event-derived scores;
distinct development/military/global metrics; configurable weight rules; no
client-provided authoritative totals; bounded projection rebuilding; deduped
event/receipt processing; old worlds without scoring stay unchanged.

**Engagement Policy**: authoritative server-side permitted-target calculation;
pack-controlled symmetric/asymmetric power ratios, novice shield, cooldown,
diplomacy/war state; re-evaluate at delayed command execution, not merely
frontend; no private information disclosed by denial; combat-free games are inert.

**AI Agent Runtime**: bots use exactly the validated commands and quotas of
human player actors, not direct world-state SQL updates; bounded CPU/ticks;
deterministic, resumable plans with idempotent IDs; game-defined goals and
strategies; operator pause/kill switch; clear bot identification; avoid
deceptive engagement; safe population ramp-down preserving debts and alliances.
Optional language-model planning is not required or privileged.

## Acceptance (all required before calling a module ready)
1. Enable any subset for a third distinct game without changing HTTP routing
   or hard-coding its lore in packages/core.
2. Strict actor/world isolation, authorization, quota, replay and migration tests.
3. Live disposable MariaDB concurrency tests show no resource creation, point
   duplication, invalid combat or bot command privilege escalation.
4. Verified E2E, bounded worker budgets and capacity, approved rules versioning.
5. No modification of ACCL-1.0, no real payment or external deployment.
