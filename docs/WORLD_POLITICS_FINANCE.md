# WP46 — annual finance bill and treasury pilot

Opt-in fr-finance-v1. All figures are fictional integer EUR minor units.
Two reviewed 2027 budget variants with tax revenue shifts and additional
daily appropriations for education, health and justice. Existing owner
domestic proposal selects a variant with effective date 2027-01-01;
certified internal finance evidence files and processes the same immutable
text. Article 47: forty-day Assembly first reading, special Senate
fifteen-day deadline after timeout transfer, seventy-day parliamentary
window. See https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241054/
and https://www.budget.gouv.fr/reperes/budget_etat/articles/preparation-lexamen-budget-letat .
Simplified ordinary and last-word paths require reviewed procedural facts,
not exhaustive LOLF, parliamentary amendments, admissibility or CMP.
Constitutional review is mandatory in this pilot, not a general legal
requirement. Publication before 2027 is required for this narrow annual
model. Seventy-day and continuity flags expose unresolved cases; no
automatic ordinance, special law, finance 49(3), shutdown or retroactive
budget is implemented. No inferred automatic government collapse.

Existing legislation clerk CLI certify accepts finance_file, finance_assembly,
finance_transfer, finance_senate, finance_final, finance_review, finance_publish
with outer bill_id null. apply-budget world-id bill-id verifies published
text, matching domestic proposal and effective date, and persists the
existing registry proof. Existing two-transaction recovery semantics.
GET /worlds/{world}/countries/FR/finance returns coherent institution and
domestic snapshots, catalog and independently computed treasury projection.
Projection starts with fictional debt 20 million EUR, 3% fixed annual rate,
daily rounded interest, and deficit-funded borrowing only if cash is
negative. Borrowing is not revenue; interest is not charged back into the
domestic ledger. No maturity schedule, auctions, credit spread, defaults,
debt repayment, GDP ratio, inflation or full national accounts yet.
Published budget deltas do enter the existing domestic ledger from the
effective date; appropriations are indicative catalog allocations rather
than enforceable ministry-level spending ceilings. Prior scenarios and
schema unchanged. Full budget editor, recurring years, fiscal incidence,
market financing, and article 47 special routes remain open.
