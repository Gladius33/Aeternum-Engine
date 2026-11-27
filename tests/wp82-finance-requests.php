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


// WP114f-h: representative careers, pay reconciliation and every social family.
require_once __DIR__.'/../games/world-politics/src/SocialEconomy.php';
require_once __DIR__.'/../games/world-politics/src/FrenchPublicServices.php';
require_once __DIR__.'/../games/world-politics/src/FrenchDemography.php';
require_once __DIR__.'/../games/world-politics/src/FrenchMacroBaseline.php';
use Aeternum\WorldPolitics\{SocialEconomy as SE,FrenchMacroBaseline as MB,FrenchDemography as FD,FrenchPublicServices as FS};
$career=['age_months'=>720,'entry_age_months'=>216,'contributed_months'=>504,'reference_monthly_gross_minor'=>250000,'arduous'=>false];
ck82(!SE::pension($career,['age'=>65])['eligible']&&SE::pension($career,['age'=>60,'contribution_years'=>40,'entry_age_before'=>20])['eligible'],'WP114f pension age and long career qualification');
$career['entry_age_months']=252;$career['contributed_months']=468;
ck82(!SE::pension($career,['age'=>60,'contribution_years'=>40,'entry_age_before'=>20])['eligible'],'WP114f long career exclusion');
$career['age_months']=660;$career['entry_age_months']=216;$career['contributed_months']=444;$career['arduous']=true;
ck82(SE::pension($career,['age'=>60,'arduous_age'=>55])['eligible'],'WP114f arduous career threshold');
$pay=SE::payroll(150000);
ck82($pay['gross_minor']===$pay['net_minor']+$pay['employee_contribution_minor']&&$pay['employer_cost_minor']===$pay['gross_minor']+$pay['employer_contribution_minor'],'WP114g payroll reconciliation');
$base=MB::load(true);$pop=FD::load('2027-05-14',$base['initial_unemployment_bps'],true);$services=FS::load('2027-05-14',$pop,$base);
$results=[];
foreach(SP::definitions() as $id=>$definition){
    $policy=['effective_on'=>'2027-09-11'];$r=SE::calculate([$id],[$id=>$policy],'2027-09-11',$pop,$services,$base,null);
    ck82(count($r['measures'])===1&&$r['measures'][0]['target']===$definition['target']&&$r['daily_expense_delta_minor']===$r['measures'][0]['daily_expense_delta_minor'],'WP114f-h typed domain calculation '.$id);
    ck82($r['labour_force_proxy']===$r['employed_proxy']+$r['unemployed_proxy'],'WP114f-h employment accounting '.$id);$results[$id]=$r;
}
ck82($results['hist2022-jean-luc-melenchon-1']['pension_transfer_delta_minor']>0&&$results['hist2022-emmanuel-macron-1']['pension_transfer_delta_minor']<0,'WP114f age direction affects pension outlay');
ck82($results['hist2022-anne-hidalgo-1']['monthly_net_floor_minor']>$results['hist2022-yannick-jadot-1']['monthly_net_floor_minor'],'WP114g wage floor distinction');
ck82($results['hist2022-jean-luc-melenchon-2']['daily_expense_delta_minor']>$results['hist2022-nicolas-dupont-aignan-2']['daily_expense_delta_minor'],'WP114h differentiated employment guarantee');
ck82($results['hist2022-fabien-roussel-2']['weekly_hours']===32&&$results['hist2022-philippe-poutou-1']['measures'][0]['days_per_week']===4,'WP114h working time');
$oldSocial=['proposed_on'=>'2027-05-14','effective_on'=>'2027-09-11','authorized_on'=>'2027-09-10','instrument_sha'=>str_repeat('a',64)];
$newSocial=['proposed_on'=>'2027-10-01','effective_on'=>'2028-01-29','authorized_on'=>null,'instrument_sha'=>null];
$twoSocial=['hist2022-jean-luc-melenchon-1'=>$oldSocial,'hist2022-emmanuel-macron-1'=>$newSocial];
ck82(SP::effectiveFamilies($twoSocial,'2028-01-29')['pension']==='hist2022-jean-luc-melenchon-1','WP114j unapproved replacement cannot repeal');
$twoSocial['hist2022-emmanuel-macron-1']['authorized_on']='2028-01-28';
ck82(SP::effectiveFamilies($twoSocial,'2028-01-29')['pension']==='hist2022-emmanuel-macron-1','WP114j published replacement supersedes prior law');
ck82(PP::nextProposal(PP::compileProfile(['hist2022-emmanuel-macron-1']),'2027-10-01',['hist2022-jean-luc-melenchon-1'=>$oldSocial],true)['policy_id']==='hist2022-emmanuel-macron-1','WP114j replacement may be proposed without revoking previous law');


