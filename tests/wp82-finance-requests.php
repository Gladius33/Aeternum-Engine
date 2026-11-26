<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{InstitutionalSimulation as I,ParliamentaryLifecycle as P,FinanceBudget as F};
function ck82(bool $v,string $m):void{if(!$v)throw new RuntimeException('WP82 FAIL '.$m);echo "PASS WP82: $m\n";}
function no82(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Unexpected acceptance');}
$x=new I('world-test','FR','2027-05-14');$x->enableElectoral(P::publishedElectionConfig('2027-05-14'));$x->enablePoliticalAI();$x->enableRevision();$x->enableLegislation(true,true,true,true,true,true,true,2028,true);$x->enableContinuityLot();$x->enableCabinetSenateLot();$x->enableFinanceRequests();
$payload=['policy_id'=>'budget-2028-defense-maintain'];
no82(fn()=> $x->decide('president','request_annual_budget',$payload,'unauthorized'));
$x->decide('scenario_president_requester','request_annual_budget',$payload,'budget-request');
no82(fn()=> $x->decide('scenario_president_requester','request_annual_budget',$payload,'duplicate'));
for($n=1;$n<=32;$n++){
    $day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$n.' days')->format('Y-m-d');$x->advanceTo($day);
    $s=$x->snapshot();ck82(I::restore($s)->hash()===$x->hash(),'daily replay '.$n);
    if($n===1)ck82($s['finance_requests']['budget-request']['status']==='filed','no instant law');
}
$s=$x->snapshot();$r=$s['finance_requests']['budget-request'];ck82(in_array($r['status'],['published','rejected'],true),'ordinary process reaches parliamentary outcome');
if($r['status']==='published')ck82(count($r['steps'])===7&&count($s['legislation']['finance']['bills'])===1,'seven distinct certified steps');
$bad=$s;$bad['finance_requests']['budget-request']['steps'][0]['evidence_id']='fake';no82(fn()=>I::restore($bad));
