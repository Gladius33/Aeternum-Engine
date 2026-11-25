<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{BicameralLegislation,FrenchParliament};
function ck42(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP42 FAIL: '.$s);echo "PASS WP42: $s\n";}
function no42(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck42(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function ev42(BicameralLegislation $l,string $kind,array $data,string $id,?string $bill=null):array{
    return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>$bill,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);
}
function model42():BicameralLegislation{$l=new BicameralLegislation('world-test','2026-11-25');$l->enableResponsibility();$l->enableAssembly();return $l;}
$l=model42();$p=$l->snapshot()['responsibility']['assembly'];$names=array_slice(array_keys($p['deputies']),0,58);
ck42(count($p['deputies'])===577&&$p['occupied_seats']===577,'577 fictional individual deputies');
no42(fn()=>ev42($l,'assembly_censure_file',['at'=>'2026-11-25T10:00:00Z','motion_id'=>'invalid','signatories'=>array_fill(0,58,$names[0])],'duplicates'),'duplicate signatures rejected');
ev42($l,'assembly_censure_file',['at'=>'2026-11-25T10:00:00Z','motion_id'=>'motion-one','signatories'=>$names],'file');
$l->advance('2026-11-27');
no42(fn()=>ev42($l,'assembly_censure_vote',['at'=>'2026-11-27T09:59:59Z','motion_id'=>'motion-one'],'early'),'48 hour minimum');
no42(fn()=>ev42($l,'assembly_censure_vote',['at'=>'2026-11-27T10:00:00Z','motion_id'=>'motion-one','votes_for'=>577],'fake'),'external tally rejected');
$r=ev42($l,'assembly_censure_vote',['at'=>'2026-11-27T10:00:00Z','motion_id'=>'motion-one'],'vote');
$t=$r['roll_call']['tally'];ck42(array_sum($t)===577&&count($r['roll_call']['roll_call'])===577,'roll call conserves seats');
ck42($r['procedure']['verified_votes_for']===$t['for']&&($r['procedure']['motion_status']==='adopted')===($t['for']>=289),'legal result follows roll call');
ck42(BicameralLegislation::restore($l->snapshot())->snapshot()===$l->snapshot(),'spontaneous motion replay');
$x=model42();
ev42($x,'assembly_confidence_open',['at'=>'2026-11-25T10:00:00Z','statement_id'=>'confidence-one','statement_kind'=>'general_policy','council_deliberated'=>true],'confidence-open');
$r=ev42($x,'assembly_confidence_vote',['at'=>'2026-11-25T12:00:00Z','statement_id'=>'confidence-one'],'confidence-vote');
$t=$r['roll_call']['tally'];ck42(($r['procedure']['confidence']==='approved')===($t['for']>$t['against']),'confidence uses expressed votes');
$y=model42();$b=$y->draft('bill-one','education-reform');
ev42($y,'file',['government_project'=>true,'accelerated'=>true,'joint_opposition'=>false,'filing_certified'=>true],'bill-file','bill-one');
ev42($y,'responsibility_engage',['at'=>'2026-11-25T10:00:00Z','council_deliberated'=>true,'version'=>'standard','text_sha'=>$b['texts']['standard'],'reading_delay_certified'=>true],'engage','bill-one');
no42(fn()=>ev42($y,'responsibility_file',['at'=>'2026-11-25T11:00:00Z','motion_id'=>'linked','signatures'=>58],'fake-file','bill-one'),'linked motion requires individual signatures');
ev42($y,'responsibility_file',['at'=>'2026-11-25T11:00:00Z','motion_id'=>'linked','signatories'=>$names],'linked-file','bill-one');
$y->advance('2026-11-27');
$r=ev42($y,'responsibility_vote',['at'=>'2026-11-27T11:00:00Z','motion_id'=>'linked'],'linked-vote','bill-one');
$p=FrenchParliament::restore($y->snapshot()['responsibility']['assembly']);$t=$p->result('linked')['tally'];
ck42(($r['bill']['status']==='rejected')===($t['for']>=289),'linked bill follows individual votes');
ck42(BicameralLegislation::restore($y->snapshot())->snapshot()===$y->snapshot(),'linked roll call exact replay');
$bad=$y->snapshot();$bad['responsibility']['assembly']['matters']['linked']['tally']['for']++;
no42(fn()=>BicameralLegislation::restore($bad),'tampered tally rejected');
