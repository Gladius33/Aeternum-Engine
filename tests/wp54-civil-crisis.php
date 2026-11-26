<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{PublicOpinion,CampaignLifecycle,CivilMobilisation};
function ck54(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP54 FAIL: '.$s);echo "PASS WP54: $s\n";}
$day='2026-11-25';$x=new PublicOpinion('world-test',$day,PublicOpinion::fixture());$x->enableDialogue();$x->enableSocialPartners();$x->enableCampaign();$x->enableCivilCrisis();
$peak=0;$early=0;
for($n=1;$n<=100;$n++){
    $day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
    $e=['world_id'=>'world-test','day'=>$day,'domestic_sha'=>str_repeat('a',64),'institutional_sha'=>str_repeat('b',64),
        'pressure'=>$n<=35?['workers'=>750,'retired'=>550]:['workers'=>400,'retired'=>300],
        'active_policies'=>[],'production_index_bps'=>10000,'employment_index_bps'=>10000,'constitutional_breaches'=>0,
        'legislature'=>'sitting','election_status'=>null,'partner_observation'=>['funding_bps'=>10000,'health_shortfall_bps'=>0,'education_shortfall_bps'=>0,'pension_shortfall_bps'=>0,'unemployment_bps'=>800],
        'civil_observation'=>['population'=>68000000,'service_funding_bps'=>10000]];
    $kind=match($n){2=>'leadership_displaced',4=>'leadership_abroad',45=>'leadership_returned',default=>null};
    $event=$kind===null?null:CampaignLifecycle::event('world-test',$day,'continuity-'.$n,$kind);
    $r=$x->advance($e,$event);$v=$r['civil_mobilisation'];
    if(array_sum($v['cities'])!==$v['participation_estimate']||$v['estimate_low']>$v['participation_estimate']||$v['estimate_high']<$v['participation_estimate']||$r['campaign']['status']!=='active'||!$r['campaign']['office_retained'])throw new RuntimeException('Civil continuity invariant');
    $peak=max($peak,$v['participation_estimate']);
    if($n===4)ck54($r['campaign']['continuity']['location_status']==='abroad','abroad without terminal outcome');
    if($n===45)ck54($r['campaign']['continuity']['location_status']==='domestic','return preserves office');
}
ck54($peak>0&&$v['participation_estimate']<$peak,'attendance and recovery');
ck54(PublicOpinion::restore($x->snapshot())->hash()===$x->hash(),'replay of civil crisis and continuity');
$bad=$x->snapshot();$bad['civil_mobilisation']['cities']['Paris']++;
try{PublicOpinion::restore($bad);throw new RuntimeException('tampering accepted');}catch(DomainException $e){ck54(true,'tampering rejected');}
$old=CampaignLifecycle::initial('world-test','2026-11-25');
try{CampaignLifecycle::advance($old,'2026-11-26',CampaignLifecycle::event('world-test','2026-11-26','bad-event','leadership_abroad'));throw new RuntimeException('legacy event accepted');}catch(DomainException $e){ck54(true,'legacy scenario isolation');}
