<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\InstitutionalSimulation as I;
use Aeternum\WorldPolitics\ComparativeElectionCalendar as C;
use Aeternum\WorldPolitics\AutonomousForeignFormation as A;
function ck65(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('WP65 FAIL '.$label);echo "PASS WP65: $label\n";}
function make65(string $world,string $cc,bool $auto):I{
    $i=new I($world,$cc,'2027-05-14');
    $seats=match($cc){'DE'=>['bundestag'=>100],'ES'=>['congress'=>100],'GB'=>['commons'=>100],'IT'=>['chamber'=>100,'senate'=>50],'JP'=>['representatives'=>100],'US'=>['house'=>100,'senate'=>50]};
    $i->enableAccountability('initial-government',$seats);$i->enableElectoral(C::config($cc,'2027-05-14'));
    $i->enablePoliticalAI();$i->enableForeignRecurring();$i->enableFormationProtocol();if($auto)$i->enableAutonomousFormation();return $i;
}
foreach(['DE','ES','GB','IT','JP','US'] as $cc){
    $i=make65('world-test',$cc,false);$poll=$i->snapshot()['electoral']['config']['poll_day'];
    $day=(new DateTimeImmutable($poll))->modify('+5 days')->format('Y-m-d');$i->advanceTo($day);$s=$i->snapshot();
    $p=A::plan($s);
    if(in_array($cc,['IT','JP','US'],true)){ck65($p===null,'missing or separate constitutional route '.$cc);continue;}
    ck65($p!==null&&$p===A::plan($s)&&count($p['ballots'])>=1,'deterministic autonomous procedure '.$cc);
    foreach($p['ballots'] as $b)ck65(array_sum($b)===100,'seat conservation '.$cc);
    if($p['status']==='certified')ck65($p['proof']['candidate']===$s['electoral']['coalition']['candidate'],'bound proof '.$cc);
    $s['accountability']['status']='motion_pending';
    ck65(A::plan($s)['reason']==='unresolved_constitutional_procedure','no bypass of unresolved procedure '.$cc);
}
$i=make65('world-test','ES',true);
$poll=$i->snapshot()['electoral']['config']['poll_day'];$due=(new DateTimeImmutable($poll))->modify('+5 days')->format('Y-m-d');
for($d=new DateTimeImmutable('2027-05-15');$d->format('Y-m-d')<=$due;$d=$d->modify('+1 day')){
    $day=$d->format('Y-m-d');$i->advanceTo($day);
    if($day===$poll)ck65($i->snapshot()['accountability']['incumbent']==='initial-government','no appointment on election day');
}
$s=$i->snapshot();$attempts=array_values(array_filter($s['events'],fn($e)=>$e['kind']==='foreign_formation_attempt'));
ck65(count($attempts)===1,'one daily-clock autonomous attempt');
$r=$attempts[0]['result'];
ck65($r['status']==='certified'&&$s['accountability']['incumbent']===$r['candidate'],'actual Spanish fictional succession through gate');
ck65(I::restore($s)->snapshot()===$s,'durable replay of autonomous proof');
$bad=$s;foreach($bad['events'] as &$e)if($e['kind']==='foreign_formation_attempt'){$e['result']['ballots'][0]['yes']--;$e['result']['ballots'][0]['no']++;}unset($e);
try{I::restore($bad);throw new RuntimeException('Tampered vote accepted');}catch(DomainException $expected){}
try{$i->advanceTo((new DateTimeImmutable($due))->modify('+2 days')->format('Y-m-d'));throw new RuntimeException('Skipped daily procedure');}catch(DomainException $expected){}
ck65($i->snapshot()===$s,'no mutation on skipped daily clock');

$it=make65('world-test','IT',false);$poll=$it->snapshot()['electoral']['config']['poll_day'];
$it->advanceTo((new DateTimeImmutable($poll))->modify('+5 days')->format('Y-m-d'));
$state=$it->snapshot();$state['autonomous_formation']=A::VERSION_V2;$r=A::plan($state);
ck65($r!==null&&$r['upper_chamber']!==null&&array_sum($r['upper_chamber']['seats_by_party'])===50&&array_sum($r['upper_chamber']['votes'])===50,'Italy independent upper chamber conservation');
ck65($r===A::plan($state),'Italy deterministic bicameral procedure');
if($r['status']==='certified')ck65($r['proof']['procedure']['steps'][2]['chamber']==='senate','Italy distinct second chamber certificate');
else ck65($r['proof']===null,'Italy failed confidence cannot certify');
$old=$state;$old['autonomous_formation']=A::VERSION;
ck65(A::plan($old)===null,'old Italian snapshot rules preserved');
$it=make65('world-test','IT',true);
$due=(new DateTimeImmutable($poll))->modify('+5 days')->format('Y-m-d');
for($d=new DateTimeImmutable('2027-05-15');$d->format('Y-m-d')<=$due;$d=$d->modify('+1 day'))$it->advanceTo($d->format('Y-m-d'));
$s=$it->snapshot();ck65(I::restore($s)->snapshot()===$s,'Italian autonomous attempt durable replay');
$bad=$s;$bad['events']=array_values(array_filter($bad['events'],fn($e)=>$e['kind']!=='foreign_formation_attempt'));
try{I::restore($bad);throw new RuntimeException('Missing Italian attempt accepted');}catch(DomainException $expected){}

