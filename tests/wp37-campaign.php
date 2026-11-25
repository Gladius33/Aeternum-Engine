<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{PublicOpinion,CampaignLifecycle};
function ck37(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP37 FAIL: '.$s);echo "PASS WP37: $s\n";}
function no37(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck37(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function event37(string $day,string $type,string $id):array{return CampaignLifecycle::event('world-test',$day,$id,$type);}
function next37(array $s,string $type,string $id):array{
    $day=(new DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d');
    return CampaignLifecycle::advance($s,$day,event37($day,$type,$id));
}
$s=CampaignLifecycle::initial('world-test','2026-11-25');
$s=next37($s,'cabinet_resigned','cabinet-one');
ck37($s['office_retained']&&$s['status']==='active'&&$s['cabinet_status']==='caretaker','cabinet resignation not loss of presidency');
$s=next37($s,'cabinet_reconstituted','cabinet-two');
no37(fn()=>next37($s,'revolution_succeeded','invalid'),'no revolution without independently authored episode');
$s=next37($s,'constitutional_crisis_opened','crisis-one');
$s=next37($s,'revolution_attempted','attempt-one');
ck37($s['status']==='active'&&!$s['regime_changed'],'attempt is not success');
$failed=next37($s,'revolution_failed','attempt-failed');
$failed=next37($failed,'constitutional_crisis_resolved','crisis-resolved');
ck37($failed['office_retained']&&$failed['constitutional_status']==='continuity'&&!$failed['regime_changed'],'failed revolution permits continued campaign');
$won=next37($s,'revolution_succeeded','attempt-succeeded');
ck37($won['status']==='game_over'&&!$won['office_retained']&&$won['regime_changed']&&$won['reason']==='revolution_succeeded','successful revolutionary transition terminates campaign');
no37(fn()=>next37($won,'cabinet_reconstituted','restart'),'terminal state cannot advance or recover');
foreach(['presidential_election_lost','presidential_mandate_ended','presidential_resignation','presidential_removal','presidential_death'] as $type){
    $lost=next37(CampaignLifecycle::initial('world-test','2026-11-25'),$type,'loss');
    ck37($lost['status']==='game_over'&&!$lost['regime_changed']&&$lost['reason']===$type,'office loss without regime change '.$type);
}
$initial=CampaignLifecycle::initial('world-test','2026-11-25');
no37(fn()=>CampaignLifecycle::validateQueue($initial,[event37('2026-11-26','presidential_election_lost','one'),event37('2026-11-27','cabinet_resigned','two')]),'future events after terminal outcome rejected');
$op=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());$op->enableDialogue();$op->enableCampaign();
function observation37(string $day):array{return ['world_id'=>'world-test','day'=>$day,'domestic_sha'=>str_repeat('a',64),
    'institutional_sha'=>str_repeat('b',64),'pressure'=>['workers'=>1000,'retired'=>1000],'active_policies'=>[],
    'production_index_bps'=>0,'employment_index_bps'=>0,'constitutional_breaches'=>10,'legislature'=>'dissolved','election_status'=>'constitutional_deadline_missed'];}
for($n=1;$n<=45;$n++){
    $day=(new DateTimeImmutable('2026-11-25'))->modify('+'.$n.' days')->format('Y-m-d');
    $r=$op->advance(observation37($day));
    if($r['campaign']['status']!=='active'||$r['regime_changed'])throw new RuntimeException('Unrest alone ended campaign');
}
ck37($op->snapshot()['civic_dissatisfaction']>900&&$op->snapshot()['campaign']['office_retained'],'extreme fictional dissatisfaction and institutional breach do not automatically end campaign');
$day=(new DateTimeImmutable($op->snapshot()['day']))->modify('+1 day')->format('Y-m-d');
$op->advance(observation37($day),event37($day,'presidential_election_lost','certified-loss'));
ck37(PublicOpinion::restore($op->snapshot())->hash()===$op->hash(),'terminal journal replay');
$bad=$op->snapshot();$bad['campaign']['status']='active';
no37(fn()=>PublicOpinion::restore($bad),'tampered terminal state rejected');
