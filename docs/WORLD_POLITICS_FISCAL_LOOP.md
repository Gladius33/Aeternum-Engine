# WP48 — France observed anchors and daily fiscal feedback

Opt-in fr-fiscal-loop-v1, only France playable; same prior institutional
modules, foreign institutional participants remain non-player. Existing
32-observation staging pack is validated (source, units, period, identity,
scope, duplicate guards), embedded in immutable per-world assumptions
in wp_domestic_states and exposed with the source hash. No SQL migration.
Legacy scenario hashes and rows unchanged. This is an observed-anchored
fictional economy, not the actual 2026 French public accounts: annual
flows use 2025 nominal observations divided into daily minor units;
GDP residual is explicit. Debt uses T2 2026, unemployment T3 2026,
inflation Oct 2026, cash EUR 10bn and effective annual interest 2%
are assumptions. Source dates differ intentionally. Aggregate general
government flows are not solely the central state's budget. No annual
GDP ratio mixing different dates. No newly verified source collection
in this commit; uses the existing staged pack. Other packs still staged.

Lagged nominal GDP changes tax receipts, unemployment and price levels
modify social outlays, real wages influence consumption, activity affects
investment, inflation and unemployment adjust gradually. Statutory policy
deltas remain law-authorized and separately identified. Day N uses day
N-1 fiscal feedback. Daily cash is reconciled with primary flows, interest
and borrowing (not tax revenue). Debt grows by borrowing, no repayment/
maturity model yet. Finance projection consumes the same authoritative
treasury rows, no duplicate interest. Opinion receives derived production/
employment indices via existing daily source. Read-only macro API uses the
persisted domestic ledger instead of the WP47 projection for this scenario.

Coefficients are illustrative and uncalibrated. No external trade feedback
in WP48 (WP47 legacy read projection retained); later monetary and sector
iterations must integrate trade, energy and external currency exposures.
Existing policy templates retain small scenario amounts, not national
policy calibration. Domestic revenue and expense remain primary fiscal
flows; cash includes financing and interest. Not a full accrual ledger or
national accounts forecast. No automatic collapse or political outcome.
Replay verifies every day. Tests cover observed units, exact accounting,
lagged effects, debt/cash, tamper detection and authenticated HTTP.