/* WP91: test both copies of a certificate are tampered identically.
 * Independent seat replay must still reject it. */
$es=make65('world-test','ES',true);
$poll=$es->snapshot()['electoral']['config']['poll_day'];$due=(new DateTimeImmutable($poll))->modify('+5 days')->format('Y-m-d');
for($d=new DateTimeImmutable('2027-05-15');$d->format('Y-m-d')<=$due;$d=$d->modify('+1 day'))$es->advanceTo($d->format('Y-m-d'));
$bad=$es->snapshot();
foreach($bad['events'] as &$e){
    if($e['kind']==='foreign_formation_attempt')$proof=&$e['result']['proof'];
    elseif($e['kind']==='electoral_certify')$proof=&$e['result']['national_proof'];
    else continue;
    foreach($proof['procedure']['steps'] as &$step)if($step['kind']==='investiture_vote'){$step['yes']--;$step['no']++;break;}
    unset($step,$proof);
}unset($e);
try{A::validateHistory($bad);throw new RuntimeException('WP91 altered certified tally accepted');}catch(DomainException $expected){}

/* WP119: opt-in Japanese concordant bicameral designation. Old snapshots
 * retain the pre-existing unsupported-route behavior. */
$jp=make65('jp-pilot','JP',false);
$poll=$jp->snapshot()['electoral']['config']['poll_day'];
$due=(new DateTimeImmutable($poll))->modify('+5 days')->format('Y-m-d');
$jp->enableAutonomousFormation(true);
for($d=new DateTimeImmutable('2027-05-15');$d->format('Y-m-d')<=$due;$d=$d->modify('+1 day'))$jp->advanceTo($d->format('Y-m-d'));
$j=$jp->snapshot();
$attempts=array_values(array_filter($j['events'],fn($e)=>$e['kind']==='foreign_formation_attempt'));
ck65(count($attempts)===1&&I::restore($j)->snapshot()===$j,'Japanese opt-in designation journal replays');
$attempt=$attempts[0]['result'];
if($attempt['ballots']!==[]){
    ck65(array_sum($attempt['upper_chamber']['votes'])===50&&array_sum($attempt['upper_chamber']['seats_by_party'])===50,'Japanese independent upper house conserves seats');
    $bad=$j;foreach($bad['events'] as &$ev)if($ev['kind']==='foreign_formation_attempt')$ev['result']['upper_chamber']['votes']['yes']++;unset($ev);
    try{I::restore($bad);throw new RuntimeException('Tampered Japanese upper house accepted');}catch(DomainException $expected){}
}
$bad=$j;$bad['events']=array_values(array_filter($bad['events'],fn($e)=>$e['kind']!=='foreign_formation_attempt'));
try{I::restore($bad);throw new RuntimeException('Missing Japanese attempt accepted');}catch(DomainException $expected){}
// Exercise positive procedural certificate independently of the random fixture.
$cycle=$j['electoral'];
if(($cycle['coalition']['status']??null)==='majority_agreement'){
    $steps=[['kind'=>'designation_vote','at'=>$due.'T09:00:00Z','chamber'=>'representatives','yes'=>70,'no'=>25,'abstain'=>5],['kind'=>'designation_vote','at'=>$due.'T10:00:00Z','chamber'=>'councillors','yes'=>35,'no'=>12,'abstain'=>3],['kind'=>'imperial_appointment','at'=>$due.'T12:00:00Z']];
    $proof=['world_id'=>$cycle['world_id'],'country'=>'JP','evidence_id'=>'jp-concordant-proof','coalition_id'=>$cycle['coalition']['id'],'candidate'=>$cycle['coalition']['candidate'],'day'=>$due,'procedure_certified'=>true,'procedure'=>['poll_day'=>$poll,'steps'=>$steps]];
    $base=\Aeternum\WorldPolitics\FormationProtocol::certificate($proof,$cycle,A::formationSeats($j));
    ck65($base['candidate']===$cycle['coalition']['candidate'],'both Diet chambers and imperial appointment required');
    $proof['procedure']['steps'][1]['yes']=20;$proof['procedure']['steps'][1]['no']=27;
    try{\Aeternum\WorldPolitics\FormationProtocol::certificate($proof,$cycle,A::formationSeats($j));throw new RuntimeException('Failed Japanese designation accepted');}catch(DomainException $expected){}
}

