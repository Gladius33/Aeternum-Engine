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

## Still open
729 categories not collected or not validated, plus 195 partially documented; none of the 924 gap categories is fully closed. Full constitutional evidence, officeholders, party programmes and election results, tax/social law, creditor stocks, sector accounts, treaty obligations, and May 2027 scenario opening remain incomplete. Keep the real candidate admission and country capability gates closed for unsupported content. Later research editions must have new version IDs, provenance, units, dates, new manifests. No automated promotion of projections, estimates or unverified excerpts to observations. No new universe state or score change.
