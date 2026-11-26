<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\InstitutionalSimulation as I;
use Aeternum\WorldPolitics\ComparativeElectionCalendar as C;
use Aeternum\WorldPolitics\FormationProtocol as F;
function ck63(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('WP63 FAIL '.$label);echo "PASS WP63: $label\n";}
function deny63(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection');}
function make63(string $cc):I{
    $i=new I('world-test',$cc,'2027-05-14');
    $seats=match($cc){'DE'=>['bundestag'=>100],'ES'=>['congress'=>100],'GB'=>['commons'=>100],'IT'=>['chamber'=>100,'senate'=>50]};
    $i->enableAccountability('initial-government',$seats);$i->enableElectoral(C::config($cc,'2027-05-14'));
    $i->enablePoliticalAI();$i->enableForeignRecurring();$i->enableFormationProtocol();return $i;
}
function proof63(I $i):array{
    $s=$i->snapshot();$poll=$s['electoral']['config']['poll_day'];$day=(new DateTimeImmutable($poll))->modify('+5 days')->format('Y-m-d');
    $i->advanceTo($day);$s=$i->snapshot();$c=$s['electoral']['coalition'];
    $at=fn(int $n)=>(new DateTimeImmutable($poll))->modify('+'.$n.' days')->format('Y-m-d').'T12:00:00Z';
    $step=fn(string $kind,int $n)=>['kind'=>$kind,'at'=>$at($n)];
    $vote=fn(string $kind,int $n,string $ch,int $yes,int $no,int $abstain)=>$step($kind,$n)+['chamber'=>$ch,'yes'=>$yes,'no'=>$no,'abstain'=>$abstain];
    $steps=match($s['country_code']){
        'DE'=>[$step('presidential_nomination',1),$vote('chancellor_vote',3,'bundestag',55,40,5),$step('presidential_appointment',5)],
        'ES'=>[$step('royal_nomination_after_consultations',1),$vote('investiture_vote',2,'congress',49,45,6),$vote('investiture_vote',4,'congress',49,45,6),$step('royal_appointment',5)],
        'GB'=>[$step('commons_confidence_assessed',2),$step('royal_appointment',5)],
        'IT'=>[$step('presidential_appointment',1),$vote('confidence_vote',3,'chamber',55,40,5),$vote('confidence_vote',5,'senate',28,20,2)]
    };
    return ['world_id'=>'world-test','country'=>$s['country_code'],'evidence_id'=>'formation-one','coalition_id'=>$c['id'],'candidate'=>$c['candidate'],'day'=>$day,'procedure_certified'=>true,'procedure'=>['poll_day'=>$poll,'steps'=>$steps]];
}
foreach(['DE','ES','GB','IT'] as $cc){
    $i=make63($cc);$e=proof63($i);$before=$i->snapshot();
    ck63($before['accountability']['incumbent']==='initial-government','coalition not appointment '.$cc);
    $bare=$e;unset($bare['procedure']);deny63(fn()=>$i->electoral('constitutional_clerk','electoral_certify',$bare,'cert-one'));
    $bad=$e;$bad['procedure']['steps']=array_slice($bad['procedure']['steps'],0,-1);deny63(fn()=>$i->electoral('constitutional_clerk','electoral_certify',$bad,'cert-one'));
    $bad=$e;$bad['world_id']='wrong-world';deny63(fn()=>$i->electoral('constitutional_clerk','electoral_certify',$bad,'cert-one'));
    deny63(fn()=>$i->electoral('scenario_negotiator','electoral_certify',$e,'cert-one'));
    ck63($i->snapshot()===$before,'failed attempts atomic '.$cc);
    $r=$i->electoral('constitutional_clerk','electoral_certify',$e,'cert-one');
    ck63($r['candidate']===$e['candidate']&&$i->snapshot()['accountability']['incumbent']===$e['candidate'],'certified formation '.$cc);
    $s=$i->snapshot();ck63(I::restore($s)->snapshot()===$s,'proof survives replay '.$cc);
    $bad=$s;foreach($bad['events'] as &$ev)if($ev['kind']==='electoral_certify')unset($ev['result']['national_proof']);unset($ev);
    deny63(fn()=>I::restore($bad));
    $i->advanceTo('2040-01-01');ck63(I::restore($i->snapshot())->snapshot()===$i->snapshot(),'proof survives cycle rollover '.$cc);
}
$i=make63('IT');$e=proof63($i);$e['procedure']['steps'][2]['chamber']='chamber';
deny63(fn()=>$i->electoral('constitutional_clerk','electoral_certify',$e,'cert-one'));
$i=make63('ES');$e=proof63($i);$e['procedure']['steps'][2]['at']=str_replace('T12:00:00Z','T11:00:00Z',$e['procedure']['steps'][2]['at']);
deny63(fn()=>$i->electoral('constitutional_clerk','electoral_certify',$e,'cert-one'));
ck63(F::references()['JP']['supported']===null&&F::references()['US']['supported']===null,'unsupported routes fail closed');
