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

### WP54r firm-agent correction

New corporation/civil-crisis worlds pin 40 independent fictional firm profiles.
Previous committed demand, credit, trade and labour-cost proxies drive sustained
stress or relief. Projects take 90–180 days, may be cancelled and change installed
capacity only on completion. Only capacity differences feed macro investment
and employment; baseline firm output is not added again. API exposes coherent
scenario state, not real firm intentions, forecasts or balance sheets. Historical
worlds keep their original model. Company acquisition, compensation, real data,
sector capital budgeting and calibrated firm weights remain open. Full snapshots
increase save size; performance gate remains open.

### WP54r public equity correction

Optional new-world ledger for a voluntary purchase of a fictional 51% domestic
stake, using a clearly synthetic quote. An offer, independent fictional consent,
30-day delay and available cash after existing obligations precede settlement.
No forced transfer, invented actual market prices or automatic borrowing. The
settlement exchanges cash for a financial asset at historical cost; does not
increase primary spending or GDP. Compulsory nationalisation remains a review
request, with statutory authorization, actual valuation, financing and legal
scope still open. Ownership does not yet affect dividends, management or output.

### WP54r industrial capital pilot

Equity correction CI 45792401529 green. New opt-in pilot implements three
fictional projects with bounded daily cash spending only after ordinary
obligations, funded construction, delayed commissioning, depreciation and lagged
capacity/employment feedback. Capex is primary expenditure and gross capital
formation, not government consumption; illustrative imported input share 30%.
Existing service funding is protected by a separate ordinary settlement. This
is not the full reindustrialisation pack, statutory budget authority or a
finding of EU state-aid compliance. All coefficients synthetic. CI pending.

### WP54r party response correction

New corporation/civil-crisis opinion worlds pin independent group positions
against committed corporate and industrial policies, services and output. Uses
existing fictional programme axes, seat totals only from final game results,
no fabricated seats. Positions change gradually and have no legal effect;
legislative enactment and certified roll calls remain separate. No calibrated
party behaviour or automatic change of regime. Further coalition, actual
legislative action and electoral dynamics remain open. CI pending.

### WP54r diplomatic replies correction

Party response CI 45793372187 green. New corporation/civil-crisis diplomatic
states pin a bounded daily autonomous foreign reply model. Incoming fictional
French dialogue, aid, trade and treaty negotiation offers receive nonbinding
responses after two to five days, with synthetic preferences and prior interaction
history. Only the trusted clock invokes it, no foreign player impersonation and
no retroactive sanctions authorization or treaty ratification. Old states remain
unchanged. No claim of real foreign policy preferences. Independent international
initiative, EU legal decisions, sanctions approval and economic implementation
remain open. CI pending.

### WP54r diplomatic-economic bridge correction

New corporation/civil-crisis economies pin an internal daily outbox bridge.
Checks committed diplomatic day, registry, snapshot and source journal, then
writes a synthetic bounded economic proposal and acknowledges the source in
the same economic clock transaction. No authorization, no tariff, no economic
impact merely from a diplomatic declaration. Up to 32 pending rows per day;
unsupported participants require review. The statutory legal gate, differentiated
trade exposure, and EU powers remain open. CI pending.

### WP54r daily legal activation

Diplomatic/economic bridge CI 45798259162 green. New worlds pin automatic
reconciliation of previously certified published standard bills, exact
regulatory decrees and finance laws with matching pending domestic proposals.
Uses verified institutional snapshot, exact effective date, unambiguous text
and existing final-act ledger. No client authority, no auto certification or
retroactive effect; transaction commits alongside daily domestic ledger.
Amended texts without fiscal mapping, missing proposal and ambiguous instruments
remain inactive. Manual clerk certification and full pack legislative workflows
remain open. CI pending.
