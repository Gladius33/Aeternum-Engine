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
