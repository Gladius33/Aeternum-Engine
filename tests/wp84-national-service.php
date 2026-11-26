<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/BicameralLegislation.php';
use Aeternum\WorldPolitics\{BicameralLegislation as B,NationalServiceLaw as N};
function ck84(bool $v,string $m):void{if(!$v)throw new RuntimeException('WP84 FAIL '.$m);echo "PASS WP84: $m\n";}
function no84(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Unexpected national service permission');}
$l=new B('world-test','2027-05-14');$l->enableResponsibility();$l->enableAssembly();$l->enableLifecycle();$l->enableCabinet();$l->enableExecutive();$l->enableFinance(2028,true);$l->enableCalendarReplay();$l->enableNationalService();
ck84(N::report($l->snapshot())['legal_status']==='suspended','opening suspension');
no84(fn()=> $l->draft('bad-suspend',N::SUSPEND));$bill=$l->draft('restore',N::RESTORE);no84(fn()=> $l->draft('duplicate',N::RESTORE));$sha=$bill['texts']['standard'];
function ev84(B $l,string $kind,array $data,string $id):array{return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>'restore','kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);}
ev84($l,'file',['government_project'=>true,'accelerated'=>false,'joint_opposition'=>false,'filing_certified'=>true,'prime_minister_id'=>'pm-initial'],'file');
$v=['chamber'=>'assembly','version'=>'standard','text_sha'=>$sha,'yes'=>200,'no'=>100,'abstain'=>0,'blank_invalid'=>0,'amendment_certified'=>false,'reading_delay_certified'=>true];
no84(fn()=>ev84($l,'reading',$v,'early'));$l->advance('2027-06-25');ev84($l,'reading',$v,'assembly');
$l->advance('2027-07-23');ev84($l,'reading',array_replace($v,['chamber'=>'senate']),'senate');
ev84($l,'transmit',['text_sha'=>$sha],'transmit');ev84($l,'refer_review',['referral_certified'=>true],'referral');
$l->advance('2027-08-07');ev84($l,'review_result',['constitutional'=>true,'text_sha'=>$sha],'review');
ev84($l,'promulgate',['text_sha'=>$sha,'presidential_act_certified'=>true],'promulgate');
no84(fn()=>ev84($l,'publish',['text_sha'=>$sha,'effective_on'=>'2027-08-08','publication_certified'=>true],'early-effect'));
ev84($l,'publish',['text_sha'=>$sha,'effective_on'=>'2027-11-05','publication_certified'=>true],'publish');
ck84(N::report($l->snapshot())['legal_status']==='suspended'&&N::report($l->snapshot())['pending_law']!==null,'law published but not yet effective');
$l->advance('2027-11-05');$r=N::report($l->snapshot());ck84($r['legal_status']==='restored'&&$r['pending_law']===null&&!$r['actual_call_up_authorized_by_report']&&$r['personnel_added']===null,'effective legal restoration distinct from implementation');
ck84(B::restore($l->snapshot())->snapshot()===$l->snapshot(),'exact legal replay');
$l->draft('suspend',N::SUSPEND);ck84(count(N::report($l->snapshot())['open_bills'])===1,'reversible by separate law');
