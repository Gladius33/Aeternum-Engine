# WP31 — global geopolitical references

The curated country file contains 193 UN members and both UN observer
states; the Holy See is represented using ISO VA. The separate Palestinian
Authority actor is retained from the prior dataset. These are game entities
and membership metadata, not assertions of territorial control.
Sources: https://www.un.org/en/about-us/member-states, https://www.un.org/en/about-us/non-member-states, https://unstats.un.org/unsd/methodology/m49/overview/.
Country labels are short editorial labels; ISO codes are mapped to membership,
not used to infer membership. Game eligibility does not imply playable
constitutional coverage; the alpha gateway still grants only France control.

New fr-global-v1 worlds start 2026-11-25 with 196 actors. Old fr-sandbox-v1
worlds retain the original five actors and start date. Default global worlds
have no economic observations; missing and absent are reported distinctly.
Existing saved states pin actor and economic reference hashes. An optional
operator-installed bundle SHA can be supplied at world creation. A bundle
is content-addressed and revalidated whenever loaded; no live mutable
"latest" data is used by a saved game.

## Import historical WDI data
Supported series: SP.POP.TOTL and NY.GDP.MKTP.CD. Fetch and archive all API
pages from the official provider, with format=json, historical date range
and explicit pagination. See
https://datahelpdesk.worldbank.org/knowledgebase/articles/898581-api-basic-call-structures
and https://www.worldbank.org/ext/en/legal/terms-conditions/datasets.
Review series-specific third-party rights first. Prepare a local manifest:
```json
{"as_of":"2026-11-24","series":{"SP.POP.TOTL":{"pages":[{"file":"population-page1.json","sha256":"<actual SHA-256>"}],"rights":{"license":"<reviewed license>","license_url":"<reviewed HTTPS URL>","reviewed_at":"2026-11-24","redistribution_approved":true}}}}
```
Use migration 020 then run WP_REFERENCE_IMPORT=YES php
games/world-politics/bin/import-global-references.php /path/to/manifest.json
with the dedicated database environment. Store the returned full bundle SHA
in the creation request. Archived pages are local operator inputs, not
player uploads. WDI API responses are not bundled with WP31; raw external
observations have not been imported in this delivery. No values are
fabricated. CI data are synthetic test fixtures only.

The economic model consumes the same EconomicReferenceRegistry used by
previous projections. No bilateral trade, current governments, budgets,
constitutional rules outside reviewed countries or calibrated national
demographic forecasts are added in this milestone.


## Corrective WP54r — published table bundle, not an empty default

New global/comparative and subsequent campaign worlds without a requested bundle now install and pin the immutable published table bundle. Existing worlds whose stored bundle SHA is null continue to read the old empty registry. Creation idempotency fingerprints the request's explicit bundle selector (null if omitted), not the server-selected default, preserving retries from before the migration. The `/references` endpoint exposes the actual pinned records, not just counts. API-based archives retain the original ingestion gate; the manually transcribed published tables are not represented as API responses.

Population: 194 of 195 UN member/observer states, WDI 2024 table vintage 15 December 2025. GDP: 55 strategic/European/alliance states, same table vintage. The remaining GDP entries are left absent rather than assigning a date to italicized historical values that the extraction does not distinguish. The table's precision, source geography and CC BY 4.0 dataset terms are preserved. Russia/Ukraine territorial caveats, Cyprus and Palestine scopes are not political recognition decisions. The World Bank API and original PDF screenshot retrieval were unavailable in this session; table text was used. The pack also adds four France-centred bilateral goods trade totals for 2025, with the ECB annual average conversion to USD and symmetric same-reporter lookup. These records are consumed by the existing authorized trade restriction projection (not automatic sanctions). The EU and NATO membership lists, BRICS reported membership and WTO/WHO source counts are exposed as historical reference; they do not silently grant real treaty authority or substitute for incomplete accession/withdrawal mechanics. Sources and scope are embedded in the pack.
