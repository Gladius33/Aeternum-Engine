<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{BicameralLegislation,FinanceBudget};
function ck46(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP46 FAIL: '.$s);echo "PASS WP46: $s\n";}
function no46(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck46(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function ev46(BicameralLegislation $l,string $k,array $d,string $id):array{return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>null,'kind'=>$k,'procedure_certified'=>true,'data'=>$d]);}
function setup46():BicameralLegislation{$l=new BicameralLegislation('world-test','2026-11-25');$l->enableResponsibility();$l->enableAssembly();$l->enableLifecycle();$l->enableCabinet();$l->enableExecutive();$l->enableFinance();return $l;}
$f=['policy_id'=>'budget-2027-balance','prime_minister'=>'pm-initial','finance_minister'=>'civic-economy','government_project_certified'=>true];
$l=setup46();no46(fn()=>ev46($l,'finance_file',array_replace($f,['finance_minister'=>'civic-health']),'bad'),'competent finance minister required');
$b=ev46($l,'finance_file',$f,'budget')['budget'];$sha=$b['text_sha'];
ck46($b['assembly_deadline']==='2027-01-04'&&$b['parliament_deadline']==='2027-02-03','40 and 70 day deadlines');
$trans=['bill_id'=>'budget','prime_minister'=>'pm-initial','transmission_certified'=>true];
no46(fn()=>ev46($l,'finance_transfer',$trans,'early'),'premature Senate referral denied');
$l->advance('2027-01-04');$b=ev46($l,'finance_transfer',$trans,'transfer')['budget'];
ck46($b['senate_deadline']==='2027-01-19','15 day special Senate deadline');
$l->advance('2027-01-20');$vote=['bill_id'=>'budget','text_sha'=>$sha,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0];
no46(fn()=>ev46($l,'finance_senate',$vote,'late'),'late special Senate vote denied');
$x=setup46();ev46($x,'finance_file',$f,'budget');ev46($x,'finance_assembly',$vote,'assembly');ev46($x,'finance_transfer',$trans,'transfer');
ev46($x,'finance_senate',$vote,'senate');
$final=['bill_id'=>'budget','text_sha'=>$sha,'route'=>'identical','navette_certified'=>true,'yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0];
ev46($x,'finance_final',$final,'final');
$pub=['bill_id'=>'budget','text_sha'=>$sha,'president'=>'campaign-president','promulgation_certified'=>true,'publication_certified'=>true];
no46(fn()=>ev46($x,'finance_publish',$pub,'early'),'review required before publication');
ev46($x,'finance_review',['bill_id'=>'budget','text_sha'=>$sha,'constitutional'=>true,'decision_certified'=>true],'review');
ev46($x,'finance_publish',$pub,'publish');
ck46(FinanceBudget::proof($x->snapshot()['finance'],$f['policy_id'],$sha,'2027-01-01')!==null,'exact annual published budget proof');
ck46(BicameralLegislation::restore($x->snapshot())->snapshot()===$x->snapshot(),'finance replay exact');
$bad=$x->snapshot();$bad['finance']['bills']['budget']['text_sha']=str_repeat('0',64);no46(fn()=>BicameralLegislation::restore($bad),'tampered appropriation hash rejected');
$dom=['day'=>'2027-01-01','balance'=>-1000000,'policies'=>[],'days'=>[['day'=>'2027-01-01','opening_balance'=>0,'revenue'=>10000000,'expense'=>11000000]]];
$t=FinanceBudget::project($x->snapshot()['finance'],$dom);
ck46($t['cash']===0&&$t['debt']===$t['opening_debt']+$t['borrowing_total']&&$t['borrowing_total']===1000000+$t['interest_total'],'cash and debt reconcile');
ck46($t['continuity_review_required'],'publication alone not domestic authorization');
