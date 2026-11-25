# WP49 — sovereign nominal debt ledger

New opt-in fr-debt-v1. The 41 debt reference entries (35 observations and
6 explicitly marked forecasts) are pinned in per-world MariaDB assumptions,
alongside WP48's 40 French observations. No SQL migration. France only
playable, legacy scenarios unchanged. Opening Maastricht gross debt anchored
at Q2 2026, not the October negotiable State debt. Market-value holder
shares used ONLY as synthetic nominal allocation weights for the State
contribution; residual other government ownership is unknown. All claims
are artificial aggregate categories, not observed named positions.
Opening stock divided into 120 fictional monthly maturity cohorts with
exact cent remainder allocation. No actual ISIN schedule yet. New debt
has assumed ten-year maturity outside the 3650-day pilot horizon; fixed
normal/stress rate buckets preserve issue-rate differences. Initial 2%
rate assumption; normal new coupon anchored to dated AFT issuance rate,
not a forecast. Simplified daily interest payment, not actual coupon dates.
Debt stock is nominal principal, separate from unpaid interest and primary
outlays. No indexation, currency conversion, secondary-market value, or
writeoffs yet.

Daily finite funding cap = base + fraction of today's scheduled maturity,
with lower cap and higher new coupon after prior-day arrears. Fictional
parameters, not historical borrowing capacity. Fixed illustrative payment
ordering primary, interest, matured principal; pro-rata principal payments
across aggregate overdue claims with exact integer math. Unpaid principal
remains outstanding; no automatic capitalisation or extinguishment.
Unpaid primary spending and interest persist separately and are not
included in gross nominal principal. Treasury cash reconciles with actual
paid flows, not gross fiscal accruals. No assumed unlimited refinancing.
No player-controlled default or allied financing in WP49; next steps.
No actual bank balance sheet or legal ownership-transfer mechanic yet.

GET /worlds/{world}/countries/FR/debt returns creditor totals, dated source
pack, daily/monthly/yearly stock-flow journal and funding gaps. Partial
calendar periods are explicitly dated. Finance and macro use same verified
daily treasury state. No writes on GET. Tests check reconciliation,
maturity, finite financing, arrears, stress and tamper rejection.
