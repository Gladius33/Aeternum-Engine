# WP115 — staged international data acceptance (2026-11-27)

The complete v0.4 snapshot is immutable and available in `games/world-politics/data/wp115-collection-v0.4/` and its matching ZIP. Do not rewrite the previous archive or count its duplicates as additional observations.

## Reproducible checks
Run `python3 tests/wp115-staging.py` in a full checkout. This verifies SHA-256, ZIP membership, CRC, national coverage, source references, row types and keys, split of observed/forecast rows, nulls, and report totals. CI runs this separately from the simulation. It is a structural acceptance gate, not a factual second-source review, legal certification or runtime importer.

## Data editions and deployment boundary
Research snapshots are pinned to their retrieval vintage and separated from executable world rules. Source links, definitions, units and attribution are retained. Runtime promotion requires an explicit versioned integration with deterministic replay tests. The WP115 research archive is separate from the alpha runtime handoff.

## Still open
729 categories not collected or not validated, plus 195 partially documented; none of the 924 gap categories is fully closed. Full constitutional evidence, officeholders, party programmes and election results, tax/social law, creditor stocks, sector accounts, treaty obligations, and May 2027 scenario opening remain incomplete. Keep the real candidate admission and country capability gates closed for unsupported content. Later research editions must have new version IDs, provenance, units, dates, new manifests. No automated promotion of projections, estimates or unverified excerpts to observations. No new universe state or score change.
