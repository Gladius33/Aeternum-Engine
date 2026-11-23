# M15 — Optional progression and engagement protections (in development)

M14 was merged in main and seven post-merge workflows passed. M15 is a
new macro; the V1.0 release remains gated by issue #55.

## Implemented in the first M15 increment — authoritative scoring

Opt-in JSON section: progression. Required keys are:
- building_points: development weights by allowed building type
- upgrade_points: development weights by allowed resource ID
- military_building_points: military weights by allowed building type
- trade_points: development points for each completed barter involving
  the player, counted once from its committed market-offer prefix

ScoreRules validates exact keys, types, references, non-negative weights,
and bounded values. The entire exact manifest remains SHA-256 pinned by
the existing GamePack digest. Existing packs without progression retain
their identical artifact and rule identities.

ScoreProjector derives integer scores from completed buildings, upgrades
and filled trades in the canonical state: development, military, global.
There is NO independent score counter or client-provided score mutation.
Queued buildings/upgrades earn nothing until successful completion. An
idempotent market request cannot produce a second scored trade.

PdoScheduler::scoreForActor reads under the world transaction lock, with
active membership, account ownership, immutable rule-key verification
and a due-event barrier. Its reads do not mutate the world state.

ProgressionHttpApi offers:

    GET /v1/worlds/{world}/progression/{account}
    Authorization: Bearer {token}

Only the owning actor may read their account. Access is rate-limited to
60 requests/minute per actor. Worlds without scoring return not-found.
No other players' scores or private world details are disclosed.

## Immutable fixture and acceptance

games/reference-guild/versions/0.2.0/manifest.json is a new, separately
pinned reference campaign with economic and military scoring; the former
0.1.0 manifest remains untouched.

PHP tests cover stable pinned identities, score categories, bounded
arithmetic/state size, unknown definitions and malformed JSON.
Real MariaDB tests cover two independently authenticated players, filled
barter with identical idempotent retry, completed constructions, delayed
upgrade, stale-event barrier, cross-player authorization, read purity and
replay consistency.

## M15 milestones still open

- Engagement protection: power ratios, novice shielding, diplomacy and
  cooldown integrated into the authoritative transaction. Delayed effects
  must re-evaluate policy at due time. This increment does not add combat.
- Privacy-safe ranked pages and bounded materialization; no global
  ranking exists yet.
- Full exact-HEAD CI and broader concurrency, failure and browser tests.
- Validation of all relevant criteria before declaring M15 complete.

M16 bot runtime and publication V1.0 qualification remain separate.
