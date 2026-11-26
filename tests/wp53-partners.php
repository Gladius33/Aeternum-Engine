<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{PublicOpinion,SocialPartners};
function ck53(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP53 FAIL: '.$s);echo "PASS WP53: $s\n";}
function no53(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck53(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
$day='2026-11-25';
$x=new PublicOpinion('world-test',$day,PublicOpinion::fixture());$x->enableDialogue();$x->enableSocialPartners();
no53(fn()=>$x->enableSocialPartners(),'duplicate bootstrap refused');
$legacy=new PublicOpinion('world-test',$day,PublicOpinion::fixture());$legacy->enableDialogue();
$base=['funding_bps'=>10000,'health_shortfall_bps'=>0,'education_shortfall_bps'=>0,'pension_shortfall_bps'=>0,'unemployment_bps'=>800];
$seen=[];$peak=0;
for($n=1;$n<=65;$n++){
    $day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
    $e=['world_id'=>'world-test','day'=>$day,'domestic_sha'=>str_repeat('a',64),'institutional_sha'=>str_repeat('b',64),
        'pressure'=>['workers'=>400,'retired'=>300],'active_policies'=>[],'production_index_bps'=>10000,'employment_index_bps'=>10000,
        'constitutional_breaches'=>0,'legislature'=>'sitting','election_status'=>null];
    $old=$legacy->advance($e);
    $e['partner_observation']=$n<=20?array_replace($base,['health_shortfall_bps'=>8000]):$base;
    $r=$x->advance($e);$v=$r['social_partners'];$h=$v['groups']['health'];
    $seen[$h['phase']]=true;$peak=max($peak,$h['grievance']);
    if($v['groups']['education']['phase']!=='idle'||$v['groups']['retirees']['phase']!=='idle'||$v['legal_agreement']||$v['regime_changed']
        ||$r['approval']!==$old['approval']||$r['regime_changed'])throw new RuntimeException('WP53 sector isolation or false consequence');
}
ck53($peak>120&&isset($seen['consultation_requested'],$seen['dialogue_open'],$seen['unresolved'],$seen['relief_observed']),'sector pressure and gradual recovery');
ck53($x->snapshot()['social_partners']['groups']['health']['phase']==='idle','consultation returns to idle');
ck53(!isset($legacy->snapshot()['social_partners']),'old scenario remains unchanged');
ck53(PublicOpinion::restore($x->snapshot())->hash()===$x->hash(),'exact replay');
$bad=$x->snapshot();$bad['social_partners']['groups']['health']['meetings']++;
no53(fn()=>PublicOpinion::restore($bad),'tampered partner state rejected');
$e['day']=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');$e['partner_observation']['funding_bps']=10001;
no53(fn()=>$x->advance($e),'invalid funding refused');
$initial=SocialPartners::initial('2026-11-25');
no53(fn()=>SocialPartners::advance($initial,'2026-11-27',['workers'=>400,'retired'=>300],['workers'=>400,'retired'=>300],$base),'nonconsecutive day refused');
$obs=SocialPartners::observe(['services'=>['primary_funding_bps'=>7500,'health'=>['unserved_bed_demand_proxy'=>3,'daily_bed_demand_proxy'=>12],
    'education'=>['unserved_pupils_proxy'=>0,'pupils_proxy'=>100],'pensions'=>['daily_unpaid_proxy_minor'=>100,'daily_reference_need_minor'=>400]],
    'macro'=>['unemployment_bps'=>850]]);
ck53($obs['health_shortfall_bps']===2500&&$obs['pension_shortfall_bps']===2500&&$obs['unemployment_bps']===850,'committed observation ratios');
