# M05 — Medieval settlement progression (work in progress)

This is **not** a completed playable reference game. The opt-in `reference-medieval@0.2.0` manifest introduces a first declarative construction progression; the original `0.1.0` bytes remain unchanged and worlds remain pinned by their manifest SHA-256.

## Progression model

| Building | Level 1 (wood/grain; seconds) | Level 2 | Prerequisites |
| --- | --- | --- | --- |
| Lumberyard | 12 / 4; 15 | 45 / 12; 45 | Farm 1 for level 2 |
| Farm | 18 / 8; 20 | 50 / 20; 60 | Lumberyard 1 for level 2 |
| Granary | 28 / 15; 30 | — | Farm 1 |
| Keep | 60 / 25; 60 | 120 / 65; 120 | Lumberyard 1 + Farm 1 for level 1; Lumberyard 2 + Farm 2 + Granary 1 for level 2 |

Costs use the existing atomic construction debit and scheduled worker completion from M04. The keep at level 2 is a **candidate objective**, not yet a server-authoritative win condition. The manifest does not yet attach building levels to population, food consumption, production rates or territorial expansion.

## Operator-controlled opt-in

Bind a disposable world to pack `reference-medieval`, version `0.2.0` and the **SHA-256 of the exact manifest bytes** in the private registry. Never repoint an existing pinned world to this version in place. Existing `0.1.0` worlds must remain on their original digest. Apply and verify migrations in a disposable MariaDB database before any persistent-world experiment.

## Settlement progression preview (M05 incremental)

Authenticated players can request `GET /v1/worlds/{world}/construction/progression` with their bearer session. The response contains only their foundations, current completed levels, pending work, the next immutable stage, missing prerequisite levels, and a read-only `ready` hint. A missing prerequisite reports both `required` and `current` levels; `ready` is never a construction authorization or a stock/affordability guarantee. The command engine remains authoritative for resource debit, prerequisites, and concurrent updates. Results are bounded to 100 owned foundations and protected by the existing session, membership, world isolation and read-rate limit. This is a gameplay planning API, not yet a complete medieval UI or victory evaluator.

## Player onboarding without administration

When a player registers in an approved `reference-medieval` world that
contains pinned building definitions, the trusted provisioner automatically
grants their own homestead territory at their existing settlement location
and installs the immutable world catalog. Each new player's territory is
their own unique actor ID. Repeated provisioning is idempotent and does not
silently place or pay for a building. Other worlds and the original unversioned
medieval pack retain their previous behavior. The existing construction
choices screen lists these new settlements for the player.

## Colonists, work and victory (opt-in v0.3.0)

A new immutable `reference-medieval@0.3.0` pack extends 0.2.0 with a
strict declarative settlement ruleset. `settlement.feed` consumes four
units of grain to add one resident, bounded by farms and granaries.
`settlement.work` consumes two grain and produces 15 wood per
lumberyard level, requiring two residents and a ten-second logical
cooldown. The account's stocks, population, ledger, command receipt and
cooldown are committed atomically. Every rule is pinned by the exact
manifest digest; 0.1.0 and 0.2.0 cannot invoke these actions.

The private authenticated `GET /v1/worlds/WORLD/settlement/status/ACCOUNT`
reports the same account's residents, capacity, labor output and
server-authoritative win result. Victory needs keep 2, farm 2,
lumberyard 2, granary 1 and six residents. The server computes it from
the canonical, replayable world state — never from a browser flag.

The MariaDB suite now exercises a two-colony campaign from empty signup to
keep level two and population six, including deficits, prerequisites,
real worker completions, food-for-wood conversion, cooldown, idempotency,
cross-actor authorization, reconnect, victory and full replay.

## Browser tutorial

A newly registered player in approved medieval version 0.3.0 sees a
colony panel with a server-derived population counter and explicit
victory milestones. The existing foundation screen offers settlement
construction without any CLI. Player actions `settlement.feed` and
`settlement.work` reuse the authenticated, idempotent command envelope.
The displayed victory is the backend's decision, never a browser flag.
Other games and legacy versions do not show this tutorial.

## Evidence and outstanding work

`tests/m05-medieval-pack.php` exercises manifest loading, deterministic stage prerequisites, malformed-rule rejection and side-by-side version pinning. It runs in `tests/run.php` as part of PHP core CI. The strict JSON schema now documents the existing runtime building extension.

**Not yet proven:** new player settlement provisioning and geography, player-driven building type registration for an approved world, UI onboarding/tutorial, meaningful production/population mechanics, server-authoritative victory objective, full two-player MariaDB and Chromium gameplay, migration rehearsal on populated medieval worlds, and reconnection/replay acceptance. The M05 PR must remain draft until these are implemented and tested; no production deployment is authorized.
