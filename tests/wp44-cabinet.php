<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{BicameralLegislation,FrenchCabinet,ElectoralCycle,ParliamentaryLifecycle};
function ck44(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP44 FAIL: '.$s);echo "PASS WP44: $s\n";}
function no44(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck44(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function ev44(BicameralLegislation $l,string $kind,array $data,string $id,?string $bill=null):array{return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>$bill,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);}
$l=new BicameralLegislation('world-test','2026-11-25');$l->enableResponsibility();$l->enableAssembly();$l->enableLifecycle();$l->enableCabinet();
$r=$l->snapshot()['responsibility'];ck44(count($r['cabinet']['ministers'])===7&&!FrenchCabinet::projection($r['cabinet'],$r['assembly'])['cohabitation'],'initial cabinet');
$b=$l->draft('bill-one','education-reform');
$filing=['government_project'=>true,'accelerated'=>true,'joint_opposition'=>false,'filing_certified'=>true,'prime_minister_id'=>'pm-social'];
no44(fn()=>ev44($l,'file',$filing,'wrong-pm','bill-one'),'wrong prime minister rejected');
$filing['prime_minister_id']='pm-initial';ev44($l,'file',$filing,'file','bill-one');
$d=['at'=>'2026-11-25T10:00:00Z','prime_minister'=>'pm-initial','resignation_id'=>'resignation-one','resignation_presented'=>true];
$appoint=['at'=>'2026-11-25T11:00:00Z','appointment_id'=>'appointment-one','prime_minister'=>'pm-social','president'=>'campaign-president',
    'resignation_presented'=>true,'presidential_act_certified'=>true,'incompatibilities_cleared'=>true];
no44(fn()=>ev44($l,'assembly_cabinet_appoint',$appoint,'early'),'cannot dismiss incumbent without resignation');
ev44($l,'assembly_cabinet_resign',$d,'resign');
ck44($l->snapshot()['responsibility']['cabinet']['status']==='caretaker','presented resignation creates caretaker');
no44(fn()=>ev44($l,'assembly_cabinet_appoint',array_replace($appoint,['president'=>'intruder']),'fake'),'presidential authority required');
ev44($l,'assembly_cabinet_appoint',$appoint,'appoint');
$r=$l->snapshot()['responsibility'];$projection=FrenchCabinet::projection($r['cabinet'],$r['assembly']);
ck44($projection['cohabitation']&&$r['crisis']['government']==='in_office'&&count($r['cabinet']['archives'])===1,'cohabitation and cabinet continuity');
$portfolio=['at'=>'2026-11-25T12:00:00Z','prime_minister'=>'pm-social','president'=>'campaign-president','portfolio'=>'education',
    'person'=>'social-education','pm_proposal_certified'=>false,'presidential_act_certified'=>true,'incompatibilities_cleared'=>true];
no44(fn()=>ev44($l,'assembly_cabinet_portfolio',$portfolio,'bad-minister'),'PM proposal required');
$portfolio['pm_proposal_certified']=true;ev44($l,'assembly_cabinet_portfolio',$portfolio,'minister');
no44(fn()=>ev44($l,'assembly_cabinet_portfolio',array_replace($portfolio,['portfolio'=>'health']),'double'),'duplicate portfolio occupant rejected');
no44(fn()=>ev44($l,'assembly_cabinet_portfolio',array_replace($portfolio,['portfolio'=>'health','person'=>'deputy-001']),'deputy'),'parliamentary incompatibility');
$e=new ElectoralCycle('world-test','FR','2026-11-25',ParliamentaryLifecycle::electionConfig('2026-11-25'));$e->advance('2026-12-03');
$l->advance('2026-12-03');$l->installElection($e->snapshot());
$r=$l->snapshot()['responsibility'];$projection=FrenchCabinet::projection($r['cabinet'],$r['assembly']);
ck44($projection['cohabitation']&&$projection['declared_support_seats']===$e->snapshot()['result']['seats']['social'],'election changes support not cabinet automatically');
ck44($r['cabinet']['ministers']['education']==='social-education','portfolio survives election');
ck44(BicameralLegislation::restore($l->snapshot())->snapshot()===$l->snapshot(),'cabinet exact replay');
$bad=$l->snapshot();$bad['responsibility']['cabinet']['prime_minister']='pm-civic';no44(fn()=>BicameralLegislation::restore($bad),'tampered cabinet rejected');
