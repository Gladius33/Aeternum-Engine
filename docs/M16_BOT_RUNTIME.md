# M16 — deterministic optional actor bots (first increment)

The original engine remains a shared, general-purpose asynchronous strategy
engine. Bots are never a mandatory component of a game pack. They must not
masquerade as human participants or bypass security/quotas.

## First increment: bounded authenticated economic actions

- ApprovedBotStrategy is an operator-reviewed PHP strategy, not a name or
  executable path parsed from a manifest. codeDigest() is a mandatory
  lowercase SHA-256 identity for reviewed code.
- BotPlan permits only economy.settle or economy.upgrade and exactly the
  bot's own account/resource in the payload. Combat, transfer, social
  interactions and premium endpoints are not eligible.
- ResourceUpgradeBot is a conservative example, selecting an affordable
  upgrade or settling production from a *human-visible* read-only economy
  overview, not the world state, SQL or a private projection.
- TrustedBotRuntime defaults to PAUSED. The operator must explicitly
  enable each tick and provide a valid normal Bearer session.
- The runtime invokes the unchanged EconomyHttpApi for **one GET** and
  **at most one POST per tick**, so it passes through exactly the
  human authentication, rate limits, due-event barriers, account ownership,
  pinned world rules, transaction locks, receipts, and replay.
- A 60-second logical tick gives a stable deterministic command ID
  derived from world, account, resource and period. A strategy/code
  change within one period cannot bypass idempotent command IDs.
- A foreign human session or revoked bot session cannot act as the
  bot's account. No API exposes a bot control or grants elevated privileges.
- The sample is entirely deterministic without LLM/network calls.

## Acceptance and planned hardening

Unit tests check strict action schema, malformed overviews, reviewed
code digest, cost-aware upgrade selection and no privilege expansion.

Real MariaDB tests create separately authenticated bot and human actors,
prove paused-by-default has no mutations, permitted upgrade scheduling,
same-tick idempotence, foreign actor rejection, due-time worker execution,
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
