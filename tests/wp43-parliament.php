<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{BicameralLegislation,ParliamentaryLifecycle,InstitutionalSimulation,ElectoralCycle};
function ck43(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP43 FAIL: '.$s);echo "PASS WP43: $s\n";}
function no43(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck43(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function ev43(BicameralLegislation $l,string $kind,array $data,string $id):array{return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>null,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);}
$l=new BicameralLegislation('world-test','2026-11-25');$l->enableResponsibility();$l->enableAssembly();$l->enableLifecycle();
$r=$l->snapshot()['responsibility'];$names=array_slice(array_keys($r['assembly']['deputies']),0,58);
$d=['at'=>'2026-11-25T10:00:00Z','motion_id'=>'motion-one','signatories'=>$names,'vote_at'=>'2026-11-30T10:00:00Z',
    'sitting_days'=>['2026-11-27','2026-11-30','2026-12-01','2026-12-02','2026-12-03']];
$bad=$d;$bad['vote_at']='2026-12-03T10:00:00Z';
no43(fn()=>ev43($l,'assembly_censure_file',$bad,'bad-slot'),'third following sitting day enforced');
ck43($l->snapshot()['responsibility']['crisis']['spontaneous_motions']===[],'rejected slot leaves no filing');
ev43($l,'assembly_censure_file',$d,'file');
$l->advance('2026-11-30');
ck43($l->snapshot()['responsibility']['lifecycle']['slots']['motion-one']['status']==='scheduled','scheduled vote not premature');
no43(fn()=>ev43($l,'assembly_censure_vote',['at'=>'2026-11-30T10:00:00Z','motion_id'=>'motion-one'],'manual'),'no manual scheduled tally');
$l->advance('2026-12-01');
$r=$l->snapshot()['responsibility'];
ck43($r['lifecycle']['slots']['motion-one']['status']==='resolved'&&array_sum($r['assembly']['matters']['motion-one']['tally'])===577,'clock resolves exact scheduled vote');
$e=new ElectoralCycle('world-test','FR','2026-11-25',ParliamentaryLifecycle::electionConfig('2026-11-25'));$e->advance('2026-12-03');
$l->advance('2026-12-03');$l->installElection($e->snapshot());
$r=$l->snapshot()['responsibility'];
ck43(count($r['assembly']['deputies'])===577&&isset($r['lifecycle']['archives'][0]['assembly']['matters']['motion-one']),'new legislature preserves old roll call');
$counts=array_fill_keys(array_keys($e->snapshot()['config']['parties']),0);
foreach($r['assembly']['deputies'] as $dep)$counts[$dep['group']]++;
ck43($counts===$e->snapshot()['result']['seats'],'district seat conservation');
ev43($l,'assembly_coalition',['at'=>'2026-12-03T12:00:00Z','coalition_id'=>'new-agreement','groups'=>['civic','liberal'],'active'=>true,'agreement_certified'=>true],'coalition');
ck43($l->snapshot()['responsibility']['assembly']['coalitions']['new-agreement']['active'],'certified coalition change');
ck43(BicameralLegislation::restore($l->snapshot())->snapshot()===$l->snapshot(),'exact replay including election and calendar');
$l->advance('2027-07-01');ck43($l->snapshot()['responsibility']['lifecycle']['session']===null,'ordinary session ends');
$l->advance('2027-10-01');ck43($l->snapshot()['responsibility']['crisis']['session']==='ordinary-2027','ordinary session renewal');
$bad=$l->snapshot();$bad['responsibility']['lifecycle']['archives'][0]['sha']=str_repeat('0',64);
no43(fn()=>BicameralLegislation::restore($bad),'archive tamper rejected');
$i=new InstitutionalSimulation('world-test','FR','2026-11-25');$i->enableElectoral(ParliamentaryLifecycle::electionConfig('2026-11-25'));$i->enableLegislation(true,true,true);
$i->advanceTo('2026-12-03');
ck43($i->snapshot()['legislation']['responsibility']['lifecycle']['installed_on']==='2026-12-03','coordinated election installs roster');
ck43(InstitutionalSimulation::restore($i->snapshot())->hash()===$i->hash(),'institutional election source verified');
