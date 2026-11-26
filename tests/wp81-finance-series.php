<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/BicameralLegislation.php';
use Aeternum\WorldPolitics\{BicameralLegislation as B,FinanceBudget as F};
function ck81(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP81 FAIL '.$s);echo "PASS WP81: $s\n";}
function no81(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected denial');}
$l=new B('world-test','2027-05-14');$l->enableResponsibility();$l->enableAssembly();$l->enableLifecycle();$l->enableCabinet();$l->enableExecutive();$l->enableFinance(2028,true);
function ev81(B $l,string $kind,array $d,string $id):array{return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>null,'kind'=>$kind,'procedure_certified'=>true,'data'=>$d]);}
function bill81(B $l,int $y):void{
    $id='budget-'.$y;$policy=$id.'-defense-increase';$sha=F::digest($policy,$y);
    $l->advance(($y-1).'-10-01');
    $f=['policy_id'=>$policy,'prime_minister'=>'pm-initial','finance_minister'=>'civic-economy','government_project_certified'=>true];
    ev81($l,'finance_file',$f,$id);no81(fn()=>ev81($l,'finance_file',$f,$id.'-duplicate'));
    $v=['bill_id'=>$id,'text_sha'=>$sha,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0];
    ev81($l,'finance_assembly',$v,$id.'-a');ev81($l,'finance_transfer',['bill_id'=>$id,'prime_minister'=>'pm-initial','transmission_certified'=>true],$id.'-t');
    ev81($l,'finance_senate',$v,$id.'-s');ev81($l,'finance_final',['bill_id'=>$id,'text_sha'=>$sha,'route'=>'identical','navette_certified'=>true,'yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0],$id.'-f');
    ev81($l,'finance_review',['bill_id'=>$id,'text_sha'=>$sha,'constitutional'=>true,'decision_certified'=>true],$id.'-r');
    ev81($l,'finance_publish',['bill_id'=>$id,'text_sha'=>$sha,'president'=>'campaign-president','promulgation_certified'=>true,'publication_certified'=>true],$id.'-p');
}
no81(fn()=>ev81($l,'finance_file',['policy_id'=>'budget-2029-defense-increase','prime_minister'=>'pm-initial','finance_minister'=>'civic-economy','government_project_certified'=>true],'too-early'));
bill81($l,2028);bill81($l,2029);bill81($l,2030);
$s=$l->snapshot();$f=$s['finance'];
ck81(count($f['bills'])===3&&B::restore($s)->snapshot()===$s,'three annual laws coexist with exact replay');
foreach([2028,2029,2030] as $y)ck81(F::proof($f,'budget-'.$y.'-defense-increase',F::digest('budget-'.$y.'-defense-increase',$y),$y.'-01-01')!==null,'proof '.$y);
$p=F::seriesPolicies();ck81(count($p)===60&&$p['budget-2028-defense-increase']['duration_days']===366&&$p['budget-2029-defense-increase']['duration_days']===365&&isset($p['budget-2047-defense-maintain']),'full campaign catalog');
$d=['day'=>'2029-01-01','balance'=>0,'days'=>[],'policies'=>['budget-2028-defense-increase'=>['authorized_on'=>'2027-12-01']]];
$r=F::project($f,$d);ck81($r['continuity_review_required']&&$r['annual_continuity'][0]['published_and_authorized']&&!$r['annual_continuity'][1]['published_and_authorized'],'prior budget never satisfies new year');
ck81(F::filingYear($f,'2047-05-01')===2047,'horizon bounded');