// WP114 lifecycle: neutral repeal, no premature effect, repeated law identity.
$reset114='social-repeal-pension';$pension114='hist2022-jean-luc-melenchon-1';
$resetProfile=PP::compileProfile([$reset114,'industry-active']);
ck82(strlen($resetProfile)<=24&&PP::socialIds($resetProfile)===[$reset114]&&PP::agenda($resetProfile)['industry']==='active','WP114j canonical mixed repeal programme');
no82(fn()=>PP::compileProfile([$reset114,$pension114]));
ck82(count(SP::objectives())===112&&count(SP::definitions())===24,'WP114j ten explicit repeal families preserve historical archive');
$pendingReset=PP::nextProposal($resetProfile,'2027-10-01',[$pension114=>$oldSocial],true);
ck82($pendingReset['policy_id']===$reset114,'WP114j explicit repeal proposed only with active prior reform');
$resetPolicy=['proposed_on'=>'2027-10-01','effective_on'=>$pendingReset['effective_day'],'authorized_on'=>null,'instrument_sha'=>null];
$cycle=[$pension114=>$oldSocial,$reset114=>$resetPolicy];
ck82(SP::effectiveFamilies($cycle,'2028-01-29')['pension']===$pension114,'WP114j repeal requires actual legal proof');
$cycle[$reset114]['authorized_on']='2028-01-28';$cycle[$reset114]['instrument_sha']=str_repeat('b',64);
ck82(SP::effectiveFamilies($cycle,'2028-01-29')['pension']===$reset114,'WP114j legal repeal supersedes pension law');
$reintroduced=PP::nextProposal(PP::compileProfile([$pension114]),'2028-02-01',$cycle,true);
ck82($reintroduced['policy_id']===$pension114.'-r2'&&PP::permits(PP::compileProfile([$pension114]),$reintroduced['policy_id']),'WP114j new immutable instance can reintroduce repealed law');
ck82(SP::legalText($pension114)['standard']!==SP::legalText($pension114.'-r2')['standard'],'WP114j distinct instrument digest');
$neutral=SE::calculate([$reset114],$cycle,'2028-01-29',$pop,$services,$base,null);
ck82($neutral['daily_expense_delta_minor']===0&&$neutral['daily_revenue_delta_minor']===0&&$neutral['pension_transfer_delta_minor']===0,'WP114j repeal returns reference scenario');
$catalogLifecycle=SP::policies($base);
ck82(SP::assumption($reset114,$catalogLifecycle,false)===null&&SP::assumption($reset114,$catalogLifecycle,true)['expense_delta']===0,'WP114j legacy world cannot accept unpinned lifecycle');
ck82(SP::assumption($pension114.'-r2',$catalogLifecycle,true)===$catalogLifecycle[$pension114],'WP114j repeat uses pinned assumptions');
$cycle[$pension114.'-r2']=['proposed_on'=>'2028-02-01','effective_on'=>$reintroduced['effective_day'],'authorized_on'=>null,'instrument_sha'=>null];
ck82(SP::nextInstance($pension114,'2028-02-02',$cycle)===null,'WP114j pending instance is not duplicated');


