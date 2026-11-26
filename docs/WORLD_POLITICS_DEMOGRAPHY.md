# WP51 — French demographic cohort dynamics

Opt-in fr-population-v1 retains WP50 crisis, institutions and economy.
France only playable. 51 observed entries from Insee January 2026 vintage:
42 age/sex counts (14 age groups, female/male/total), 3 population totals
and 6 annual demographic indicators. These are provisional observations,
not a November population estimate or current revised vintage. Raw rounded
counts retained; normalize each sex to published total by deterministic
cumulative allocation, 28 nonoverlapping simulated cohorts sum to
69,082,000. Age bands 0–14, then five-year bands to 74, 75+. Source pack
pinned in per-world MariaDB assumptions; no schema migration.

Daily births, deaths and net migration with integer carry; population
conservation, simultaneous adjacent age transitions (uniform age-band
approximation), no aging out of 75+; birth rate scales with female 15–49
stock, death volume with total stock, mortality allocation uses fictional
age weights. Migration baseline +176,000 is net only, not gross flows.
Prior-day unemployment modifies net migration by a bounded scenario
coefficient. No geographic/nationality attribution or real causal fit.
Statistical adjustment is not added to simulated physical flows.
Fixed 365-day model year, calendar aggregates include partial periods.
Fertility observation not a causal forecast. Death allocation, sex split,
migration and age transition assumptions are uncalibrated. No age-specific
observed mortality rates, gross migration, region, household composition
or fertility policies yet.

Lagged working-age cohort change influences macro production proxy within
+/-500 bps; not measured labor participation or employment. GDP per capita
daily, dependency and broad education-age proxy exposed, no double-counted
fiscal costs. WP52 should integrate labor force, schools, health, pensions
and actual sector budgets; do not treat age 65 as actual pension status.
GET /worlds/{world}/countries/FR/population authenticated coherent
read with source hash, daily/monthly/yearly aggregates and ETag. Legacy
scenario hashes unchanged. Replay verifies every population transition.
400-day deterministic unit run plus MariaDB HTTP acceptance.
