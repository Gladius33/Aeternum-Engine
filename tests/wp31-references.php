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
