<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{PublicOpinion,PartyPolicyResponse,PresidentialMandate};
function ckParty(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "PASS party: $msg\n";}
$start='2026-11-25';$day=$start;$a=PartyPolicyResponse::initial($start);$b=$a;
$groups=['civic'=>['economy'=>500,'services'=>600,'tolerance'=>180,'seats'=>250],
    'liberal'=>['economy'=>650,'services'=>450,'tolerance'=>180,'seats'=>150],
    'social'=>['economy'=>200,'services'=>900,'tolerance'=>150,'seats'=>177]];
$base=['groups'=>$groups,'corporation_mode'=>'pluralist','industrial_mode'=>'not_started','funding_bps'=>10000,'output_bps'=>10000];
$other=$base;$other['corporation_mode']='exclusive_contested';$other['industrial_mode']='active';
for($i=1;$i<=40;$i++){
    $day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
    $a=PartyPolicyResponse::advance($a,$day,$base);$b=PartyPolicyResponse::advance($b,$day,$other);
    if(array_sum($a['seat_stances'])!==577||array_sum($b['seat_stances'])!==577||$a['legal_effect']||$b['legal_effect'])throw new RuntimeException('Seat or authority invariant');
}
ckParty($a['groups']['civic']['stance']==='support'&&$a['groups']['liberal']['stance']==='negotiate'&&$b['groups']['liberal']['stance']==='oppose','different policies produce different fictional positions');
ckParty(count(array_unique(array_column($b['groups'],'alignment_index')))>1,'independent group preferences');
$unknown=$base;foreach($unknown['groups'] as &$p)$p['seats']=null;unset($p);
$day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');$b=PartyPolicyResponse::advance($b,$day,$unknown);
ckParty($b['seat_stances']===null&&!$b['seat_counts_available'],'unknown seats not fabricated');
$x=new PublicOpinion('world-test',$start,PublicOpinion::fixture());$x->enableDialogue();$x->enableCampaign();$x->enablePresidentialMandate(PresidentialMandate::fixture(),true);$x->enablePartyResponses();
for($i=1;$i<=12;$i++){
    $day=(new DateTimeImmutable($start))->modify('+'.$i.' days')->format('Y-m-d');
    $r=$x->advance(['world_id'=>'world-test','day'=>$day,'domestic_sha'=>str_repeat('a',64),'institutional_sha'=>str_repeat('b',64),'pressure'=>['workers'=>400,'retired'=>300],'active_policies'=>[],'production_index_bps'=>10000,'employment_index_bps'=>10000,'constitutional_breaches'=>0,'legislature'=>'sitting','election_status'=>null,'party_observation'=>$other]);
}
ckParty($r['party_responses']['groups']['liberal']['stance']==='oppose'&&!$r['regime_changed'],'party response independent of automatic regime outcome');
ckParty(PublicOpinion::restore($x->snapshot())->hash()===$x->hash(),'exact replay');
