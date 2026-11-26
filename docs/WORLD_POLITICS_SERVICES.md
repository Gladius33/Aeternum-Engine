# WP52 — Public services, health, education and retirement

Opt-in fr-services-v1, France only playable, retains WP51 population and
WP50 crisis. 33 dated DREES/DEPP/Insee observations pinned in per-world
MariaDB assumptions and validated during replay. Mixed vintages explicit:
health expenditure 2025, hospitals 2024 edition 2026, education expenditure
2025, school personnel/pupils rentrée 2026, pensions/contributors 2023
edition 2025. Not a single-year government account. Published health
DCSi 353.2bn EUR, 77.4% public financing; DIE 199.2bn, public shares
55+23+4=82% (rounded); pension expenditure 370bn in 2023, 17.2m
direct-right recipients (including abroad), 30.4m contributors/emploi
intérieur. Distinct overlapping scope: do not sum these to claim actual
public spending. Pensions used as an assumed public-fiscal proxy;
source-specific national-account crosswalk still needed.

Physical hospital anchors 367,100 full beds, 91,000 partial places,
25,400 HAD, 1.41m employees. Full-bed baseline occupancy assumed 85%;
older population relative demand weight 3. Fixed physical capacity,
operational capacity adapts over 180-day proxy to aggregate current
primary payment rate (floor 50%); no actual staffed-bed forecast.
School capacity assumed 105% of 11.6m pupils, all personnel 1.2m not
teacher FTE. Broad under-20 ratio drives pupils proxy, not exact
school enrollment or higher education. Pension recipients scale with
65+ population; neither equal to age 65+ nor individual entitlement.
Contributors scale with working-age and prior unemployment; no separate
cotisation tariff or pension financing balance yet. No regional variation,
retirement age legislation, medical throughput microdata or recruitment
mechanism yet.

Only demographic volume changes relative to initial service cost proxies
are added to already-inclusive WP48 primary expenses. No duplicated
baseline or extra inflation index. Pension transfer increment excluded
from government consumption term; no direct pension-consumption response
until paid-flow calibration. Aggregate primary payment allocation assumed
proportional after older arrears, not actual earmarked payments. No new
sovereign liability added from unpaid-pension proxy (already reflected
within aggregate primary arrears). Persistent shortage feeds prior-day
cohort pressure, not automatic political outcome. Unserved beds are
daily bed-demand equivalents, not identifiable patients; school gaps
are stocks, not sum of unique children. Daily/monthly/yearly incremental
expenses, bed-day equivalents and unpaid pension proxy; no sum of pupil
stock. Read-only GET /worlds/{world}/countries/FR/services coherent
with clock, authenticated world ownership/membership, ETag. Legacy
hashes unchanged. Tests run 400 days and stress fiscal shortages, verify
replay and API persistence. No source redistribution licence certification
claimed; sources are attributed and observed facts separated from assumptions.
