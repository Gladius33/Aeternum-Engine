# F01 — Shared world runtime composition (first increment)

## Contract

`WorldRuntime` owns the data-only approved pack map and the corresponding
trusted rule map. The application composition point accepts already reviewed
command/event objects, never executable names from player requests or JSON.
The default composition preserves all existing world rule identities.

The HTTP front controller shares a lazily initialized runtime between
registration, economy, progression and settlement/industry projections.
CLI provisioning, autonomous worker, bounded CRON, projection
materialization/replay and lab bots use the same composition entrypoint.
Direct service callers retain their existing default signatures, with
optional approved rules passed explicitly where necessary.

RuleSet now retains optional source manifest provenance. A supplied binding
must match pack ID, version and the exact source SHA-256; a different
manifest with the same version is rejected. The canonical effective
RuleSet key remains SHA-256 of the same three historical values (ID, version,
effective artifact digest); provenance does not enter its key. Existing
packs, manifest bytes and SQL migrations are unchanged.

## Acceptance

Core tests check shared/default identity, per-world isolation, manifest
provenance, unknown world denial and an empty runtime. The MariaDB
delayed-event fixture uses the common runtime for onboarding, authenticated
commands, worker completion and deterministic replay. Existing HTTP, CRON,
bot and three-game tests exercise the unchanged default composition.

## Remaining F01 work

- Complete reviewed application-level module selection and independent pack
  examples through the common composition point.
- Remove remaining thematic coupling while preserving historical
  command/event semantics; complete typed module contracts and
  authorization/side-effect tests.
- Document explicit version migration/rollback, SDK scaffolding and
  validator tooling; verify end-to-end custom pack integration.
- Close F01 only after the [finalization roadmap](FINALIZATION_ROADMAP.md)
  criteria and exact-head CI evidence are satisfied.
