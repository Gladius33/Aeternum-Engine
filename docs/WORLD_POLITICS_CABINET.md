# WP44 — fictional French cabinet and cohabitation

Opt-in fr-cabinet-v1 builds on WP43. Articles 8, 20, 21, 23, 39, 49 and 50
provide the legal basis (https://qpc360.conseil-constitutionnel.fr/constitution-4-octobre-1958).
A cabinet has a named fictional prime minister, seven portfolio slots,
generation, resignation and appointment dates and archived ministers.
The president's office is separate. A legislative election does not
automatically dismiss the government or end the presidential mandate.
Cohabitation is a simplified affiliation comparison; declared coalition
support is derived from current group seats, not a binding confidence vote.
The independent affiliation is not automatically classified as cohabitation.
Actual confidence continues through the WP42 individual ballot engine;
the cabinet affiliation does not rewrite those ballot coefficients.

Trusted clerk events use the existing legislation CLI. bill_id must be
null. assembly_cabinet_resign requires the incumbent prime minister and
certified presentation of resignation, not unilateral presidential removal.
assembly_cabinet_appoint requires caretaker status, a certified
presidential appointment and cleared incompatibilities. The prior cabinet
is archived and portfolios start vacant. assembly_cabinet_portfolio
requires the incumbent PM's proposal, a certified presidential act,
and a reviewed fictional eligible person or null for termination.
Duplicate portfolio assignments and occupied deputy IDs are rejected.
Actual eligibility is certified; the catalog contains fictional nondeputies.
No mandatory parliamentary investiture is invented. Censure marks the
cabinet resignation_required; presentation and appointment are separate.
The pilot serializes cabinet transitions with pending proceedings.
The file, CMP, last-word, 49(3) and confidence opening evidence must
identify the active prime minister through prime_minister_id. Legacy
appointment shortcuts are disabled in this scenario. No public certification
route. GET /worlds/{world}/countries/FR/cabinet exposes cabinet and support
projection. Competence metadata is illustrative, not a full execution
engine for ministerial actions. The original political roster is not
mutated. Cabinet records use existing institutional persistence and replay.
No new SQL migration. Remaining: playable nominations through authorized
player requests, automatic negotiation, full portfolios, changing electoral
cycles, delegated acts/countersignature, actual cabinet influence on roll
calls and integration with all country-specific executive functions.