// WP114j: independently dated age-band employment observations and holdout,
// plus every historical measure's bounded parameter scenario.
require_once __DIR__.'/../games/world-politics/src/SocialCalibration.php';
use Aeternum\WorldPolitics\SocialCalibration as SC;
$cal114=SC::benchmark(SC::load());
ck82($cal114['observed_2025_employment_bps']['age_15_64']===6930&&$cal114['observed_2025_activity_bps']['age_15_64']===7510,'WP114j exact observed age scope');
ck82($cal114['train']===[2015,2021]&&$cal114['holdout']===[2022,2025]&&!$cal114['policy_causal_calibration_qualified'],'WP114j no holdout leakage or causal claim');
foreach($cal114['age_band_backtest'] as $series)ck82(count($series['holdout'])===4&&$series['trend_mae_bps']>=0,'WP114j four held-out years');
$sr114=SC::sensitivity($pop,$services,$base);
ck82(count($sr114['measures'])===24,'WP114j all historical objectives have scenario envelopes');
foreach($sr114['measures'] as $metrics)foreach($metrics as $v)ck82($v['min']<=$v['central']&&$v['central']<=$v['max'],'WP114j sensitivity contains central scenario');
no82(fn()=>SE::calculate([],[],'2027-09-11',$pop,$services,$base,null,['job_guarantee_takeup_bps'=>10001]));


require_once __DIR__.'/../games/world-politics/src/HistoricalProgrammeCoverage.php';
$historicalCoverage=\Aeternum\WorldPolitics\HistoricalProgrammeCoverage::report();
ck82(count($historicalCoverage['candidates'])===12&&$historicalCoverage['executable_archived_count']===24&&$historicalCoverage['additional_unmapped_count']===0&&$historicalCoverage['additional_executable_count']===47,'WP114i separate all twelve source inventories from executable historical measures');
ck82(!$historicalCoverage['wp114_closure_qualified'],'WP114i no false exhaustive coverage');
ck82(SC::load()['employment_bps'][0]===[2015,2830,7990,6060,6460]&&SC::load()['employment_bps'][2]===[2017,2900,8060,6230,6540],'WP114j exact verified INSEE training anchors');


// WP114 primary quantitative: exact source targets, extended masks,
// separately costed routes and legal identity for each primary measure.
use Aeternum\WorldPolitics\PrimaryManifestoPolicy as PM;
ck82(count(PM::definitions())===44,'WP114 44 independently reviewed primary routes');
$primaryMix=PP::compileProfile(['pf2022-eric-zemmour-3','pf2022-yannick-jadot-2','hist2022-emmanuel-macron-1','industry-active']);
ck82(strlen($primaryMix)<=40&&count(PP::socialIds($primaryMix))===3&&PP::agenda($primaryMix)['industry']==='active','WP114 extended mixed canonical mask');
no82(fn()=>PP::compileProfile(['pf2022-anne-hidalgo-4','pf2022-philippe-poutou-1']));
$pc=SP::policies($base);$primaryResults=[];
foreach(PM::definitions() as $id=>$def){
    $day=SP::effectiveDay($id,'2027-05-14');
    $profile=PP::compileProfile([$id]);
    ck82(PP::socialIds($profile)===[$id]&&PP::permits($profile,$id)&&isset($pc[$id]),'WP114 exact policy route '.$id);
    $r=SE::calculate([$id],[$id=>['effective_on'=>$day]],$day,$pop,$services,$base,null);
    ck82(count($r['measures'])===1&&$r['measures'][0]['target']===$def['target'],'WP114 primary calculation '.$id);
    $primaryResults[$id]=$r;
}
ck82($primaryResults['pf2022-valerie-pecresse-3']['daily_revenue_delta_minor']===-intdiv(1000000000000,365),'WP114 specified annual tax cut');
ck82($primaryResults['pf2022-yannick-jadot-4']['daily_revenue_delta_minor']===intdiv(1050000000000,365),'WP114 collection assumption disclosed');
ck82($primaryResults['pf2022-anne-hidalgo-4']['monthly_net_floor_minor']===SE::ASSUMPTIONS['reference_net_smic_minor']+20000,'WP114 nominal wage increment');
ck82($primaryResults['pf2022-emmanuel-macron-1']['pension_transfer_delta_minor']>0,'WP114 pension minimum has actual transfer');
$hires='pf2022-yannick-jadot-2';$staff=SE::calculate([$hires],[$hires=>['effective_on'=>'2027-09-11']],'2032-09-11',$pop,$services,$base,null);
ck82($staff['measures'][0]['planned_positions_proxy']===65000&&$staff['daily_expense_delta_minor']===intdiv(65000*62000*100,365),'WP114 completed staffing ramp');
$both=SE::calculate([$hires,'pf2022-fabien-roussel-1'],[$hires=>['effective_on'=>'2027-09-11'],'pf2022-fabien-roussel-1'=>['effective_on'=>'2027-09-11']],'2032-09-11',$pop,$services,$base,null);
ck82(array_sum(array_column($both['measures'],'planned_positions_proxy'))===500000,'WP114 overlapping public staffing targets not counted twice');
ck82(SP::legalText('pf2022-yannick-jadot-2')['standard']!==SP::legalText('pf2022-yannick-jadot-2-r2')['standard'],'WP114 primary legal instance proof');


