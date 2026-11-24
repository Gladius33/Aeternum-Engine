# World Politics — HEADLESS BACKEND & REST API CONTRACT (24 November 2026)

## Binding scope (NO UI)

**Only backend development is authorized for now.** Aeternum Engine is a
theme-neutral engine whose frontend templates are actively changing. World
Politics must not depend on, import, style or modify its existing client or
templates. No Vue, HTML, image asset, CSS, screenshot, or browser E2E work for
World Politics until the user approves integration with the eventual generic
template system.

World Politics remains in an **isolated game branch**; **NEVER MERGE INTO
AETERNUM MAIN**. The game will move to its own repository. The reusable engine
will later be an explicitly pinned dependency or integration boundary.

## Client-independent architecture

Web, Android/iOS and possible desktop clients will all use the **same**
versioned RESTful service, with HTTPS + JSON and independent authentication.

- Domain: nation, identity, government, parliament, constitution, policies,
  public finances, population, defence, diplomacy, international
  organizations, religion, civil society and strategic political events.
- Reference datasets: immutable versioned observations with source URL,
  publication date, observation date, unit, licenses, coverage and quality;
  never silently mutate ongoing campaigns.
- Simulation: signed-off ruleset version, world-scoped actor commands, pending
  events, deterministic order, idempotency and replayable receipts.
- AI: autonomous actor plans use the same server-authorized command boundary
  and world rules as humans, never privileged SQL state mutation.
- Transport: REST resource representations (JSON), HTTP method/status
  semantics, pagination, schema/version compatibility, error contract and
  idempotency. An optional event feed can be added later without introducing
  gameplay logic into clients.
- Persistence: transactional InnoDB world state, journal and outbox, plus
  read projections and source registry. *Not yet implemented for this game*.
- Security: server-authenticated principals, per-world/country/office policy,
  no client-declared role authority, rate limits, input bounds and audit.
  Mobile/public browser tokens must not silently gain administrative rights.

## Current WP01 prototype: in-memory contract adapter ONLY

The isolated classes:
- \`games/world-politics/src/PoliticalRoster.php\` (pure domain),
- \`games/world-politics/src/WorldPoliticsApi.php\` (transport-neutral REST contract).

REST-shaped endpoints defined in the prototype:

| Method | Endpoint | Purpose | Privilege |
| --- | --- | --- | --- |
| GET | /api/v1/worlds/{world}/countries/{ISO2}/politics | Read a national roster and cabinet snapshot | read |
| GET | /api/v1/worlds/{world}/countries/{ISO2}/politics/people/{id} | Read one political person | read |
| POST | /api/v1/worlds/{world}/countries/{ISO2}/politics/people | Register **fictional** person only | create_fictional_person |
| POST | /api/v1/worlds/{world}/countries/{ISO2}/politics/head-of-state | Initialize an alternate presidency | initialize_scenario |
| POST | /api/v1/worlds/{world}/countries/{ISO2}/politics/nominations | Propose an appointment | nominate |
| POST | /api/v1/worlds/{world}/countries/{ISO2}/politics/nominations/{office}/resolution | Resolve a proposal | resolve_nomination |

The endpoint method is intended to be called by a future trusted HTTP
adapter; **it is not a public route or running HTTP service**. The
\`PoliticsAuthorizer\` interface is supplied by trusted server-side session
admission. Each POST requires \`Idempotency-Key\` and matching \`If-Match\`
from the last GET/POST ETag. This prototype uses *process-local*
idempotency receipts and in-memory state, NOT crash-safe durable receipts.

Unapproved real-world profiles cannot be injected by a public client.
Approved real-world datasets must use a separate trusted curation pipeline.

Errors use JSON problem responses with stable codes. A read-denied world is
concealed as 404, while a forbidden mutation returns 403. Commands must be
re-authorized inside the eventual durable transaction, not merely by the
transport adapter.

## Important limitations and acceptance blockers

- No persisted state, official Aeternum command journal or worker.
- No live HTTP server, no token parsing/auth provider, no rate-limits.
- No actual country-specific nomination-law enforcement.
- No real-world personality import or macroeconomic simulation.
- No production authorization proof, backup/restore or concurrency tests.
- No frontend, no mobile client, no generic engine UI adapter.

**Do not deploy this prototype** or claim that its API is production-ready.

## Next backend-only milestones

1. Transactional SQL repositories, world/country/actor ownership, persistent
   scoped idempotency receipts and append-only event/audit journal.
2. Trusted auth session bridge and write admission under InnoDB locks,
   verified cross-world isolation, rate limits and safe error handling.
3. Source registries & importers with immutable snapshots and provenance.
4. Country-specific constitutional action policies and government nomination
   workflow, conflict/rejection and deterministic time.
5. Government/political-party AI that submits ordinary authorized commands.
6. Full MariaDB and REST integration tests, property/concurrency/fault tests,
   CI proofs, OpenAPI 3.1 schemas and version-compatibility fixtures.
7. Client-facing graphical integration **only with explicit future approval**.
