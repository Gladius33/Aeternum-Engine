# F04 — Versioned campaigns and optional gameplay

New pinned packs: reference-medieval 0.4.0, reference-industrial 0.4.0,
reference-guild 0.5.0. The lab registry generates a separate module registry
with exact manifest/module/effective rules fingerprints. Its new world IDs
end in -f04, so existing populated worlds and their immutable manifests
are not rebound. Existing player logins and bot identities can enter the
new lab world after normal provisioning; no old state is overwritten.

## Campaign mechanics

Medieval and industrial retain the complete original settlement and
delivery objectives, construction costs, production, capacities and
cooldowns. The new approved campaign module adds two research levels,
one pending job, and up to three expeditions; research requires the
corresponding completed facility at the relevant level and unlocks
expeditions. A keep, freight terminal or caravan hub is also required
before an expedition. Four- and six-second durations are accelerated
reference-game values. Expeditions are completed logistical/exploration
studies, not a claim to land, a cargo transfer or a resource reward.
All new effects are journaled private state; no ledger resources minted.

Guild victory is authoritative: level 2 market hall, level 2 caravan hub,
level 1 watchtower, two research levels, two completed expeditions, and
three distinct filled barter offers involving the account. A cancelled,
expired, pending or repeated receipt is not an additional exchange.
Version 0.5.0 adds second building stages with actual silver, grain and
spice costs and prerequisites. The earlier guild versions keep their
historical guide-only behavior and scoring rules.

The reviewed guild challenge is immediate and non-destructive: it records
a bounded source-account challenge count, after the existing active
membership, ownership, novice shield, power ratio, cooldown and mutual
truce checks. No delayed hostile action is admitted; no combat casualties,
loot or territorial conquest are implemented. A proper delayed hostile
module would need a deterministic journaled due-time authorization outcome,
including changed membership, and independent transactional tests.

## Player API and UI

The authenticated discovery response advertises only actual reviewed
packActions and packViews. GET /v1/worlds/WORLD/economy/pack-view/campaign/ACCOUNT
uses the pinned rules and the world lock, verifies active membership and
account ownership, and returns an explicit aggregate projection, not raw
state or foreign scores. No generic state export. The private job state
is confirmed only after the worker commits. Ordinary actor-scoped commands
carry the original durable request ID. The modern French/English campaign
screen exposes research, expeditions, eligible guild challenges and truce
proposal/acceptance; a target is selected from the authenticated recipient
directory. The guild victory banner is read from the server, not calculated
from local artwork or a tutorial counter.

## Bots

Thirty explicit lab bot accounts still use the normal player APIs and
same quota/ownership checks. The planner can now develop watchtowers,
research and expeditions when their confirmed prerequisites are met.
It does not issue hostile challenges. Each 30-second cycle rotates its
starting position and stops admitting new bot work at a 25-second budget.
Transient failures retry the same serialized command once, then defer.
A stable per-bot/tick request ID prevents double effects on restart.
Set AETERNUM_LAB_BOTS_PAUSED=1 when recreating the bot service to pause
without deleting accounts, escrow or alliances; reset to 0 to resume.
The public roster exposes paused status, never tokens or private state.

## Acceptance and scope

New unit tests verify immutable registration, prerequisite and event
guards, isolation, exact victory authority and denied forged events.
MariaDB exercises three independently authenticated merchants, funded
construction, actual delayed jobs, exact idempotent receipts, novice
shield, cooldown, mutual truce, three trades, reconnect, score/economy
projection and deterministic replay. G08 extends the modern UI scenario
for each game to complete its campaign, two research levels and two
expeditions, then reconnect and verify the same confirmed progress.
The guild counterparty uses real authenticated HTTP escrow commands.
Legacy M05/M06 and M15 tests remain unchanged.

The new player view still reads a canonical snapshot and the trade goal
may scan the state until three qualifying offers. It is bounded by
the existing world state capacity, not a replacement for a general
indexed campaign history. Full territorial conquest, destructive combat,
physical cargo logistics and combined long-duration fault campaigns are
not represented as delivered. Additional balance calibration and device
review are separate acceptance activities.

## Observed F04 qualification

Exact head `d6127c4e32c89aad071c67555293edaca292bc6b` passed all ten
pre-merge workflows and merged at `d8660e4b`. [G08 run 45614401520](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45614401520),
artifact 15022580696: the three F04 campaign JSON records each show victory,
reconnection, research level 2 and two expeditions. Screenshots cover
desktop and mobile. The first G08 attempt did not start its tests due to a
duplicate JS binding; the corrected head passed.
