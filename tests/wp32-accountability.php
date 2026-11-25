<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{ComparativeGovernment,InstitutionalSimulation,InstitutionalRulebook};
function ck32(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP32 FAIL: '.$s);echo "PASS WP32: $s\n";}
function reject32(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck32(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function ev32(string $cc,string $id,string $date,array $extra):array{return ['world_id'=>'world-test','country'=>$cc,'evidence_id'=>$id,'motion_id'=>'motion-one','at'=>$date.'T12:00:00Z','procedure_certified'=>true]+$extra;}
function file32(ComparativeGovernment $g,string $cc,string $ch,?string $candidate,int $signatures=10,string $kind='no_confidence'):void{
    $g->apply('accountability_file','parliament_clerk',ev32($cc,'filing','2026-11-25',['chamber'=>$ch,'signatures'=>$signatures,'candidate'=>$candidate,'kind'=>$kind]));
}
function vote32(ComparativeGovernment $g,string $cc,string $ch,string $day,int $yes,int $no,int $abstain,string $id='voting'):array{
    return $g->apply('accountability_vote','parliament_clerk',ev32($cc,$id,$day,['chamber'=>$ch,'present'=>$yes+$no+$abstain,'yes'=>$yes,'no'=>$no,'abstain'=>$abstain]));
}
function settle32(ComparativeGovernment $g,string $cc,string $day,string $outcome,?string $candidate,string $id='settling'):array{
    return $g->apply('accountability_resolve','constitutional_clerk',ev32($cc,$id,$day,['outcome'=>$outcome,'successor'=>$candidate]));
}
foreach (['DE'=>'bundestag','ES'=>'congress'] as $cc=>$ch){
    $g=new ComparativeGovernment('world-test',$cc,'2026-11-25','old-government',[$ch=>100]);
    file32($g,$cc,$ch,'new-government');
    reject32(fn()=>vote32($g,$cc,$ch,'2026-11-25',51,0,0),'minimum delay '.$cc);
    $g->advance('2026-11-30');
    $r=vote32($g,$cc,$ch,'2026-11-30',51,30,19);
    ck32($r['government_status']==='appointment_required'&&$r['threshold']===51,'constructive majority '.$cc);
    reject32(fn()=>settle32($g,$cc,'2026-11-30','appoint_successor','other-government'),'cannot substitute candidate '.$cc);
    ck32(settle32($g,$cc,'2026-11-30','appoint_successor','new-government')['incumbent']==='new-government','certified succession '.$cc);
    ck32(ComparativeGovernment::restore($g->snapshot())->snapshot()===$g->snapshot(),'replay '.$cc);
}
$gb=new ComparativeGovernment('world-test','GB','2026-11-25','old-government',['commons'=>100]);
file32($gb,'GB','commons',null,0);
ck32(vote32($gb,'GB','commons','2026-11-25',40,30,20)['government_status']==='response_required','UK confidence based on votes cast');
ck32(settle32($gb,'GB','2026-11-25','resign',null)['government_status']==='caretaker','UK resignation not regime collapse');
$it=new ComparativeGovernment('world-test','IT','2026-11-25','old-government',['chamber'=>100,'senate'=>50]);
reject32(fn()=>file32($it,'IT','senate',null,4),'Italian tenth signature threshold');
file32($it,'IT','senate',null,5);$it->advance('2026-11-28');
ck32(vote32($it,'IT','senate','2026-11-28',26,20,4)['government_status']==='response_required','Italy either chamber confidence');
$jp=new ComparativeGovernment('world-test','JP','2026-11-25','old-government',['representatives'=>100]);
file32($jp,'JP','representatives',null,0);
ck32(vote32($jp,'JP','representatives','2026-11-25',51,30,19)['pending']['deadline']==='2026-12-05','Japan ten-day response');
$jp->advance('2026-12-06');ck32($jp->snapshot()['status']==='deadline_breached'&&$jp->snapshot()['breaches']===1,'Japanese deadline breach');
reject32(fn()=>settle32($jp,'JP','2026-12-06','dissolve',null),'late dissolution cannot satisfy Article 69');
$us=new ComparativeGovernment('world-test','US','2026-11-25','old-government',['house'=>100,'senate'=>50]);
reject32(fn()=>file32($us,'US','house',null),'US no ordinary censure');
file32($us,'US','house',null,0,'impeachment');
ck32(vote32($us,'US','house','2026-11-25',51,49,0)['government_status']==='senate_trial_pending','House impeachment not removal');
$r=vote32($us,'US','senate','2026-11-25',34,16,0,'senate-ballot');
ck32($r['government_status']==='removed_successor_pending'&&$r['threshold']===34,'Senate two thirds present');
$s=new InstitutionalSimulation('world-test','JP','2026-11-25');
$s->enableAccountability('old-government',['representatives'=>100]);
$s->advanceTo('2026-11-26');
ck32(InstitutionalSimulation::restore($s->snapshot())->hash()===$s->hash(),'nested government state replay');
foreach(['ES','GB','IT','JP'] as $cc)ck32(InstitutionalRulebook::hasRules($cc),'new reviewed rulebook '.$cc);
reject32(fn()=>new ComparativeGovernment('world-test','FR','2026-11-25','old-government',['assembly'=>100]),'France keeps dedicated procedure');
