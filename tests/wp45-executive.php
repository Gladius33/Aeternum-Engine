<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{BicameralLegislation,ExecutiveActs};
function ck45(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP45 FAIL: '.$s);echo "PASS WP45: $s\n";}
function no45(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck45(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function ev45(BicameralLegislation $l,string $k,array $d,string $id):array{return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>null,'kind'=>$k,'procedure_certified'=>true,'data'=>$d]);}
$l=new BicameralLegislation('world-test','2026-11-25');$l->enableResponsibility();$l->enableAssembly();$l->enableLifecycle();$l->enableCabinet();$l->enableExecutive();
$d=['template'=>'reporting-decree','kind'=>'pm_decree','habilitation'=>null,'signer'=>'pm-initial','prime_minister'=>'pm-initial','minister'=>'civic-economy','council_deliberated'=>false,'state_council_consulted'=>false,'procedure_reviewed'=>true];
no45(fn()=>ev45($l,'executive_sign',array_replace($d,['minister'=>'civic-health']),'bad'),'competent minister required');
no45(fn()=>ev45($l,'executive_sign',array_replace($d,['template'=>'reporting-ordinance']),'bad'),'no law by ordinary decree');
$a=ev45($l,'executive_sign',$d,'decree')['act'];
ck45(ExecutiveActs::proof($l->snapshot()['executive'],ExecutiveActs::POLICY,$a['text_sha'],'2026-11-26')===null,'signature not publication');
ev45($l,'executive_publish',['act_id'=>'decree','text_sha'=>$a['text_sha'],'publication_certified'=>true,'effective_on'=>'2026-11-26'],'publish');
ck45(ExecutiveActs::proof($l->snapshot()['executive'],ExecutiveActs::POLICY,$a['text_sha'],'2026-11-26')!==null,'published exact decree proof');
no45(fn()=>ev45($l,'executive_sign',array_replace($d,['kind'=>'council_decree','council_deliberated'=>true]),'bad'),'Council decree needs president');
$o=array_replace($d,['kind'=>'ordinance','template'=>'reporting-ordinance','habilitation'=>'habilitation','signer'=>'campaign-president','council_deliberated'=>true,'state_council_consulted'=>true]);
no45(fn()=>ev45($l,'executive_sign',$o,'bad'),'no ordinance without delegation');
ev45($l,'executive_habilitation',['scope'=>'reporting-ordinance','law_sha'=>str_repeat('a',64),'authorization_end'=>'2026-11-28','filing_deadline'=>'2026-12-01','law_published_certified'=>true],'habilitation');
no45(fn()=>ev45($l,'executive_sign',array_replace($o,['state_council_consulted'=>false]),'bad'),'Council of State opinion required');
foreach(['ordinance-one','ordinance-two'] as $id){
    $a=ev45($l,'executive_sign',$o,$id)['act'];
    ev45($l,'executive_publish',['act_id'=>$id,'text_sha'=>$a['text_sha'],'publication_certified'=>true,'effective_on'=>'2026-11-25'],'publish-'.$id);
}
ev45($l,'executive_ratification_file',['act_id'=>'ordinance-two','bill_sha'=>str_repeat('b',64),'filing_certified'=>true],'file-ratification');
$l->advance('2026-12-01');$acts=$l->snapshot()['executive']['acts'];
ck45($acts['ordinance-one']['status']==='lapsed'&&$acts['ordinance-two']['status']==='published','missed deadline versus timely filing');
no45(fn()=>ev45($l,'executive_sign',$o,'late'),'expired delegation');
no45(fn()=>ev45($l,'executive_ratification',['act_id'=>'ordinance-two','law_sha'=>str_repeat('c',64),'bill_sha'=>str_repeat('b',64),'express_ratification_certified'=>false],'bad'),'no implicit ratification');
ev45($l,'executive_ratification',['act_id'=>'ordinance-two','law_sha'=>str_repeat('c',64),'bill_sha'=>str_repeat('b',64),'express_ratification_certified'=>true],'ratification');
ck45($l->snapshot()['executive']['acts']['ordinance-two']['status']==='ratified','express ratification');
ck45(BicameralLegislation::restore($l->snapshot())->snapshot()===$l->snapshot(),'executive replay exact');
$bad=$l->snapshot();$bad['executive']['acts']['ordinance-one']['status']='ratified';no45(fn()=>BicameralLegislation::restore($bad),'tampered status rejected');
