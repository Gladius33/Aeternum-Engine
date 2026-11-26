<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
require_once __DIR__.'/../games/world-politics/src/DailyLegalActivation.php';
use Aeternum\WorldPolitics\{BicameralLegislation as B,FinanceBudget as F,DailyLegalActivation as A};
function ck71(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP71 FAIL '.$s);echo "PASS WP71: $s\n";}
function no71(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected denial');}
function ev71(B $l,string $k,array $d,string $id):array{return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>null,'kind'=>$k,'procedure_certified'=>true,'data'=>$d]);}
$l=new B('world-test','2027-05-14');$l->enableResponsibility();$l->enableAssembly();$l->enableLifecycle();$l->enableCabinet();$l->enableExecutive();$l->enableFinance(2028);
$policy='budget-2028-defense-increase';$f=['policy_id'=>$policy,'prime_minister'=>'pm-initial','finance_minister'=>'civic-economy','government_project_certified'=>true];
no71(fn()=>ev71($l,'finance_file',array_replace($f,['policy_id'=>'budget-2027-balance']),'bad-year'));
$l->advance('2027-10-01');
$b=ev71($l,'finance_file',$f,'budget')['budget'];$sha=$b['text_sha'];
ck71($b['assembly_deadline']==='2027-11-10'&&$b['parliament_deadline']==='2027-12-10','post-investiture annual budget window');
ck71($sha===F::digest($policy,2028),'year bound digest');
$v=['bill_id'=>'budget','text_sha'=>$sha,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0];
ev71($l,'finance_assembly',$v,'assembly');ev71($l,'finance_transfer',['bill_id'=>'budget','prime_minister'=>'pm-initial','transmission_certified'=>true],'transfer');
ev71($l,'finance_senate',$v,'senate');ev71($l,'finance_final',['bill_id'=>'budget','text_sha'=>$sha,'route'=>'identical','navette_certified'=>true,'yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0],'final');
$pub=['bill_id'=>'budget','text_sha'=>$sha,'president'=>'campaign-president','promulgation_certified'=>true,'publication_certified'=>true];
no71(fn()=>ev71($l,'finance_publish',$pub,'early'));
ev71($l,'finance_review',['bill_id'=>'budget','text_sha'=>$sha,'constitutional'=>true,'decision_certified'=>true],'review');
ev71($l,'finance_publish',$pub,'publish');$s=$l->snapshot();
ck71(F::proof($s['finance'],$policy,$sha,'2028-01-01')!==null&&F::proof($s['finance'],$policy,$sha,'2027-01-01')===null,'exact future year publication');
ck71(count(A::candidates($s,$policy,'2028-01-01','2027-10-01'))===1&&A::candidates($s,$policy,'2027-01-01','2027-10-01')===[],'existing trusted legal activation sees exact policy');
ck71(B::restore($s)->snapshot()===$s,'durable finance replay');
$p=F::policies(2028);$plus=$p[$policy];$minus=$p['budget-2028-defense-reduce'];
ck71($plus['duration_days']===366&&$plus['expense_delta']===-$minus['expense_delta']&&634000000000-$plus['expense_delta']*366<366,'symmetric incremental fiscal scenario over leap year');
ck71(count(F::catalog())===2&&F::initial()['year']===2027,'legacy finance rules unchanged');
