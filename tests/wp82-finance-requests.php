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


// WP114b custom structured agenda and deterministic legal routing.
$agenda114=['education'=>true,'administration'=>false,'defense'=>'reduce','health'=>'increase','ukraine_aid'=>'stop'];
$code114=PP::encodeAgenda($agenda114);
ck82(PP::agenda($code114)===$agenda114&&strlen($code114)<=24,'WP114b canonical custom agenda');
ck82(PP::encodeAgenda(array_reverse($agenda114,true))===$code114,'WP114b order independent encoding');
no82(fn()=>PP::encodeAgenda($agenda114+['unexpected'=>true]));
no82(fn()=>PP::encodeAgenda(array_replace($agenda114,['education'=>'true'])));
no82(fn()=>PP::encodeAgenda(array_replace($agenda114,['health'=>'arbitrary'])));
ck82(PP::aidAction($code114)==='ukraine-aid-stop','WP114b exact aid route');
ck82(PP::nextProposal($code114,'2027-05-14',[])['policy_id']==='education-reform','WP114b education selected');
ck82(PP::nextProposal($code114,'2027-05-14',['education-reform'=>true])===['policy_id'=>'corrective-2027-health-increase','effective_day'=>'2027-10-01'],'WP114b corrective budget route');
ck82(PP::nextProposal($code114,'2027-05-14',['education-reform'=>true,'corrective-2027-health-reduce'=>true])===['policy_id'=>'budget-2028-defense-reduce','effective_day'=>'2028-01-01'],'WP114b no competing corrective bill');
ck82(!PP::permits($code114,'administration-report')&&!PP::permits($code114,'corrective-2027-health-reduce'),'WP114b excluded and contrary policy rejected');
$ac114=['programme'=>$code114,'decision_day'=>'2027-05-14','policy_id'=>'corrective-2027-health-increase','effective_day'=>'2027-10-01'];
ck82(PP::validAction($ac114)&&!PP::validAction(array_replace($ac114,['effective_day'=>'2027-10-02'])),'WP114b exact effective date');
$z114=new I('world-test','FR','2027-05-14');$z114->enableElectoral(P::publishedElectionConfig('2027-05-14'));$z114->enablePoliticalAI();$z114->enableRevision();$z114->enableLegislation(true,true,true,true,true,true,true,2028,true,true);$z114->enableContinuityLot();$z114->enableCabinetSenateLot();$z114->enableFinanceRequests();
$z114->advanceTo('2027-05-15');
$step114=PP::advanceOne($z114,$ac114,['proposed_on'=>'2027-05-14','effective_on'=>'2027-10-01','authorized_on'=>null,'instrument_sha'=>null]);
ck82($step114!==null&&$step114['stage']==='budget_requested','WP114b submits actual corrective request');
for($n=2;$n<=34;$n++)$z114->advanceTo((new DateTimeImmutable('2027-05-14'))->modify('+'.$n.' days')->format('Y-m-d'));
$req114=array_values($z114->snapshot()['finance_requests'])[0];
ck82(in_array($req114['status'],['published','rejected'],true)&&I::restore($z114->snapshot())->hash()===$z114->hash(),'WP114b independent corrective process and replay');


// WP114c: extended objective grammar, legacy decoding and Article 89 request.
$ext114=$agenda114+['cabinet'=>'social','revision'=>'environment-report','ratification'=>'referendum','industry'=>'active','commitments'=>'request_deferral'];
$extCode114=PP::encodeAgenda($ext114);
ck82(PP::agenda($extCode114)===$ext114&&strlen($extCode114)<=24,'WP114c canonical extended agenda');
ck82(PP::agenda($code114)===$agenda114,'WP114c original agenda remains replayable');
ck82(PP::industryAction($extCode114,'not_started')==='start-industrial-pilot'&&PP::industryAction($extCode114,'active')===null,'WP114c industrial transition matches current state');
ck82(PP::commitmentAction($extCode114)==='request_deferral','WP114c negotiated commitment objective');
no82(fn()=>PP::encodeAgenda(array_replace($ext114,['cabinet'=>'unknown'])));
no82(fn()=>PP::encodeAgenda(array_replace($ext114,['revision'=>'none'])));
$x114c=new I('world-test','FR','2027-05-14');$x114c->enableElectoral(P::publishedElectionConfig('2027-05-14'));$x114c->enablePoliticalAI();$x114c->enableRevision();$x114c->enableLegislation(true,true,true,true,true,true,true,2028,true,true);$x114c->enableContinuityLot();$x114c->enableCabinetSenateLot();$x114c->enableFinanceRequests();
$st114c=PP::institutionalObjective($x114c,$extCode114,'2027-05-14');
ck82($st114c!==null&&$st114c['stage']==='revision_requested','WP114c opens real revision request, not an instant constitutional change');
$s114c=$x114c->snapshot();
ck82($s114c['revision']['constitution_version']===0&&count($s114c['cabinet_requests'])===0,'WP114c no fabricated cabinet vacancy and no direct constitutional effect');
ck82(PP::institutionalObjective($x114c,$extCode114,'2027-05-14')===null,'WP114c no duplicated constitutional requests');
ck82(I::restore($s114c)->hash()===$x114c->hash(),'WP114c institutional request replay');


