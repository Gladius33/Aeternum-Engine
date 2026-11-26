<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\GlobalReferenceBundle;
require_once __DIR__.'/../games/world-politics/src/GlobalReferenceBundle.php';
function ck31(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('WP31 FAIL: '.$label);echo "PASS WP31: $label\n";}
$b=new GlobalReferenceBundle();
ck31($b->countries->unMemberCount()===193 && count($b->countries->all())===195 && count($b->actors->all())===196,'UN membership and separate authority');
foreach(['IL','IR','PS','VA'] as $c) ck31(!$b->actors->isHumanPlayable('state_'.strtolower($c)),'protected actor '.$c);
ck31($b->coverage()['population_total']['absent']===195,'missing observations never become zeros');
$rights=['license'=>'CI fixture only','license_url'=>'https://www.worldbank.org/ext/en/legal/terms-conditions/datasets','reviewed_at'=>'2026-11-24','redistribution_approved'=>true];
$page=json_encode([['page'=>1,'pages'=>1,'total'=>2],[
    ['indicator'=>['id'=>'SP.POP.TOTL'],'country'=>['id'=>'FR'],'date'=>'2024','value'=>123],
    ['indicator'=>['id'=>'SP.POP.TOTL'],'country'=>['id'=>'DE'],'date'=>'2024','value'=>null]
]],JSON_THROW_ON_ERROR);
$pack=GlobalReferenceBundle::import(['SP.POP.TOTL'=>['pages'=>[$page],'rights'=>$rights]],'2026-11-24');
ck31($pack->coverage()['population_total']===['observed'=>1,'missing'=>1,'absent'=>193],'archive coverage distinguishes missing and absent');
ck31((new GlobalReferenceBundle($pack->payload()))->sha()===$pack->sha(),'deterministic archive replay');
$bad=$pack->payload();$bad['records'][0]['value']=456;
try {new GlobalReferenceBundle($bad);throw new RuntimeException('tampered pack accepted');}catch(DomainException|InvalidArgumentException $e){ck31(true,'tampered series rejected');}
$rights['redistribution_approved']=false;
try {GlobalReferenceBundle::import(['SP.POP.TOTL'=>['pages'=>[$page],'rights'=>$rights]],'2026-11-24');throw new RuntimeException('unreviewed license accepted');}
catch(DomainException $e){ck31(true,'rights gate');}

use Aeternum\WorldPolitics\GlobalPublishedObservations as P;
$p=GlobalReferenceBundle::published();
ck31($p->coverage()['population_total']===['observed'=>194,'missing'=>0,'absent'=>1]&&$p->coverage()['gdp_current_usd']===['observed'=>55,'missing'=>0,'absent'=>140],'published country coverage');
ck31($p->manifest['observations']===257&&$p->manifest['published_tables_sha']===P::digest(),'published pack has pinned provenance and 257 records');
ck31($p->references->latest('state_fr','population_total')['value']===68552000&&$p->references->latest('state_fr','gdp_current_usd')['value']===3160443000000,'France historical WDI values preserve published rounding');
ck31($p->references->latest('state_va','population_total')===null,'unavailable Holy See population not invented');
foreach($p->countries->all() as $country)if($country['eligible_for_human_play']||in_array($country['iso2'],['IL','IR','PS','UA'],true))ck31($p->references->latest('state_'.strtolower($country['iso2']),'gdp_current_usd')!==null,'GDP for strategic or protected state '.$country['iso2']);
ck31($p->references->latest('state_fr','bilateral_trade_usd','state_ru')['value']===6768700000&&$p->references->latest('state_ru','bilateral_trade_usd','state_fr')['value']===6768700000,'real bilateral French goods turnover translated at ECB annual rate');
ck31((new GlobalReferenceBundle($p->payload()))->sha()===$p->sha()&&$p->sha()!==$b->sha(),'published bundle exact replay and legacy empty bundle preserved');
$bad=$p->payload();$bad['records'][0]['value']++;try {new GlobalReferenceBundle($bad);throw new RuntimeException('tampered published pack accepted');}catch(DomainException $e){ck31(true,'tampered published record rejected');}
$bad=$p->payload();$bad['published_tables_sha']=str_repeat('0',64);try {new GlobalReferenceBundle($bad);throw new RuntimeException('tampered published pin accepted');}catch(DomainException $e){ck31(true,'tampered published digest rejected');}
ck31(count($p->manifest['published_tables']['organizations_reference_only']['NATO']['members'])===32&&count($p->manifest['published_tables']['organizations_reference_only']['EU']['members'])===27,'observed alliance rosters published as reference only');

require_once __DIR__.'/../games/world-politics/src/ReferenceDataAudit.php';
$a=\Aeternum\WorldPolitics\ReferenceDataAudit::report();
ck31($a['observations']['assembly']['occupied_seats']===574&&$a['observations']['assembly']['majority_of_statutory_seats']===289,'published parliamentary data arithmetic');
ck31($a['observations']['senate']['occupied_seats']===348,'dated Senate group data reconciled');
ck31($a['domains']['fr_companies']['status']==='observed_identities_fictional_behavior','audit does not pretend corporate parameters are observed');
$macro=json_decode(file_get_contents(__DIR__.'/../games/world-politics/data/2026-11-25-france-observations-v2.json'),true,32,JSON_THROW_ON_ERROR);
$values=[];foreach($macro['observations'] as $v)if($v['period']==='2025')$values[$v['metric']]=$v['value'];
ck31(intdiv(($values['exports_goods_services']+$values['imports_goods_services'])*11300,10000)===$a['national_trade_basis_usd'],'trade denominator reconciles with observed French national accounts');

