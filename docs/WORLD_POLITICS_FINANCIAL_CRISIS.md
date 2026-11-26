# WP50 — fictional financial crisis pilot

Opt-in fr-financial-crisis-v1. Same WP49 reference packs and baseline.
No public laws are enacted by the new owner-only fictional decisions;
the route is deliberately a scenario control, not constitutional approval.
POST /worlds/{world}/domestic/crisis with strong ETag and idempotency
key, action moratorium/resume/request-support/request-restructuring.
One decision per simulated day, effects next day; max 128 decisions,
one support and one restructuring request per pilot. Decisions are
journaled with domestic receipts, fenced against concurrent ticks,
and replayed chronologically. Migration 023 expands the journal enum.
Existing worlds and WP49 debt rules remain unchanged.

Moratorium suspends principal and interest payments and market borrowing.
Unpaid amounts remain due, not erased. A fictional creditor committee
may accept a 20% reduction of PREVIOUSLY overdue principal only after
resumption; request without arrears declines. Future scheduled maturities
unchanged. A fictional partner can decline during a moratorium or with
interest arrears, or supply a one-day loan up to EUR 3bn for uncovered
eligible need, at an assumed coupon; cash only when actually drawn,
distinct creditor liability and no promise of real-world aid.
No real partner named, actual alliance-dependent negotiations remain open.
All rates and rules hypothetical. An aggregate banking equity/valuation
proxy reacts to unpaid sovereign obligations; lower credit availability
feeds next-day production and employment. Not a full bank balance sheet,
no insolvency resolution, deposits, FX, central bank liquidity, or actual
monetary policy yet. Mark-to-market proxy not booked as debt extinguishment.
No nationalisation or ownership seizure action yet; requires the distinct
legal/asset model. No real-world asset targeting. No automatic game over.
Unit/HTTP tests cover scope, decisions, arrears, support, restructuring,
stock/flow, tamper resistance, replay and legacy isolation.
