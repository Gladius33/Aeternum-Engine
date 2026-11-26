# WP53c — functional and causal audit (26 Nov 2026)

Baseline commit 58f7f4506bc62c41d6ea123b8dfa2679cc47dfd3. WP53b CI 45788792377
succeeded. Inventory: 80 PHP source files; static architecture and key path review,
not a line-by-line security audit or a calibrated economic forecast.

## Verified code paths and limitations

| Area | Existing chain | Blocking gap |
|---|---|---|
| Identity/accounts | AlphaAccounts, PoliticalRoster, AlphaWorlds, AlphaGateway | Player authority is still scenario/role based, not complete office authority |
| Clock | WorldMutationFence, WorldClockCoordinator, Pdo stores | Durable daily saga, not distributed atomic transactions; replay cost grows with history |
| Constitution | ConstitutionalRevision, BicameralLegislation, LegislativeResponsibility, FrenchParliament, FrenchSittingCalendar | Several actions require certified clerk fixtures; player programmes cannot automatically create legally effective bills |
| Cabinet | FrenchCabinet, ExecutiveActs | Narrow catalogs, certified succession, no full autonomous ministerial policies |
| Finance | FinanceBudget, DomesticEnactmentRegistry, DomesticPolicySimulation | Limited annual budget variants and enactment proof, no full recurring budget cycle |
| Macro | MacroFiscalLoop in FrenchMacroBaseline.php | Fiscal/debt loop real; exports/imports fixed baseline, wage target follows prices only |
| Debt | SovereignDebtLedger, FinancialCrisis | Accounting and financing limits, synthetic creditor cohorts, no firm balance sheets or actual creditor inventory |
| Population/services | FrenchDemography, FrenchPublicServices | Demographic costs and lagged pressure wired; staffing, capacity investment and regional detail lacking |
| Corporations | ProfessionalCorporations -> DomesticPolicySimulation -> MacroFiscalLoop -> PublicOpinion | Opposition raises worker pressure, resistance lowers investment; wage decisions only change branch index; no independent firm actors, benefits/quality conditional on policy design absent |
| Social | PublicOpinion, CivicDialogue, SocialPartners, FrenchSocialDynamics | Pressure and trust evolve; no full protest participation or politically distinct mobilisation; separate models must not double-count |
| Electoral and opposition | ElectoralCycle, PoliticalNegotiator, PresidentialMandate, CampaignLifecycle | Coalition proposals and some roll calls, but preset presidential ballots and curated terminal events; no autonomous reaction to each pack |
| Trade and diplomacy | WorldEconomicSimulation, DiplomaticSimulation, DiplomacyEconomicBridge, OrganizationSimulation | Trade restrictions produce projections, not national output changes; limited NPC profiles and organisations; global scenario charters empty |
| EU and currency | FR_EU_EURO_EXIT and EU_EURO_SPILLOVERS studies | No implemented Article 50 withdrawal, effective tariff regime, or currency conversion |
| Performance | Full-state JSON and deterministic restore | Repeated replay and serialization on reads and writes; benchmark before claiming responsiveness |

## Programme packs and firm identities

Two executable corporation actions (parallel, exclusive contested) are exposed
through a read-only catalogue alongside unavailable industrial, EU and monetary
packs. The UI must never offer unavailable actions as if executable.
One click submits the available decision via the existing ETag and receipt
protocol. A future pack is a durable DAG of proposals, authorizations,
dependencies, expenditures, delays, rejections and partial completion, not
an atomic override of law. It must support individual measures in expert mode.

The 40 CAC40 identities are dated 25 Nov 2026. No observed relocation intent
is claimed. Individual company review requests can be journaled; new sector-wide
nationalisation requests are denied. No transfer, cost, legal reach or valuation
is invented. Source identity is not ownership, domicile or asset location.
Actual company balance sheets, domestic employment/value added, ownership,
liabilities, compensation, legal scope and investment responses remain required.
Consolidated corporate group and domestic subsidiary distinct. Data distribution
rights must be reviewed before commercial use.

## Required causal acceptance gates before WP80

* Three counterfactual worlds same initial data and exogenous shocks; compare
  control, parallel corporations and exclusive contested; trace pressure,
  production/investment, approval and treasury identities, not mere flags.
* Wage vote -> actual sector payroll, employer cost, household income,
  contributions, consumption, inflation, employment, budget; no double counting.
  Include higher wages with productivity, excessive wage burden, deadlocked
  bargaining, gradual transition, and social recovery. No predetermined political
  success/failure.
* Firm agents: fictional in-world relocation proposals, staying, negotiation,
  investment, relocation implementation with time/costs; no real-world claims.
  Public ownership via independently authorized transaction/legislation,
  compensated equity acquisition and financing, contested cases, no automatic
  control of foreign assets, avoid duplicate domestic GDP.
* Industrial capital formation: investment projects, construction lead times,
  energy, skills, demand, capacity utilisation, depreciation, output and
  imports. Jobs and value added appear only when production becomes operational.
* EU policy pack: model EU-compatible industrial policy separately from
  contested unilateral tariffs and negotiated withdrawal. Articles 3, 28–30,
  207 TFEU and Article 50 TEU. Independent EU response and legal calendar;
  no automatic departure, sanction cancellation or euro exit.
  Source: https://eur-lex.europa.eu/eli/treaty/tfeu_2012/oj?locale=fr
  and https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=celex%3A12012M050
* Tariff revenues collected once on taxable imports; domestic prices, imported
  intermediate goods, substitution constraints, foreign response and exports,
  employment, GDP and fiscal accounts must reconcile. Distinguish customs
  union, membership, single market and currency.
* Opposition AI: group programmes, coalition incentives, votes and objections
  independently from public opinion; dynamic elections and constitutional
  continuity; political conflict not automatically equivalent to regime collapse.
* Performance acceptance: profile 1/30/365/1825-day saves and concurrent
  worlds; bounded pagination, incremental snapshots and verified checkpoints,
  measured p50/p95 latency and DB/storage growth. UI smoothness cannot conceal
  backend timeouts.
* Nationalisation legal reference: https://qpc360.conseil-constitutionnel.fr/commentaire-decision-2021-915916-qpc
  (compensation and property protections). Reforms may be legally contested,
  rather than falsely represented as ordinary lawful executive action.

## Status
WP53c adds a causal regression covering existing linked paths, not calibration
of coefficients or proof of all above criteria. CI confirmation required.


## WP54r remediation checkpoint

New corporation/civil-crisis worlds pin wage feedback v1 and aggregate fictional
presidential ballot model. Approved wages change weighted wage target, realised
payroll proxy, split tax base, household consumption via real wages, labour cost,
investment and employment. No claim of calibrated payroll or observed branch weights.
Presidential eligible incumbent ballots react to previous committed aggregate
approval; no real candidates or polling. Other cycles retain fixtures. Legacy
worlds retain original hash and replay. Counterfactual and replay tests added.
Remaining acceptance gates above stay OPEN, notably trade, company ownership,
industrial capital, constitutional authorization, EU/currency and performance.

### WP54r trade correction

New corporation/civil-crisis worlds pin trade feedback. Clock obtains a committed,
verified economic source and stores a compact hash-bound observation in domestic
history. Effective restrictions now alter simulated exports/imports, consumption,
investment, employment, prices and lagged fiscal flows. Overlapping same-partner
restrictions use max; missing bilateral references flagged, never filled with
invented USD/EUR conversions. Symmetric, damped exposure is an explicit uncalibrated
proxy, not a directional trade model. Alpha legal authorization and player tariff
policy remain unavailable. Old save behaviour unchanged. CI required.