// WP114d: exact reviewed IDs, conflict rejection, and actual cabinet succession.
$catalog114=PP::measureCatalog();
ck82(count($catalog114)===25,'WP114d complete reviewed objective catalogue');
$ids114=['education-reform','annual-defense-increase','corrective-health-reduce','ukraine-aid-stop','cabinet-social','revision-environment-report-referendum','industry-active','commitment-request-deferral'];
$compiled114=PP::compileMeasures($ids114);
ck82($compiled114['education']&&$compiled114['defense']==='increase'&&$compiled114['health']==='reduce'&&$compiled114['ratification']==='referendum','WP114d exact ID compilation');
ck82(PP::encodeAgenda($compiled114)===PP::encodeAgenda(PP::compileMeasures(array_reverse($ids114))),'WP114d order independent compilation');
no82(fn()=>PP::compileMeasures(['annual-defense-increase','annual-defense-reduce']));
no82(fn()=>PP::compileMeasures(['education-reform','education-reform']));
no82(fn()=>PP::compileMeasures(['hist2022-emmanuel-macron-1']));
ck82(PP::measureSupport('hist2022-unknown-1')['status']==='no_reviewed_mapping','WP114d unknown historical promise explicitly reported');
ck82(PP::measureSupport('budget-2028-defense-increase')['status']==='dated_policy_requires_explicit_timing','WP114d dated policy cannot silently become recurring policy');
ck82(PP::measureSupport('corrective-2027-health-reduce')['status']==='dated_policy_requires_explicit_timing','WP114d dated corrective policy preserved');
$x114d=new I('world-test','FR','2027-05-14');$x114d->enableElectoral(P::publishedElectionConfig('2027-05-14'));$x114d->enablePoliticalAI();$x114d->enableRevision();$x114d->enableLegislation(true,true,true,true,true,true,true,2028,true,true);$x114d->enableContinuityLot();$x114d->enableCabinetSenateLot();$x114d->enableFinanceRequests();
$profile114d=PP::encodeAgenda(PP::compileMeasures(['cabinet-social']));
ck82(PP::institutionalObjective($x114d,$profile114d,'2027-05-14')===null,'WP114d no removal of current government');
$ev114d=['world_id'=>'world-test','country'=>'FR','day'=>'2027-05-14','evidence_id'=>'certified-resignation','bill_id'=>null,'kind'=>'assembly_cabinet_resign','procedure_certified'=>true,
    'data'=>['at'=>'2027-05-14T12:00:00Z','prime_minister'=>'pm-initial','resignation_id'=>'resignation-wp114d','resignation_presented'=>true]];
$x114d->legislation('legislation_clerk','legislation_evidence',$ev114d,'record-resignation');
$req114d=PP::institutionalObjective($x114d,$profile114d,'2027-05-14');
ck82($req114d!==null&&$req114d['stage']==='cabinet_requested','WP114d real vacancy triggers nomination');
$x114d->advanceTo('2027-05-15');
ck82($x114d->snapshot()['legislation']['responsibility']['cabinet']['prime_minister']==='pm-social','WP114d certified nomination becomes appointment');
for($n=1;$n<=8;$n++){
    $day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$n.' days')->format('Y-m-d');
    if($n>1)$x114d->advanceTo($day);PP::institutionalObjective($x114d,$profile114d,'2027-05-14');
}
$cab114d=$x114d->snapshot()['legislation']['responsibility']['cabinet'];
ck82(count(array_filter($cab114d['ministers']))===7&&$cab114d['ministers']['education']==='social-education','WP114d all vacant portfolios filled through joint appointment');
ck82(I::restore($x114d->snapshot())->hash()===$x114d->hash(),'WP114d government succession deterministic replay');


