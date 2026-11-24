# Aeternum: World Politics — serious-game charter (WP01, experimental)

## Product definition

World Politics is a **separate browser game** built on Aeternum Engine, not
a change to the medieval, industrial or Guild Caravans gameplay. It is a
persistent, replayable political/economic/diplomatic simulation grounded
in real-world reference data, with a clearly marked counterfactual world
after the scenario's starting timestamp.

Two presentation modes may share the same engine: an accessible strategy
game and an inspectable serious-game simulator. Neither is a real-world
political forecast. Compare outcomes only under explicit assumptions,
data-version pins, model uncertainty and reproducible initial conditions.

## Identity and government requirements (approved scope)

- Choose a real public political figure with date-stamped, source-attributed
  career and public programme; or create a fictional president with generic
  avatar, name, biography, party and individually defined proposals.
- Choose a historical starting office holder, an explicitly alternate
  presidency, or a political-career scenario. Alternate presidencies never
  claim that a fictional election actually occurred.
- Mix real and fictional personalities in the cabinet and civil service;
  nominations are proposals with a distinct acceptance/decline step, not
  automatic assertions about the real individual's preferences.
- Country-specific **office definitions and constitutional rules** determine
  nomination authority, oversight and legal consequences. The WP01 prototype
  only accepts an operator-supplied office allowlist; it does not implement
  French appointment law.
- Preserve two separate records: immutable, externally sourced historical
  biography versus simulated roles, relations and actions within a world.
- Real programme promises must link to actual published sources, not invented
  campaign positions. Fictional policies are marked fictional.
- Player-authored identities/content require moderation, source licensing,
  identity/spoofing protection and authorization before public availability.

## Wider simulation scope (planned, NOT implemented)

World reference data: UN M49, geographical boundaries and contested statuses,
economic/demographic series with dates and license metadata. Political systems:
institutions, elections, cabinet formation, civil society, courts, legality
of decisions, protests and constitutional crises. Defence: public budgets,
personnel, conscription, reserves and training delays; no invented classified
military facts. Diplomacy: state-to-state relations, negotiated treaties,
international organizations, join/withdrawal procedures and obligations.
Religion: population and institutional datasets without inferring individual
political behavior from religious affiliation. Economic simulation: budget,
debt, trade, production and distribution with calibrated uncertainty.

Coercive or clandestine political abuses may appear as **abstract fictional
events and consequences**, not operational instructions or a claim that any
real person would perform them.

## Architecture and security invariants

- Aeternum keeps control of identity/session admission, world membership,
  authorization, transaction locks, delayed events, rule-version SHA-256
  pinning, idempotent receipts and authoritative journal replay.
- World Politics handlers must be explicitly reviewed/trusted and pinned
  through the game's composition root. Never execute code from JSON profiles.
- No API may authorize a president or official solely because a client
  declares that title. A server-side world actor-role/office authorization
  model and legally modeled powers must precede any mutation endpoints.
- Historical source data and generated simulation state remain separately
  persisted; no dynamic data refresh silently changes a running campaign.
- A live world must not be migrated by rewriting an old rules digest.
- The lab must demonstrate isolation between national rosters, worlds,
  players and immutable snapshots before exposing any gameplay endpoint.
- The existing three reference games and all pinned worlds must continue
  functioning unchanged.

## WP01 deliverable in this PR

A **pure PHP domain prototype** under
\`games/world-politics/src/PoliticalRoster.php\` with:
- real-versus-fictional provenance validated at ingestion;
- bounded programme measures and HTTPS source metadata;
- national office allowlists, initial head of state, mixed roster;
- proposed, accepted and declined appointments;
- deterministic sorted snapshots and SHA-256 snapshots;
- isolated executable PHP unit/negative tests and CI.

This code is **not yet wired** to MariaDB, authenticated HTTP, the official
Aeternum event journal, the browser UI, the base engine pack registry, or
actual data importers. It deliberately does not invent a political party
or biography data set. Tests use synthetic people/URLs only.

## Back-end-only development decision (24 November 2026)

The currently evolving Aeternum Engine graphics and generic templates MUST NOT
be used as a foundation for this game's frontend. **No browser UI, Vue game
screen, CSS, avatars, image creation or UI E2E may be developed now.** World
Politics development is limited to the domain backend, deterministic simulation,
state persistence, policy AI, datasets, versioned REST API, schemas and tests.
The future web, Android, iOS and optional desktop clients consume the **same
server-authoritative RESTful API**. See [WORLD_POLITICS_BACKEND.md](WORLD_POLITICS_BACKEND.md).
This game branch MUST NEVER be merged into Aeternum Engine main; it is a temporary
workspace awaiting migration to a dedicated game repository.

## Next acceptance milestones — headless backend only

1. Versioned source ingestion pipeline, reference-data snapshots and license audit.
2. Immutable political reference IDs, world-character IDs and durable MariaDB
   repositories, migrations, transactional replay and rollback tests.
3. Actor/office power authorization and event-journal integration.
4. Authorized RESTful API version 1, stable JSON/error contracts, schema tests,
   robust idempotency and isolation; no client graphics.
5. Constitutional procedures, real and fictional political relationships,
   institutions, economy, diplomacy, defence and autonomous actors.
6. MariaDB concurrency, fault and API integration tests plus reproducible
   simulation runs; UI postponed until the user explicitly authorizes it.

This work must not imply that Aeternum V1.0 was certified for production.
