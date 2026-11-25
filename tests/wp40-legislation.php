<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\BicameralLegislation;
function ck40(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP40 FAIL: '.$s);echo "PASS WP40: $s\n";}
function no40(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck40(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function e40(BicameralLegislation $l,string $kind,array $data,string $id):array{return ['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>'bill-one','kind'=>$kind,'procedure_certified'=>true,'data'=>$data];}
function do40(BicameralLegislation $l,string $kind,array $data,string $id):array{return $l->certify(e40($l,$kind,$data,$id));}
function read40(BicameralLegislation $l,string $ch,string $v,string $id):array{
    return do40($l,'reading',['chamber'=>$ch,'version'=>$v,'text_sha'=>$l->snapshot()['bills']['bill-one']['texts'][$v],
        'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0,'amendment_certified'=>true,'reading_delay_certified'=>true],$id);
}
function setup40(bool $acc):BicameralLegislation{
    $l=new BicameralLegislation('world-test','2026-11-25');$l->draft('bill-one','education-reform');
    do40($l,'file',['government_project'=>true,'accelerated'=>$acc,'joint_opposition'=>false,'filing_certified'=>true],'file');return $l;
}
$l=setup40(false);no40(fn()=>read40($l,'assembly','standard','early'),'ordinary first reading delay');
$l->advance('2027-01-06');read40($l,'assembly','standard','a1');
no40(fn()=>read40($l,'senate','standard','early-s'),'second chamber first reading delay');
$l->advance('2027-02-03');read40($l,'senate','amended','s1');
no40(fn()=>do40($l,'cmp',['government_request'=>true,'agreed'=>false,'version'=>null,'text_sha'=>null],'early-cmp'),'ordinary CMP after two readings each');
read40($l,'assembly','standard','a2');read40($l,'senate','amended','s2');
do40($l,'cmp',['government_request'=>true,'agreed'=>false,'version'=>null,'text_sha'=>null],'cmp');
$sha=$l->snapshot()['bills']['bill-one']['texts']['standard'];
no40(fn()=>do40($l,'last_word',['government_request'=>true,'version'=>'standard','text_sha'=>$sha,'yes'=>300,'no'=>250,'abstain'=>20,'blank_invalid'=>0],'early-last'),'last word requires new readings');
read40($l,'assembly','standard','a3');read40($l,'senate','amended','s3');
do40($l,'last_word',['government_request'=>true,'version'=>'standard','text_sha'=>$sha,'yes'=>300,'no'=>250,'abstain'=>20,'blank_invalid'=>0],'last');
ck40($l->snapshot()['bills']['bill-one']['status']==='adopted'&&$l->proof('education-reform',$sha,'2027-02-10')===null,'adoption not publication');
do40($l,'transmit',['text_sha'=>$sha],'transmit');
do40($l,'refer_review',['referral_certified'=>true],'referral');
no40(fn()=>do40($l,'promulgate',['text_sha'=>$sha,'presidential_act_certified'=>true],'blocked'),'pending constitutional review blocks promulgation');
$l->advance('2027-02-09');do40($l,'review_result',['constitutional'=>true,'text_sha'=>$sha],'review');
do40($l,'promulgate',['text_sha'=>$sha,'presidential_act_certified'=>true],'promulgation');
do40($l,'publish',['text_sha'=>$sha,'effective_on'=>'2027-02-10','publication_certified'=>true],'publication');
ck40($l->proof('education-reform',$sha,'2027-02-10')!==null&&$l->proof('education-reform',$sha,'2027-02-11')===null,'publication binds exact policy text and effective day');
ck40(BicameralLegislation::restore($l->snapshot())->snapshot()===$l->snapshot(),'exact journal replay');
$bad=$l->snapshot();$bad['bills']['bill-one']['effect_on']='2027-02-11';no40(fn()=>BicameralLegislation::restore($bad),'tampered effective date rejected');
$x=setup40(true);read40($x,'assembly','standard','a1');read40($x,'senate','amended','s1');
$h=$x->snapshot()['bills']['bill-one']['texts']['amended'];
do40($x,'cmp',['government_request'=>true,'agreed'=>true,'version'=>'amended','text_sha'=>$h],'cmp');
foreach(['assembly','senate'] as $ch)do40($x,'cmp_vote',['chamber'=>$ch,'version'=>'amended','text_sha'=>$h,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0],$ch.'cmp');
ck40($x->snapshot()['bills']['bill-one']['status']==='adopted','accelerated CMP ratified by both chambers');
do40($x,'transmit',['text_sha'=>$h],'transmit');do40($x,'promulgate',['text_sha'=>$h,'presidential_act_certified'=>true],'promulgate');
do40($x,'publish',['text_sha'=>$h,'effective_on'=>'2026-11-26','publication_certified'=>true],'publish');
ck40($x->proof('education-reform',$h,'2026-11-26')===null,'amended text cannot trigger unmatched fiscal assumptions');