// WP114 fiscal continuation: additional primary commitments and new-family
// repeal identity beyond native PHP integer mask capacity.
$large=PP::compileProfile(['pf2022-marine-le-pen-1','social-repeal-hospital-beds','industry-active']);
ck82(strlen($large)<=64&&PP::socialIds($large)===['pf2022-marine-le-pen-1','social-repeal-hospital-beds'],'WP114 arbitrary-width canonical mask');
no82(fn()=>PP::compileProfile(['pf2022-marine-le-pen-1','social-repeal-sme-procurement']));
ck82(SP::assumption('social-repeal-hospital-beds',$pc,true)['expense_delta']===0,'WP114 repeal of additional family');
ck82($primaryResults['pf2022-nicolas-dupont-aignan-1']['daily_revenue_delta_minor']===-intdiv(600000000000,365),'WP114 reinvested profits tax base');
ck82($primaryResults['pf2022-jean-lassalle-1']['daily_revenue_delta_minor']===-intdiv(580000000000,365),'WP114 fuel VAT difference');
ck82($primaryResults['pf2022-valerie-pecresse-2']['measures'][0]['weekly_activity_hours_proxy']===14250000,'WP114 RSA activity hours');
$cap='pf2022-eric-zemmour-4';$expired=SE::calculate([$cap],[$cap=>['effective_on'=>'2027-09-11']],'2028-09-11',$pop,$services,$base,null);
ck82($primaryResults[$cap]['daily_expense_delta_minor']>0&&$expired['daily_expense_delta_minor']===0,'WP114 temporary fuel compensation expires');
ck82($primaryResults['pf2022-philippe-poutou-3']['daily_expense_delta_minor']>0,'WP114 transport fare compensation');
ck82($primaryResults['pf2022-marine-le-pen-1']['daily_expense_delta_minor']===intdiv(5000000000,365),'WP114 procurement redirected not double spent');


// WP114 structural economic measures: target dates, phased flows and
// reference-reset identities are distinct from guaranteed attainment.
$energy='pf2022-jean-luc-melenchon-4';$local='pf2022-marine-le-pen-3';
$startEnergy=SP::effectiveDay($energy,'2027-05-14');
ck82($startEnergy==='2027-09-11','WP114 renewable target date is not commencement date');
$lastEnergy=SE::calculate([$energy],[$energy=>['effective_on'=>$startEnergy]],'2050-12-31',$pop,$services,$base,null);
ck82($lastEnergy['measures'][0]['planned_renewable_share_bps']===10000&&!$lastEnergy['measures'][0]['target_achievement_certified'],'WP114 dated renewable trajectory not certified physical achievement');
ck82($primaryResults[$energy]['daily_expense_delta_minor']>0&&$primaryResults[$energy]['measures'][0]['planned_renewable_share_bps']<10000,'WP114 upfront transition spending before 2050');
$endLocal=SE::calculate([$local],[$local=>['effective_on'=>'2027-09-11']],'2032-09-11',$pop,$services,$base,null);
ck82($endLocal['measures'][0]['annual_supply_chain_shift_eur']===4000000000&&$endLocal['production_delta_bps']>0&&!$endLocal['measures'][0]['tariff_or_origin_restriction_implemented'],'WP114 short chain real output and fiscal support');
$repealLocal=PP::compileProfile(['social-repeal-local-supply','industry-active']);
ck82(PP::socialIds($repealLocal)===['social-repeal-local-supply']&&SP::assumption('social-repeal-local-supply',$pc,true)['expense_delta']===0,'WP114 bit 127 repeal and zero proxy');


