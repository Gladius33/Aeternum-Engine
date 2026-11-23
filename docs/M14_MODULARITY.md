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

## Third increment — operator-controlled world composition

TrustedWorldRules::bind() joins a strict, already approved map of
GamePack worlds with explicit, already instantiated PHP modules. Unknown
world IDs, unreviewed string references and malformed handler lists are
rejected. A module enabled for one world is **not automatically enabled**
for a different world using the same game pack.

The production front controller now uses this composition root with an
empty extension map. This is intentionally **behavior-preserving**: no
pack module becomes public until the application operator explicitly
provides the reviewed PHP objects through trusted server composition.
PlayerProvisioner and WorldWorker accept an optional RuleSet from that
trusted composition, require matching pack ID/version, and still depend
on PdoScheduler's immutable world rules-key comparison. Their default
call paths remain unchanged.

MariaDB CI covers actual operator-side player provision, authenticated
extended command, worker entry, wrong-default-rule denial and journal
replay. This is not yet an external plugin installation facility or
deployment validation.

## Fourth increment — approved delayed event handlers (in PR)

ApprovedPackEvent and TrustedPackEvents provide an explicitly registered
trusted event adapter. Every custom event type must be in the
pack.module.event namespace AND declared in the immutable pack event-hooks
schema; duplicate, reserved, undeclared or unpinned event handlers are
rejected. Existing built-in event handlers remain the fallback.

GamePack::rules($commands,$events) accepts separate reviewed command
and event implementations. Event fingerprints are composed into the
existing pinned rule identity using a separate domain separator;
no-extensions and command-only callers retain their previous keys.

TrustedWorldRules::bind() can bind event adapters on a per-world basis.
Every event continues to execute under the canonical PdoScheduler world
lock, journal, retry and deterministic replay. The added tests verify
event schema rejections, code-digest pinning, per-world isolation,
delayed scheduling through real authenticated HTTP, exact due-time
execution via the production WorldWorker, no second award after retry,
and full MariaDB replay.

Only trusted PHP code can implement these handlers. No manifest path or
user-controlled class can be evaluated by the event dispatcher.

## Fifth increment — thematic module extraction and third data-only game

**Reference implementations extracted from generic economic dispatch:**
ReferenceSettlementCommand and ReferenceIndustryCommand are distinct,
trusted command adapters. EconomyGameplayPort provides the existing
canonical resource settlement/ledger operations. EconomyCommandHandler
now dispatches through a fixed, constructor-registered compatibility
map and contains no settlement/industrial action implementation.
The old command names, serialized state keys, durable journal format
and unchanged manifest/rule identity remain supported. These
historical adapters are not dynamically loaded from JSON.

**Third game:** games/reference-guild/manifest.json defines
Guild Caravans with silver, grain, spice, two construction facilities,
resource-production upgrades and ordinary market/barter progression.
Its boolean player_homestead flag enables the *generic* automatic
grant of an owned construction plot, without turning on either
medieval settlement or industrial mechanics. Omitted flag defaults
to false, preserving existing historical worlds. Values other than
JSON booleans, or a homestead with no construction definitions,
are rejected at manifest validation.

In Browser E2E, two provisioned, separately authenticated guild
players interact through the real HTML client. One places and
completes both construction facilities using the production worker;
a second player is denied control of the first player's building.
A persisted guild barter is executed and retried without duplicate
settlement, and the first player upgrades silver production through
a genuine delayed engine event. Cross-world read access is refused.
This verifies a complete small *trade/construction/progression
campaign*, not a general victory-points module (reserved for M15).

The third pack provides **no new PHP branch for its identity** inside
the economic command dispatcher. It deliberately relies on existing
generic resource/market/construction APIs; the existing browser
client provides its interface. This is a playable reference campaign,
not a polished commercial release.

## Current security and product limitations

General trusted handlers remain operator-only. ActorPackCommand handlers
are admitted only when explicitly composed into the selected world, their
payload is declared in the immutable pack, and account ownership is
verified transactionally. The third-pack HTTP/MariaDB tests establish a
working secure integration seam, **not a third commercially playable game**.
The production front controller deliberately binds no custom handlers.
No executable PHP is selected or loaded by user-authored JSON, and
registering a handler digest is not a substitute for independently
verifying the deployed bytes.

No legacy gameplay has been modified or migrated in this increment.
No M14 release, M13 V1.0 certification, external staging or payment.

## M14 criteria still to develop and prove

1. Sustain the extracted settlement/industry compatibility adapters with
   immutable historical pack keys and longer-term modular module boundaries.
2. Extend the operator-owned world composition API into the full deployment
   bootstrap path for HTTP, provisioning and workers, retaining identical
   pinned identities and refusing unapproved updates.
3. Complete actor-scoped security coverage with more complex multi-account
   action policies, concurrency and replay tests under real MariaDB; verify
   that all extension side effects remain within trusted transaction rules.
4. Expand typed event extension coverage to crash/recovery races, bounded
   worker budgets and legacy-to-extended world migrations.
5. Extend the third Guild Caravans browser campaign to richer gameplay
   and automated server-authoritative victory criteria before commercial use.
6. Retain older immutable pack versions and provide compatibility/rollback.
7. Full CI green for the exact final SHA before considering a merge.

Preserve ACCL-1.0, author attribution, existing persistence invariants
and the release gate in issue #55.

Operator composition accepts empty world sets during initial installation; this is regression-tested to preserve pre-M14 behavior.
