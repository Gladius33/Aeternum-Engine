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