// WP114 Article 13 treaty rule: notice is a separate durable action,
// requires a trusted service and never immediately cancels Article 5.
require_once __DIR__.'/../games/world-politics/src/OrganizationSimulation.php';
use Aeternum\WorldPolitics\{NatoWithdrawal as NW,OrganizationSimulation as OS,DiplomaticPrincipal as DP,GlobalReferenceBundle as GRB};
require_once __DIR__.'/../games/world-politics/src/GlobalReferenceBundle.php';
$natoId=NW::POLICY;$nh=str_repeat('a',64);$dh=str_repeat('b',64);
$nato=NW::notice('2027-09-12',$natoId,'2027-09-11',$nh,$dh);
ck82($nato['effective_on']==='2028-09-12'&&$nato['article5_treaty_applies'],'WP114 Article 13 calendar year including leap day');
ck82(NW::advance($nato,'2028-09-11')['status']==='notice_deposited'&&!NW::advance($nato,'2028-09-12')['article5_treaty_applies'],'WP114 no premature treaty termination');
no82(fn()=>NW::notice('2027-09-10',$natoId,'2027-09-11',$nh,$dh));
$badNato=$nato;$badNato['effective_on']='2027-09-13';no82(fn()=>NW::restore($badNato));
$actorsNato=GRB::published()->actors;
$orgNato=new OS('world-test','2027-09-12',$actorsNato,[]);
$serviceNato=new class implements DP{
    public function actorId():string{return 'clock-test';}
    public function allows(string $world,string $actor,string $ability):bool{return $world==='world-test'&&$actor==='clock-test'&&$ability==='organizations.clock';}
};
$humanNato=new class implements DP{
    public function actorId():string{return 'human-test';}
    public function allows(string $world,string $actor,string $ability):bool{return false;}
};
no82(fn()=>$orgNato->natoNotice($humanNato,$natoId,'2027-09-11',$nh,$dh));
ck82(!isset($orgNato->snapshot()['nato_withdrawal']),'WP114 unauthorized notice does not mutate state');
$orgNato->natoNotice($serviceNato,$natoId,'2027-09-11',$nh,$dh);
no82(fn()=>$orgNato->natoNotice($serviceNato,$natoId,'2027-09-11',$nh,$dh));
$orgNato->advanceTo($serviceNato,'2028-09-11','nato-test-before');$orgNato->advanceTo($serviceNato,'2028-09-12','nato-test-effective');
ck82($orgNato->snapshot()['nato_withdrawal']['status']==='withdrawn'&&OS::fromSnapshot($orgNato->snapshot(),$actorsNato,[])->hash()===$orgNato->hash(),'WP114 durable treaty receipt and restore');