use Aeternum\WorldPolitics\ComparativeElectionCalendar as NC;
$calendar=NC::report();ck31(count($calendar['countries'])===6&&!$calendar['complete_national_electoral_law_implemented'],'six sourced comparative calendars distinguish law from incomplete simulation');
foreach(['DE'=>'2029-03-18','ES'=>'2027-08-22','GB'=>'2029-07-19','IT'=>'2027-10-24','JP'=>'2030-01-27','US'=>'2028-11-07'] as $iso=>$poll){
    $c=NC::config($iso,'2026-11-25');ck31($c['poll_day']===$poll&&$c['runoff_day']===null,'comparative national election horizon '.$iso);
    $el=new \Aeternum\WorldPolitics\ElectoralCycle('world-test',$iso,'2026-11-25',$c);
    $el->advance('2026-11-26');ck31($el->snapshot()['status']==='scheduled','no artificial next-day foreign election '.$iso);
    ck31(\Aeternum\WorldPolitics\ElectoralCycle::restore($el->snapshot())->snapshot()===$el->snapshot(),'calendar replay '.$iso);
}
ck31($calendar['countries']['US']['presidential']['maximum_elections_total']===2&&$calendar['countries']['US']['presidential']['more_than_two_years_of_anothers_term_max_elections']===1,'US constitutional rule is distinct from French consecutive mandate rule');

require_once __DIR__.'/../games/world-politics/src/FrenchMacroBaseline.php';
require_once __DIR__.'/../games/world-politics/src/UkraineAidPolicy.php';
use Aeternum\WorldPolitics\{InvestitureEconomicProjection as IP,UkraineAidPolicy as UA,FrenchMacroBaseline as MB};
$pr=IP::project();$bb=MB::load(true);MB::assertValid($bb);
ck31($pr['opening_debt_minor']>359550000000000&&$bb['opening_debt']===$pr['opening_debt_minor'],'projected opening debt replaces historical anchor');
$bridge=$pr['debt_bridge'];ck31($pr['opening_debt_minor']===$bridge['projected_end_2026_minor']+$bridge['deficit_since_end_2026_minor']+$bridge['stock_flow_adjustment_since_end_2026_minor'],'projected debt stock-flow identity');
ck31($pr['wealth_scenarios']['central']['national']['net_minor']>2019600000000000&&$pr['wealth_scenarios']['central']['public_administrations']['net_minor']<$pr['wealth_scenarios']['central']['national']['net_minor'],'public wealth kept separate from national wealth');
$stop=[['day'=>IP::DAY,'action'=>'ukraine-aid-stop']];UA::validate($stop,IP::DAY,IP::DAY);
$a0=UA::step($bb,[],'2027-05-15',null);$a1=UA::step($bb,$stop,'2027-05-15',null);
ck31($a0['budget_delta_minor']===0&&$a1['discretionary_planned_minor']===0&&$a1['budget_delta_minor']===-$a0['baseline_discretionary_minor']&&$a1['protected_cost_proxy_minor']===$a0['protected_cost_proxy_minor'],'aid stop saves only modelled discretionary portion');
$stop[]=['day'=>'2027-05-15','action'=>'ukraine-aid-resume'];$a2=UA::step($bb,$stop,'2027-05-16',$a1);
ck31($a2['budget_delta_minor']===0&&$a2['cumulative_planned_savings_minor']===$a1['cumulative_planned_savings_minor'],'resumption no retroactive costs');

$aidRef=$bb['aid_reference_v2'];ck31($aidRef['source_pack']['observed']['humanitarian_latest_tenths_million']['total']===4716&&$aidRef['source_pack']['observed']['civil_fund_2026_new_envelope']===71&&$aidRef['not_additive'],'dated civil and humanitarian scope preserved');
ck31($aidRef['future_loans_guarantees_eu_and_refugee_costs_minor']===null&&$aidRef['cooperation_source_discrepancy_million']===-7,'unquantified future flows and source discrepancies explicit');
ck31(count($a1['categories'])===3&&$a1['domestic_demand_delta_proxy_minor']+$a1['external_transfer_delta_proxy_minor']===$a1['budget_delta_minor']&&!$a1['new_bilateral_discretionary_loans_allowed'],'all three cost categories stop, no new discretionary commitments; transfer bridge balances');

require_once __DIR__.'/../games/world-politics/src/FrenchDemography.php';
$su=$bb['opening_supplement'];
ck31(array_sum($su['gross_by_subsector_minor'])===$bb['opening_debt']&&array_sum($su['net_by_subsector_minor'])===$su['opening_net_debt_minor']&&$bb['opening_debt']-$su['opening_net_debt_minor']===22870000000000,'projected sector gross/net debt identity and frozen deductible assets');
ck31($su['observed_gross_rounding_residual_million']===-100&&$su['observed_net_rounding_residual_million']===-100,'published rounding residuals preserved');
ck31($su['purchasing_power']['opening_index_bps']===10000&&!$su['purchasing_power']['score_eligible']&&$su['civil_aid_commitments']['outstanding_payments_minor']===null,'no score from historical purchasing power and no invented commitments');
$demo=\Aeternum\WorldPolitics\FrenchDemography::load(IP::DAY,$bb['initial_unemployment_bps'],true);
\Aeternum\WorldPolitics\FrenchDemography::assertValid($demo,IP::DAY,$bb['initial_unemployment_bps']);
$dp=$demo['opening_population_projection'];
ck31($dp['days']===498&&$dp['projected_population']===$dp['observed_population']+$dp['net_change']&&$dp['projected_population']===array_sum($demo['opening_cohorts']),'investiture demography projected and reconciled');
$legacy=MB::build($bb['source_pack'],true,true);
MB::assertValid($legacy);
ck31(!isset($legacy['opening_supplement']),'older projected macro snapshot remains replayable');
