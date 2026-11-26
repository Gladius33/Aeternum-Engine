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

use Aeternum\WorldPolitics\{ConstitutionalRevision as CR,FrenchElectionCalendar as EC,ParliamentaryLifecycle as PL};
$real=PresidentialMandate::publishedFixture();
$init=PresidentialMandate::initial('world-test','2026-11-25',$real);
ck38($init['first_round_day']==='2027-04-18'&&$init['second_round_day']==='2027-05-02'&&$init['term_end']==='2027-05-14','announced national dates and exclusive handover');
ck38(EC::assemblyExpiry(2024)==='2029-06-19'&&PL::publishedElectionConfig('2026-11-25')['poll_day']==='2029-06-03','statutory Assembly expiry and explicitly synthetic poll date');
$rev=new CR('world-test','2026-11-25');$d=$rev->draft('remove-limit','remove-presidential-term-limit');
function termEvidence38(CR $r,string $kind,array $data):array{
    return $r->certify(['world_id'=>'world-test','country'=>'FR','day'=>$r->snapshot()['day'],'evidence_id'=>'term-'.$kind.($data['chamber']??''),'revision_id'=>'remove-limit','kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);
}
ck38(CR::termLimitProof($rev->snapshot(),'world-test','2026-11-25')===null,'draft not term-limit change');
termEvidence38($rev,'file',['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>true,'republican_form_preserved'=>true]);
foreach(['assembly','senate'] as $ch)termEvidence38($rev,'chamber_vote',['chamber'=>$ch,'text_sha'=>$d['text_sha'],'yes'=>200,'no'=>100,'abstain'=>0,'blank_invalid'=>0]);
termEvidence38($rev,'route',['route'=>'congress','presidential_decision'=>true]);
termEvidence38($rev,'ratify',['route'=>'congress','text_sha'=>$d['text_sha'],'yes'=>480,'no'=>320,'abstain'=>0,'blank_invalid'=>0]);
ck38(CR::termLimitProof($rev->snapshot(),'world-test','2026-11-25')===null,'adopted but unpublished revision not effective');
$rev->advance('2026-11-26');termEvidence38($rev,'promulgate',['text_sha'=>$d['text_sha'],'published_on'=>'2026-11-26']);
$proof=CR::termLimitProof($rev->snapshot(),'world-test','2026-11-26');
ck38($proof['effective_on']==='2026-11-26','only effective Article 89 publication yields proof');
$without=$init;$with=$init;
while($without['day']<'2032-05-14')$without=PresidentialMandate::advance($without,(new DateTimeImmutable($without['day']))->modify('+1 day')->format('Y-m-d'),1000);
ck38($without['status']==='ended'&&$without['consecutive_terms']===2&&$without['reason']==='presidential_mandate_ended','two consecutive terms remain binding even with maximum approval');
while($with['day']<'2037-05-14')$with=PresidentialMandate::advance($with,(new DateTimeImmutable($with['day']))->modify('+1 day')->format('Y-m-d'),1000,$proof);
ck38($with['consecutive_terms']===4&&$with['status']==='scheduled'&&$with['term_end']==='2042-05-14'&&$with['term_limit']===null,'repeal permits successive real game elections beyond ten years');
$late=$init;while($late['day']<'2032-04-01')$late=PresidentialMandate::advance($late,(new DateTimeImmutable($late['day']))->modify('+1 day')->format('Y-m-d'),1000);
$lateProof=$proof;$lateProof['effective_on']='2032-04-02';
$late=PresidentialMandate::advance($late,'2032-04-02',1000,$lateProof);
ck38($late['candidacy_locked']&&!$late['eligible_for_re_election']&&$late['term_limit']===null,'late publication cannot reopen locked candidacy');
$op2=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());$op2->enableDialogue();$op2->enableCampaign();$op2->enablePresidentialMandate($real,true);
$e=observation38('2026-11-26');$e['term_limit_revision']=$proof;$op2->advance($e);
ck38($op2->snapshot()['presidency']['term_limit']===null&&PublicOpinion::restore($op2->snapshot())->hash()===$op2->hash(),'opinion source bridge and exact replay');
$bad=$e;$bad['day']='2026-11-27';$bad['term_limit_revision']=null;
no38(fn()=>$op2->advance($bad),'published constitutional proof cannot silently disappear');

$invest=PresidentialMandate::initial('world-test','2027-05-14',PresidentialMandate::investitureFixture());
ck38($invest['consecutive_terms']===1&&$invest['term_start']==='2027-05-14'&&$invest['term_end']==='2032-05-14'&&$invest['elections']===[],'canonical campaign starts at first investiture with no previous election counted');
$unchanged=$invest;$amended=$invest;
while($unchanged['day']<'2037-05-14')$unchanged=PresidentialMandate::advance($unchanged,(new DateTimeImmutable($unchanged['day']))->modify('+1 day')->format('Y-m-d'),1000);
ck38($unchanged['status']==='ended'&&$unchanged['consecutive_terms']===2,'first full two terms end in 2037');
$proof27=$proof;$proof27['effective_on']='2027-05-15';
while($amended['day']<'2042-05-14')$amended=PresidentialMandate::advance($amended,(new DateTimeImmutable($amended['day']))->modify('+1 day')->format('Y-m-d'),1000,$proof27);
ck38($amended['status']==='scheduled'&&$amended['consecutive_terms']===4&&$amended['term_end']==='2047-05-14','third full term won after effective constitutional amendment');