// WP114 actual constitutional duration change, not an executive extension.
// A separately replayed Article 89 proof controls the next investiture only.
use Aeternum\WorldPolitics\{ConstitutionalRevision as CR,PresidentialMandate as PRES};
require_once __DIR__.'/../games/world-politics/src/PresidentialMandate.php';
$durationId='pf2022-jean-lassalle-4';
$durationProfile=PP::compileProfile([$durationId,'industry-active']);
ck82(PP::agenda($durationProfile)['revision']==='presidential-seven-year-referendum'&&PP::agenda($durationProfile)['ratification']==='referendum','WP114 duration agenda exact route');
$wrongDuration=PP::agenda($durationProfile);$wrongDuration['ratification']='congress';
no82(fn()=>PP::encodeAgenda($wrongDuration));
$revisionDuration=new CR('world-test','2027-05-14');
$draftDuration=$revisionDuration->draft('seven-years','presidential-seven-year-referendum');
$shaDuration=$draftDuration['text_sha'];
ck82(CR::termLengthProof($revisionDuration->snapshot(),'world-test','2027-05-14')===null,'WP114 draft does not extend mandate');
$seq=0;
$certDuration=function(string $kind,array $data)use($revisionDuration,&$seq){
    return $revisionDuration->certify(['world_id'=>'world-test','country'=>'FR','day'=>'2027-05-14','evidence_id'=>'duration-e-'.(++$seq),'revision_id'=>'seven-years','kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);
};
$certDuration('file',['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>true,'republican_form_preserved'=>true]);
foreach(['assembly','senate'] as $ch)$certDuration('chamber_vote',['chamber'=>$ch,'text_sha'=>$shaDuration,'yes'=>200,'no'=>100,'abstain'=>0,'blank_invalid'=>0]);
no82(fn()=>$certDuration('route',['route'=>'congress','presidential_decision'=>true]));
$certDuration('route',['route'=>'referendum','presidential_decision'=>true]);
$certDuration('ratify',['route'=>'referendum','text_sha'=>$shaDuration,'yes'=>60000,'no'=>40000,'abstain'=>0,'blank_invalid'=>0]);
ck82(CR::termLengthProof($revisionDuration->snapshot(),'world-test','2027-05-14')===null,'WP114 adopted but unpublished text cannot change date');
$certDuration('promulgate',['text_sha'=>$shaDuration,'published_on'=>'2027-05-14']);
$proofDuration=CR::termLengthProof($revisionDuration->snapshot(),'world-test','2027-05-14');
ck82($proofDuration['next_term_years']===7&&CR::termLimitProof($revisionDuration->snapshot(),'world-test','2027-05-14')===null,'WP114 duration and term limit orthogonal');
$presDuration=PRES::initial('world-test','2027-05-14',PRES::investitureFixture());
$presDuration=PRES::advance($presDuration,'2027-05-15',900,null,$proofDuration);
ck82($presDuration['term_end']==='2032-05-14'&&$presDuration['next_term_years']===7,'WP114 incumbent term not retroactively extended');
for($day='2027-05-16';$day<='2032-05-14';$day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d'))
    $presDuration=PRES::advance($presDuration,$day,900,null,$proofDuration);
ck82($presDuration['term_start']==='2032-05-14'&&$presDuration['term_end']==='2039-05-14'&&$presDuration['consecutive_terms']===2&&!$presDuration['eligible_for_re_election'],'WP114 seven-year next mandate with unchanged consecutive term limit');


// WP114 collective ownership is a separately authorized, cash-funded
// financial settlement, not a voluntary purchase or expense duplicate.
require_once __DIR__.'/../games/world-politics/src/CollectiveOwnership.php';
use Aeternum\WorldPolitics\{CollectiveOwnership as CO,PublicEquityLedger as PE};
$co=CO::POLICY;$cfgCo=['fixture-large'=>['weight'=>220,'resilience'=>150],'fixture-small'=>['weight'=>100,'resilience'=>150]];
$coLaw=['authorized_on'=>'2027-09-10','effective_on'=>'2027-09-11','instrument_sha'=>str_repeat('c',64)];
$eqCo=PE::initial('2027-09-11');
no82(fn()=>CO::apply($eqCo,$cfgCo,[$co],[],'2027-09-11',100000000000,true));
$eqCo=CO::apply($eqCo,$cfgCo,[$co],[$co=>$coLaw],'2027-09-11',100000000000,true);
ck82(count($eqCo['collective']['orders'])===1&&$eqCo['positions']===[],'WP114 no transfer on authorization day');
for($day='2027-09-12';$day<='2027-10-11';$day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d')){
    $eqCo=PE::advance($eqCo,$day,null,$cfgCo,0,false);
    $eqCo=CO::apply($eqCo,$cfgCo,[$co],[$co=>$coLaw],$day,0,false);
}
ck82($eqCo['positions']===[]&&array_values($eqCo['collective']['orders'])[0]['waiting_for_funds'],'WP114 no transfer when ordinary payments in arrears');
$eqCo=PE::advance($eqCo,'2027-10-12',null,$cfgCo,100000000000,true);
$eqCo=CO::apply($eqCo,$cfgCo,[$co],[$co=>$coLaw],'2027-10-12',100000000000,true);
ck82($eqCo['positions']['fixture-large']['stake_bps']===10000&&$eqCo['paid_today_minor']>0&&$eqCo['financial_assets_at_cost_minor']===$eqCo['paid_today_minor'],'WP114 compensated stock and cash reconcile');
ck82($eqCo['collective']['governance']['fixture-large']['worker_board_bps']===5000&&!isset($eqCo['positions']['fixture-small']),'WP114 actual synthetic collective governance and scope');
$eqCo=PE::advance($eqCo,'2027-10-13',null,$cfgCo,100000000000,true);
$eqCo=CO::apply($eqCo,$cfgCo,[],[$co=>$coLaw],'2027-10-13',100000000000,true);
ck82($eqCo['paid_today_minor']===0&&$eqCo['positions']['fixture-large']['stake_bps']===10000,'WP114 abrogation does not confiscate previous acquired title');


// WP114 civic reforms: legal publication, independent signature threshold,
// separate binding referendum and prospective registration only.
use Aeternum\WorldPolitics\{CivicReform as CV,FrenchRecurringElections as FRE,ElectoralCycle as EC};
require_once __DIR__.'/../games/world-politics/src/CivicReform.php';
$ricId='pf2022-nicolas-dupont-aignan-4';$franchiseId='pf2022-nathalie-arthaud-4';
ck82(PP::agenda(PP::compileProfile([$ricId]))['revision']==='citizen-initiative-500k','WP114 citizen initiative objective');
ck82(PP::agenda(PP::compileProfile([$franchiseId]))['revision']==='resident-worker-franchise','WP114 franchise objective');
no82(fn()=>PP::compileProfile([$ricId,$franchiseId]));
function civicProof82(string $template,string $type):array{
    $r=new CR('world-test','2027-05-14');$d=$r->draft('civic-reform',$template);$sha=$d['text_sha'];$n=0;
    $e=function(string $kind,array $data)use($r,&$n){return $r->certify(['world_id'=>'world-test','country'=>'FR','day'=>'2027-05-14','evidence_id'=>'civic-e-'.(++$n),'revision_id'=>'civic-reform','kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);};
    ck82(CV::proof($r->snapshot(),'world-test','2027-05-14',$type)===null,'WP114 draft alone grants no civic rights');
    $e('file',['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>true,'republican_form_preserved'=>true]);
    foreach(['assembly','senate'] as $ch)$e('chamber_vote',['chamber'=>$ch,'text_sha'=>$sha,'yes'=>200,'no'=>100,'abstain'=>0,'blank_invalid'=>0]);
    no82(fn()=>$e('route',['route'=>'congress','presidential_decision'=>true]));
    $e('route',['route'=>'referendum','presidential_decision'=>true]);$e('ratify',['route'=>'referendum','text_sha'=>$sha,'yes'=>60000,'no'=>40000,'abstain'=>0,'blank_invalid'=>0]);
    ck82(CV::proof($r->snapshot(),'world-test','2027-05-14',$type)===null,'WP114 adopted but unpublished reform ineffective');
    $e('promulgate',['text_sha'=>$sha,'published_on'=>'2027-05-14']);
    return CV::proof($r->snapshot(),'world-test','2027-05-14',$type);
}
$ricProof=civicProof82('citizen-initiative-500k','initiative');
$civic=CV::initial('world-test',$ricProof);$firstPoll=null;
for($n=1;$n<=650;$n++){
    $day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$n.' days')->format('Y-m-d');
    $o=['world_id'=>'world-test','day'=>$civic['day'],'approval'=>900,'sha'=>str_repeat('a',64)];
    $civic=CV::advance($civic,$day,$o);
    if($civic['poll_day']!==null&&$firstPoll===null){$firstPoll=$civic['poll_day'];ck82($civic['signatures']===500000&&$firstPoll>$day,'WP114 only threshold schedules vote');}
}
ck82($civic['status']==='effective'&&$civic['ballot']['yes']>$civic['ballot']['no']&&count($civic['reports'])===1,'WP114 independent binding vote creates annual reporting obligation');
ck82(CV::restore($civic)===$civic,'WP114 complete civic history replay');
$badCivic=$civic;$badCivic['signatures']++;no82(fn()=>CV::restore($badCivic));
$frProof=civicProof82('resident-worker-franchise','franchise');
$frPres=PRES::initial('world-test','2027-05-14',PRES::investitureFixture());
$pollFr=$frPres['first_round_day'];
for($day='2027-05-15';$day<=$pollFr;$day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d'))
    $frPres=PRES::advance($frPres,$day,800,null,null,$frProof);
ck82($frPres['first_result']['registered']===105000&&$frPres['franchise_for_cycle'],'WP114 presidential electorate expanded by fictional 5 percent');
$normalFr=FRE::early('2028-01-01');$newFr=FRE::early('2028-01-01',true);
$eFr=new EC('world-test','FR','2028-01-01',$newFr);
ck82(FRE::matches($eFr->snapshot(),true)&&!FRE::matches($eFr->snapshot(),false),'WP114 prospective assembly electorate canonical');
ck82(array_sum(array_column($newFr['districts'],'registered'))>array_sum(array_column($normalFr['districts'],'registered')),'WP114 actual expanded assembly registers');
