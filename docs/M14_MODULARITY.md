# M14 — game-pack modularity, first implementation (IN PROGRESS)

M13 qualification tooling has been merged to main. V1.0 is NOT approved:
the remaining G01-G09 release gates are tracked in issue #55.

## Existing architectural debt

The core economy still embeds thematic settlement and industrial command
paths, and the GamePack class directly carries SettlementRules/IndustryRules.
Historic rule identities must remain stable for every pinned legacy world.

## Implemented first increment

- ApprovedPackCommand is an explicit PHP interface for reviewed handlers.
- TrustedPackCommands is a bounded, application-created dispatch table.
  Every supported command must be namespaced pack.module.action and its
  payload must be declared in the data-only pack action schema.
- The dispatcher rejects unapproved, duplicate, reserved or unpinned
  handler identities before execution. It validates payload fields and
  types before invoking any approved PHP handler.
- GamePack::rules() without extensions retains the exact old rules key.
  Passing approved extensions creates an effective digest containing the
  exact manifest SHA-256 and reviewed executable fingerprints. This
  prevents a new handler from silently reusing an existing world key.
- A third-pack fixture exercises a custom research action, schema checks,
  stable identities, absence-by-default, duplicate and invalid registration
  rejections. Its test is part of the normal PHP core CI.

## Second increment — actor-authorized extension admission (in PR)

The distinct ActorPackCommand interface opts one *reviewed* PHP handler
into player admission with a **non-empty set of owner-account field names**.
TrustedPackCommands validates the action payload schema and these
account-ID fields before dispatch; arbitrary trusted modules without the
new interface **remain private to operator calls**.

EconomyHttpApi only allows actor-approved pack.module.action types when
the server has constructed an exact pack/rules binding. New actions use
an additional per-actor write quota; the existing session and world
identity rules are unchanged. PdoScheduler verifies active world
membership AND every declared account's immutable ownership under its
world-locked MariaDB transaction. The speculative optimistic fast path
is explicitly disabled for custom handlers, so they cannot execute
before a locked authorization check.

The new MariaDB test runs a third pack against the real HTTP dispatcher
with two authenticated independent actors, checks matching receipts,
idempotent retry, malformed schema, foreign account access, cross-world
access, membership revocation and deterministic canonical journal replay.
No new SQL migration is needed.

This is an **explicit trusted-server composition**, not yet a plugin
installer or a new public endpoint automatically reachable through
ConfiguredWorlds. The actor handler remains trusted code and MUST not
introduce undeclared cross-account effects or out-of-transaction side
effects. A real production composition registry and release proof
are separate M14 work.

## Critical limitations of this first increment

This is for **operator-side trusted calls only**. EconomyHttpApi and the
actor-authorization switch inside PdoScheduler still deny custom commands:
this deliberately prevents a new actor-permission bypass. The fixture is
NOT yet a third playable game. No executable PHP is selected or loaded
by user-authored JSON, and registering a code digest is not a substitute
for independently verifying the deployed handler bytes.

No legacy gameplay has been modified or migrated in this increment.
No M14 release, M13 V1.0 certification, external staging or payment.

## M14 criteria still to develop and prove

1. Extract the hard-coded settlement/industry behaviors from mandatory
   core paths, using approved adapters with a replay-compatible migration.
2. Bind reviewed module implementations to an operator-controlled registry,
   never to arbitrary HTTP fields, manifest paths or dynamic uploads.
3. Introduce a separately audited, strictly actor-scoped admission path
   for pack actions: world, account, authorization, quotas, version pins,
   transactions and idempotent receipts checked atomically.
4. Introduce typed event extensions and replay-safe worker scheduling.
5. Prove a third fully playable pack through PHP 8.4, MariaDB concurrency
   and live Chromium without new theme branches in the core.
6. Retain older immutable pack versions and provide compatibility/rollback.
7. Full CI green for the exact final SHA before considering a merge.

Preserve ACCL-1.0, author attribution, existing persistence invariants
and the release gate in issue #55.
