# WP47 — fictional macroeconomic projection

Opt-in fr-macro-v1, inherits the full finance, institutional, executive
and domestic scenario. GET /worlds/{world}/countries/FR/macro returns
daily projection and source hashes, strong ETag, requires world membership.
Reads the three existing replay/checksum-verified stores, checks committed
world day and rejects an advancing world. No new SQL migration. No public
macro mutation or client-supplied coefficients.

Real GDP expenditure identity C + I + G + X - M, nominal GDP deflated
against a fictional accumulated price index (sub-bps internal precision),
daily real growth, annual inflation assumption, unemployment adjustment,
real output index, tax revenue proxy and debt to annualized nominal GDP.
Domestic actually authorized fiscal flows drive the deterministic daily
consumption and public consumption assumptions, while production and
employment indices affect private activity. A reviewed effective bilateral
trade restriction involving state_fr contributes a bounded and damped
exposure proxy, only from its effective day and only with a nonmissing
reference. No assumption of USD/EUR parity or fabricated missing values.
A known reference still does not establish actual national trade loss.
All coefficients are fixed, fictional and not calibrated. Treasury cash,
debt, interest and borrowing use the existing WP46 projection exactly.

The projection is derived on read, not a second authoritative ledger.
No automatic feedback to actual tax receipts, spending, opinion, labour
force or monetary decisions. Baseline daily GDP is fictional, not French
national statistics. Tax revenue proxy and annualized output are not
real forecasts, and do not change the recorded historical reference data.
Investment and government consumption are simplified flow proxies; no
capital stock, inventories, GDP income-side identity, input-output table,
monetary policy, endogenous credit markets, demographics or full balance
of payments yet. Tests check identity, cumulative flows, time causality,
missing data, deterministic output, fiscal changes and debt reconciliation.
