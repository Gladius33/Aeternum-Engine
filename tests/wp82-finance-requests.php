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

$bad=$s;unset($bad['finance_requests']['budget-request']);no82(fn()=>I::restore($bad));
$bad=$s;$bad['finance_requests']['budget-request']['reason']='invented';no82(fn()=>I::restore($bad));

$y=new I('world-test','FR','2027-05-14');$y->enableElectoral(P::publishedElectionConfig('2027-05-14'));$y->enablePoliticalAI();$y->enableRevision();$y->enableLegislation(true,true,true,true,true,true,true,2028,true,true);$y->enableContinuityLot();$y->enableCabinetSenateLot();$y->enableFinanceRequests();
$cp=['policy_id'=>'corrective-2027-health-increase'];
no82(fn()=> $y->decide('scenario_president_requester','request_annual_budget',$cp,'wrong-kind'));
$y->decide('scenario_president_requester','request_corrective_budget',$cp,'correction-request');
for($n=1;$n<=32;$n++){$day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$n.' days')->format('Y-m-d');$y->advanceTo($day);ck82(I::restore($y->snapshot())->hash()===$y->hash(),'WP99 daily replay '.$n);}
ck82(in_array($y->snapshot()['finance_requests']['correction-request']['status'],['published','rejected'],true),'WP99 ordinary corrective budget route');


// WP114 domain planner: bounded programme, exact identifiers, independent votes.
require_once __DIR__.'/../games/world-politics/src/PresidentialProgramme.php';
use Aeternum\WorldPolitics\PresidentialProgramme as PP;
ck82(PP::nextProposal('manual','2027-05-14',[])===null,'WP114 manual opt-out');
ck82(PP::nextProposal('governance','2027-05-14',[])===['policy_id'=>'education-reform','effective_day'=>'2027-09-11'],'WP114 sufficient ordinary legislative lead');
ck82(PP::nextProposal('governance','2027-05-14',['education-reform'=>true,'administration-report'=>true])===['policy_id'=>'budget-2028-defense-maintain','effective_day'=>'2028-01-01'],'WP114 annual continuity proposal');
ck82(PP::nextProposal('governance-reduce','2027-05-14',['education-reform'=>true,'administration-report'=>true,'budget-2028-defense-maintain'=>true])===null,'WP114 cannot create rival annual budget');
ck82(PP::nextProposal('governance-increase','2027-12-01',['education-reform'=>true,'administration-report'=>true])===null,'WP114 no late budget request');
$x114=new I('world-test','FR','2027-05-14');$x114->enableElectoral(P::publishedElectionConfig('2027-05-14'));$x114->enablePoliticalAI();$x114->enableRevision();$x114->enableLegislation(true,true,true,true,true,true,true,2028,true);$x114->enableContinuityLot();$x114->enableCabinetSenateLot();$x114->enableFinanceRequests();
$a114=['programme'=>'governance','decision_day'=>'2027-05-14','policy_id'=>'education-reform','effective_day'=>'2027-09-11'];
$p114=['proposed_on'=>'2027-05-14','effective_on'=>'2027-09-11','authorized_on'=>null,'instrument_sha'=>null];
ck82(PP::advanceOne($x114,$a114,$p114)===null,'WP114 cannot execute same-day uncommitted proposal');
$steps114=[];
for($n=1;$n<=120;$n++){
    $day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$n.' days')->format('Y-m-d');$x114->advanceTo($day);
    $step=PP::advanceOne($x114,$a114,$p114);
    if($step!==null)$steps114[]=$step['stage'];
    if($n%10===0||$step!==null)ck82(I::restore($x114->snapshot())->hash()===$x114->hash(),'WP114 exact legislative replay '.$n);
}
$b114=array_values($x114->snapshot()['legislation']['bills'])[0];
ck82($steps114[0]==='bill_drafted'&&$steps114[1]==='file'&&in_array('reading-assembly',$steps114,true),'WP114 actual ordinary procedure rather than instant enactment');
ck82($b114['last_votes']['assembly']['day']>=$b114['filed_on']&&$b114['readings']['assembly']===1,'WP114 no manufactured repeated vote');
if($b114['status']==='published'){
    ck82($b114['effect_on']==='2027-09-11'&&$b114['review']==='cleared'&&$b114['last_votes']['assembly']['vote']['passed']&&$b114['last_votes']['senate']['vote']['passed'],'WP114 publication requires both votes and reviewed text');
}else ck82($b114['effect_on']===null,'WP114 failed route grants no implementation date');
$bad114=$p114;$bad114['effective_on']='2027-09-12';
no82(fn()=>PP::advanceOne($x114,$a114,$bad114));
