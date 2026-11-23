# M16 — deterministic optional actor bots (first increment)

The original engine remains a shared, general-purpose asynchronous strategy
engine. Bots are never a mandatory component of a game pack. They must not
masquerade as human participants or bypass security/quotas.

## First increment: bounded authenticated economic actions

- ApprovedBotStrategy is an operator-reviewed PHP strategy, not a name or
  executable path parsed from a manifest. The runtime requires an explicit
  list of approved lowercase SHA-256 source-file digests, rejects empty
  registries when enabled, and independently hashes the PHP strategy class
  file at invocation. A strategy's own codeDigest() claim cannot grant access.
  Operator approval must pin an audited release artifact, not a hash
  computed blindly from user-provided content. Transitive dependencies and
  configuration require independent release review.
- BotPlan permits only economy.settle or economy.upgrade and exactly the
  bot's own account/resource in the payload. Combat, transfer, social
  interactions and premium endpoints are not eligible.
- ResourceUpgradeBot is a conservative example, selecting an affordable
  upgrade or settling production from a *human-visible* read-only economy
  overview, not the world state, SQL or a private projection.
- TrustedBotRuntime defaults to PAUSED. The operator must explicitly
  enable each tick and provide a valid normal Bearer session.
- The runtime uses authenticated EconomyHttpApi requests: **at most two GETs**
  (own durable receipt, then economy overview only if no receipt exists)
  and **at most one POST per tick**. The receipt route enforces session,
  world rules, account ownership and transaction locking. It exposes only
  the own actor command receipt, never another account or world snapshot.
  Normal rate limits, due-event barriers and replay still apply.
- A 60-second logical tick gives a stable deterministic command ID
  derived from world, account, resource and period. The durable receipt is
  consulted before recalculating a plan from a changing overview. This
  guarantees that sequential retries after restart or code changes cannot
  submit a second or conflicting action in the same period. Concurrent
  runners remain bounded by the same database command-identity constraint.
- A foreign human session or revoked bot session cannot act as the
  bot's account. No API exposes a bot control or grants elevated privileges.
- The sample is entirely deterministic without LLM/network calls.

## Acceptance and planned hardening

Unit tests check strict action schema, malformed overviews, reviewed
source digest, cost-aware upgrade selection and no privilege expansion.
MariaDB tests also reject a missing operator allowlist and a spoofed digest.

Real MariaDB tests create separately authenticated bot and human actors,
prove paused-by-default has no mutations, permitted upgrade scheduling,
same-tick idempotence across process restarts and strategy changes,
receipt privacy, foreign actor rejection, due-time worker execution,
next-tick action selection and deterministic canonical replay.

Before shipping a bot population manager, the operator MUST additionally
provide bot-account identity metadata visible to players, explicit
credential lifecycle/revocation, measured CPU/memory/tick budgets,
restart cursor and fault injection, per-world policy activation,
anti-manipulation/anti-deception rules, metrics and a reversible ramp-down.
No background scheduler, player-facing human impersonation, LLM planning
or mass deployment is included in this first increment.

The operator supplies reviewed strategy instances and normal session
credentials through trusted server code. User JSON can neither load code
nor create bots. The M16 lab is **not** an unattended production bot system.