// WP114e: all 24 archived proposals have exact, independently typed mappings.
use Aeternum\WorldPolitics\SocialProgramme as SP;
$historical114=json_decode(file_get_contents(__DIR__.'/../games/world-politics/data/2026-11-27-fr-candidates-2022-v2.json'),true,64,JSON_THROW_ON_ERROR);
$all114=[];
foreach($historical114['candidates'] as $candidate114){
    $ids=array_column($candidate114['programme'],'id');$all114=[...$all114,...$ids];
    $profile=PP::compileProfile($ids);
    ck82(PP::socialIds($profile)===$ids,'WP114e historical programme compiled '.$candidate114['id']);
    foreach($candidate114['programme'] as $m)ck82(SP::definitions()[$m['id']]['domain']===$m['domain']&&PP::measureSupport($m['id'])['status']==='historical_social_proxy','WP114e exact source mapping '.$m['id']);
}
ck82(count($all114)===24&&array_keys(SP::definitions())===$all114,'WP114e complete immutable archived measure set');
$mixed114=PP::compileProfile(['hist2022-marine-le-pen-1','hist2022-marine-le-pen-2','industry-active','ukraine-aid-stop']);
ck82(strlen($mixed114)<=24&&PP::agenda($mixed114)['industry']==='active'&&PP::aidAction($mixed114)==='ukraine-aid-stop'&&count(PP::socialIds($mixed114))===2,'WP114e mixed agenda roundtrip');
no82(fn()=>PP::compileProfile(['hist2022-emmanuel-macron-1','hist2022-jean-luc-melenchon-1']));
ck82(SP::effectiveDay('hist2022-valerie-pecresse-1','2027-05-14')==='2030-01-01','WP114e no premature dated retirement target');
ck82(SP::phaseBps('hist2022-emmanuel-macron-1',0)===2000&&SP::phaseBps('hist2022-emmanuel-macron-1',1460)===10000,'WP114e progressive target fiscal phase');
$defs114=SP::policies(['daily_tax_base'=>100000000,'daily_social_benefits'=>100000000]);
ck82($defs114['hist2022-jean-luc-melenchon-1']['expense_delta']>0&&$defs114['hist2022-emmanuel-macron-1']['expense_delta']<0,'WP114e distinct pension fiscal scenario directions');
ck82(SP::wagePremium(['hist2022-anne-hidalgo-1'])>SP::wagePremium(['hist2022-yannick-jadot-1']),'WP114e different wage targets feed macro proxy');
$x114e=new I('world-test','FR','2027-05-14');$x114e->enableElectoral(P::publishedElectionConfig('2027-05-14'));$x114e->enablePoliticalAI();$x114e->enableRevision();$x114e->enableLegislation(true,true,true,true,true,true,true,2028,true,true);$x114e->enableContinuityLot();$x114e->enableCabinetSenateLot();$x114e->enableFinanceRequests();$x114e->enableNationalService();$x114e->enableSocialProgramme();
$histid114='hist2022-anne-hidalgo-1';$prof114=PP::compileProfile([$histid114]);$next114=PP::nextProposal($prof114,'2027-05-14',[]);
$aa114=['programme'=>$prof114,'decision_day'=>'2027-05-14',...$next114];$pp114=['proposed_on'=>'2027-05-14','effective_on'=>$next114['effective_day'],'authorized_on'=>null,'instrument_sha'=>null];
$x114e->advanceTo('2027-05-15');$s114e=PP::advanceOne($x114e,$aa114,$pp114);
ck82($s114e!==null&&$s114e['stage']==='bill_drafted','WP114e actual independent historical social bill');
$x114e->advanceTo('2027-05-16');$s114e=PP::advanceOne($x114e,$aa114,$pp114);
ck82($s114e!==null&&$s114e['stage']==='file','WP114e competent government filing');
for($n=3;$n<=120;$n++){
    $day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$n.' days')->format('Y-m-d');$x114e->advanceTo($day);PP::advanceOne($x114e,$aa114,$pp114);
}
$bill114e=array_values($x114e->snapshot()['legislation']['bills'])[0];
ck82($bill114e['policy_id']===$histid114&&$bill114e['readings']['assembly']===1,'WP114e ordinary parliamentary vote on exact historical policy');
ck82(I::restore($x114e->snapshot())->hash()===$x114e->hash(),'WP114e deterministic legal replay');
if($bill114e['status']==='published')ck82($bill114e['review']==='cleared'&&$bill114e['effect_on']===$next114['effective_day'],'WP114e exact legal activation date');
else ck82($bill114e['effect_on']===null,'WP114e blocked policy not enacted');
