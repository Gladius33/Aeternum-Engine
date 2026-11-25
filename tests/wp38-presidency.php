<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{PublicOpinion,PresidentialMandate,CampaignLifecycle};
function ck38(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP38 FAIL: '.$s);echo "PASS WP38: $s\n";}
function no38(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck38(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function tick38(array $s,string $end):array{
    while($s['day']<$end)$s=PresidentialMandate::advance($s,(new DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d'));
    return $s;
}
$c=PresidentialMandate::fixture();$s=PresidentialMandate::initial('world-test','2026-11-25',$c);
ck38($s['term_end']==='2026-12-21'&&$s['first_round_day']==='2026-11-26'&&$s['second_round_day']==='2026-12-10','five-year term and 25-day first-round interval');
$s=tick38($s,'2026-11-26');
ck38($s['status']==='runoff_pending'&&$s['first_result']['finalists']===['campaign-president','challenger-a'],'top two advance');
ck38($s['first_result']['valid_votes']===93000&&$s['first_result']['abstentions']===6000,'ballot accounting excludes blank invalid votes');
$s=tick38($s,'2026-12-10');
ck38($s['result']['winner']==='campaign-president'&&$s['consecutive_terms']===1,'election result does not start next mandate');
$s=tick38($s,'2026-12-21');
ck38($s['consecutive_terms']===2&&!$s['eligible_for_re_election']&&$s['term_end']==='2031-12-21'&&$s['officeholder']==='campaign-president','reelection begins second term at handover');
$s=tick38($s,'2031-12-21');
ck38($s['status']==='ended'&&$s['reason']==='presidential_mandate_ended'&&$s['successor']==='challenger-a','two consecutive terms maximum');
$bad=$c;$bad['cycles'][1]['first']['votes']['campaign-president']=1;
no38(fn()=>PresidentialMandate::initial('world-test','2026-11-25',$bad),'third consecutive candidacy rejected');
$bad=$c;$bad['cycles'][0]['second']['votes']['challenger-b']=1;
no38(fn()=>PresidentialMandate::initial('world-test','2026-11-25',$bad),'eliminated candidate rejected');
$bad=$c;$bad['cycles'][0]['first']['blank_invalid']=10000;
no38(fn()=>PresidentialMandate::initial('world-test','2026-11-25',$bad),'impossible turnout rejected');
$loss=$c;$loss['cycles'][0]['second']['votes']=['campaign-president'=>44000,'challenger-a'=>49000];
$s=tick38(PresidentialMandate::initial('world-test','2026-11-25',$loss),'2026-12-10');
ck38($s['result']['winner']==='challenger-a'&&$s['officeholder']==='campaign-president'&&$s['status']==='result_known','loser remains incumbent until handover');
$s=tick38($s,'2026-12-21');ck38($s['reason']==='presidential_election_lost'&&$s['successor']==='challenger-a','defeat becomes terminal on handover');
$first=$c['cycles'][0];$first['first']['votes']=['campaign-president'=>60000,'challenger-a'=>20000,'challenger-b'=>10000];$first['second']=null;
ck38(PresidentialMandate::countElection($first,true)['first']['winner']==='campaign-president','absolute majority in first round');
$tie=$c['cycles'][0];$tie['second']['votes']=['campaign-president'=>45000,'challenger-a'=>45000];
ck38(PresidentialMandate::countElection($tie,true)['second']['winner']===null,'tie never invents a winner');
function observation38(string $day):array{return ['world_id'=>'world-test','day'=>$day,'domestic_sha'=>str_repeat('a',64),
    'institutional_sha'=>str_repeat('b',64),'pressure'=>['workers'=>400,'retired'=>300],'active_policies'=>[],
    'production_index_bps'=>10000,'employment_index_bps'=>10000,'constitutional_breaches'=>0,'legislature'=>'sitting','election_status'=>null];}
$op=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());$op->enableDialogue();$op->enableCampaign();$op->enablePresidentialMandate($loss);
for($n=1;$n<=26;$n++){
    $day=(new DateTimeImmutable('2026-11-25'))->modify('+'.$n.' days')->format('Y-m-d');$r=$op->advance(observation38($day));
    if(($r['campaign']['status']==='game_over')!==($n===26))throw new RuntimeException('Incorrect terminal day');
}
ck38($op->snapshot()['campaign']['reason']==='presidential_election_lost'&&!$op->snapshot()['campaign']['regime_changed'],'electoral loss without regime change');
ck38(PublicOpinion::restore($op->snapshot())->hash()===$op->hash(),'presidential replay preserves terminal outcome');
$bad=$op->snapshot();$bad['presidency']['officeholder']='campaign-president';
no38(fn()=>PublicOpinion::restore($bad),'tampered succession rejected');
$other=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());$other->enableDialogue();$other->enableCampaign();$other->enablePresidentialMandate($c);
no38(fn()=>$other->advance(observation38('2026-11-26'),CampaignLifecycle::event('world-test','2026-11-26','fake-loss','presidential_election_lost')),'narrative cannot bypass electoral calendar');
$other->advance(observation38('2026-11-26'),CampaignLifecycle::event('world-test','2026-11-26','resignation','presidential_resignation'));
ck38($other->snapshot()['presidency']['status']==='ended'&&$other->snapshot()['presidency']['officeholder']===null,'independent resignation terminates office');
