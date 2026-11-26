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
