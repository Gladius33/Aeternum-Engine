# WP115 — staged international data acceptance (2026-11-27)

The complete v0.4 snapshot is immutable and available in `games/world-politics/data/wp115-collection-v0.4/` and its matching ZIP. Do not rewrite the previous archive or count its duplicates as additional observations.

## Reproducible checks
Run `python3 tests/wp115-staging.py` in a full checkout. This verifies SHA-256, ZIP membership, CRC, national coverage, source references, row types and keys, split of observed/forecast rows, nulls, and report totals. CI runs this separately from the simulation. It is a structural acceptance gate, not a factual second-source review, legal certification or runtime importer.

## Data editions and deployment boundary
Research snapshots are pinned to their retrieval vintage and separated from executable world rules. Source links, definitions, units and attribution are retained. Runtime promotion requires an explicit versioned integration with deterministic replay tests. The WP115 research archive is separate from the alpha runtime handoff.

## WP115 WDI edition 2026-11-27 v1
The snapshot `games/world-politics/data/wp115-wdi-2026-11-27-v1/` contains 50 files, including all 48 exact source response and metadata files, normalized evidence and its manifest. Source run [45976171412](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45976171412); original archive SHA-256 `d270193afb6891c842de9696d0a949f79960b31f46374e54f6389405a2b8eb76`. The verifier reconciles every normalized value and null against the raw publisher response and independently checks the source vintage. 24 indicators, 42 countries, 3 years: 3024 slots, 2632 numeric values, 392 missing values. The cumulative 4301 numeric values count different source vintages, not unique indicators. The former gap inventory is an immutable v0.4 baseline, not a current completion percentage. No automatic world mutation.

Run:
```sh
python3 games/world-politics/bin/freeze-wp115-wdi.py --audit games/world-politics/data/wp115-wdi-2026-11-27-v1
```

## Additional initial references
- `wp115-institutional-reference-v1.json`: dated October 15 2026 officeholder reference and IDEA legislature electoral classifications for all 42 target countries; not a certification of May 2027 offices, election results or complete cabinet appointments.
- `wp115-wdi-economic-social-2026-11-27-v2/`: 28 additional WDI indicators, 3528 slots, 2112 numeric and 1416 missing. Raw responses and source metadata preserved. Cumulative 6413 numeric values across distinct sources and vintages.
- `InternationalReferenceCatalog` ingests the three numeric editions, institutional records, country working profiles and gap inventory read-only. Every consumed staged JSON is checked against its manifest; snapshot digest pins the editions. No changes to saved worlds or authority rules. `php games/world-politics/bin/wp115-reference.php` reports the content digest; append ISO2 for a dossier or ISO2 and metric for a series. Optional fourth argument pins the expected digest. Unknown countries and mismatched pins fail closed.

The initial engineering acceptance is deliberately distinct from exhaustive national research: the original 924 categories are still tracked as unresolved and are not made complete by ingestion or a successful CI. Data gaps are part of the catalogue.

## Initial documentary milestone — final acceptance candidate
IPU public parliamentary results edition v2 covers the lower or single chambers of 42 countries, with 126 distinct election references (up to three per country), 114 published party-seat tables across 40 countries and 1077 seat entries. Source run [45979800368](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45979800368). The earlier collection v1 is retained as a superseded source capture and not counted. The supplementary South African government reference provides the 18 groups and all 400 seats from 29 May 2024. Latest results without tables are explicitly unavailable; previous elections never fill the latest result. Party-seat facts are not current group membership, votes or a legal seat allocator.

The catalogue initial v2 pins every edition and validates the election identifiers, date, source host, party names and reported sums. The CLI supports `ISO2 --coverage` (22 domains with evidence pointers, including actual numeric metric availability) and `ISO2 --opening` (latest non-null historical references by source and distinct annual 2027 forecasts). The opening export deliberately has no actual future opening state. Its contract is in `data/wp115-opening-reference.schema.json`. Coverage flags are not a claim of complete legislation. The initial technical acceptance criterion in the career roadmap is collection, schemas, source traceability, versioning and ingestion with validation; complete political programme registries, career/institutional rules and calibrated models remain the work of WP117–122. No simulated result or future fact is invented. Final general CI must pass on the exact acceptance commit before qualifying this milestone.

## Still open for subsequent research
The immutable v0.4 baseline listed 729 uncollected or unvalidated categories and 195 partially documented. The live `--coverage` report supersedes those counts for documentary availability; all 924 domain requirements remain tracked and no full legal or empirical completion is asserted. Full constitutional evidence, officeholders, party programmes and election results, tax/social law, creditor stocks, sector accounts, treaty obligations, and May 2027 scenario opening remain incomplete. Keep the real candidate admission and country capability gates closed for unsupported content. Later research editions must have new version IDs, provenance, units, dates, new manifests. No automated promotion of projections, estimates or unverified excerpts to observations. No new universe state or score change.
