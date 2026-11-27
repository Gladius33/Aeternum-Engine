# WP115 — staged international data acceptance (2026-11-27)

The complete v0.4 snapshot is immutable and available in `games/world-politics/data/wp115-collection-v0.4/` and its matching ZIP. Do not rewrite the previous archive or count its duplicates as additional observations.

## Reproducible checks
Run `python3 tests/wp115-staging.py` in a full checkout. This verifies SHA-256, ZIP membership, CRC, national coverage, source references, row types and keys, split of observed/forecast rows, nulls, and report totals. CI runs this separately from the simulation. It is a structural acceptance gate, not a factual second-source review, legal certification or runtime importer.

## Commercial rights boundary
Reviewed general terms on 2026-11-27:
- World Bank WDI: https://www.worldbank.org/ext/en/legal/terms-conditions/datasets — CC BY 4.0 by default, with additional attribution, third-party exceptions and indicator-specific conditions. Keep existing WDI records, check dataset-level metadata and give required source attribution before any new admission.
- Eurostat: https://ec.europa.eu/eurostat/help/copyright-notice — reuse generally allowed with attribution, third-party exceptions apply. Check the exact release and country metadata.
- IMF: https://www.imf.org/en/about/copyright-and-terms — commercial reuse requires contacting copyright@imf.org; no permission has been obtained for this project.
- OECD: https://www.oecd.org/en/about/terms-conditions.html — general terms and release-specific notices must be checked before commercial admission.
- SIPRI, IPU, UNDP, Tax Foundation, PwC and all legal texts/translations: exact-source rights still unresolved.

The staging dataset must not be included in an alpha source handoff or production package while rights are unresolved. Exclusion does not itself authorize existing repository publication; if the branch is public, review access and redistribution with the maintainer. Do not retroactively remove history without an explicit decision. No source is deemed commercially approved by this document.

## Still open
729 categories not collected or not validated, plus 195 partially documented; none of the 924 gap categories is fully closed. Full constitutional evidence, officeholders, party programmes and election results, tax/social law, creditor stocks, sector accounts, treaty obligations, and May 2027 scenario opening remain incomplete. Keep the real candidate admission and country capability gates closed for unsupported content. Later research editions must have new version IDs, provenance, units, dates, rights decisions and new manifests. No automated promotion of projections, estimates or unverified excerpts to observations. No new universe state or score change.