/* WP119 separate US presidency: no congressional appointment, deterministic
 * electoral college, historical term limits, replay and exact inauguration. */
$us=new I('us-president-test','US','2027-05-14');
$us->enableAccountability(\Aeternum\WorldPolitics\UsPresidentialElection::initial(false),['house'=>100,'senate'=>50]);
$us->enableElectoral(\Aeternum\WorldPolitics\DocumentaryForeignBallot::config('US',C::config('US','2027-05-14')));
$us->enablePoliticalAI();$us->enableForeignRecurring();$us->enableFormationProtocol();$us->enableAutonomousFormation();$us->enableUsPresidential();
$u=\Aeternum\WorldPolitics\UsPresidentialElection::class;
$dates=$u::dates(2028);
ck65($dates['ballot']==='2028-11-07'&&$dates['electors']==='2028-12-19'&&$dates['inauguration']==='2029-01-20','US presidential calendar independent of Congress');
$original=$us->snapshot()['accountability']['incumbent'];
$r=$u::result($us->snapshot()['accountability'],2028);
ck65(array_sum($r['electoral_votes'])===538&&!in_array($original,$r['nominees'],true)&&$r['winner']!==null,'US electoral college and twice-elected incumbent exclusion');
for($d=new DateTimeImmutable('2027-05-15');$d->format('Y-m-d')<=$dates['inauguration'];$d=$d->modify('+1 day')){
    $day=$d->format('Y-m-d');$us->advanceTo($day);
    if($day<$dates['inauguration']&&in_array($day,$dates,true))ck65($us->snapshot()['accountability']['incumbent']===$original,'no early presidential accession '.$day);
}
$usState=$us->snapshot();
ck65($usState['accountability']['incumbent']===$r['winner']&&I::restore($usState)->snapshot()===$usState,'US inaugurated winner and replay');
$bad=$usState;foreach($bad['events'] as &$event)if($event['kind']==='us_presidential_count')$event['result']['electoral_votes'][$r['winner']]++;unset($event);
try{I::restore($bad);throw new RuntimeException('US forged count accepted');}catch(DomainException $expected){}
$bad=$usState;$bad['events']=array_values(array_filter($bad['events'],fn($e)=>$e['kind']!=='us_presidential_electors'));
try{I::restore($bad);throw new RuntimeException('Missing US elector stage accepted');}catch(DomainException $expected){}

// Article 67 alternative designation and ten non-recess days, opt-in only.
$found=null;
for($n=0;$n<100;$n++){
    $fixture=make65('jp-extended-'.$n,'JP',false);
    $p=$fixture->snapshot()['electoral']['config']['poll_day'];
    $d=(new DateTimeImmutable($p))->modify('+13 days')->format('Y-m-d');
    $fixture->advanceTo($d);$snap=$fixture->snapshot();$snap['autonomous_formation']=A::VERSION_V4;
    $attempt=A::plan($snap);
    if(($attempt['status']??null)==='certified'&&count($attempt['proof']['procedure']['steps'])===4){$found=[$n,$d,$attempt];break;}
}
ck65($found!==null,'Japanese extended fixture exercises Article 67');
[$n,$d,$attempt]=$found;
$extended=make65('jp-extended-'.$n,'JP',false);$extended->enableAutonomousFormation(true,true);
for($t=new DateTimeImmutable('2027-05-15');$t->format('Y-m-d')<=$d;$t=$t->modify('+1 day'))$extended->advanceTo($t->format('Y-m-d'));
$s=$extended->snapshot();ck65(I::restore($s)->snapshot()===$s&&$s['accountability']['incumbent']===$attempt['candidate'],'Japanese extended succession replays');
$protocol=\Aeternum\WorldPolitics\FormationProtocol::class;
$proof=$attempt['proof'];$cycle=$s['electoral'];$seats=A::formationSeats($s);
try{$protocol::certificate($proof,$cycle,$seats);throw new RuntimeException('Old pin accepted extended route');}catch(DomainException $expected){}
$proof['procedure']['steps'][2]['kind']='councillors_deadline_elapsed';
$proof['procedure']['steps'][2]['at']=$proof['procedure']['steps'][1]['at']; // premature, duplicate instant
try{$protocol::certificate($proof,$cycle,$seats,true);throw new RuntimeException('Premature deadline accepted');}catch(DomainException $expected){}
