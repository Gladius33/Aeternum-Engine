<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{InstitutionalSimulation,ElectoralScenario,PoliticalNegotiator,ElectoralCycle};
function ck34(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP34 FAIL: '.$s);echo "PASS WP34: $s\n";}
function no34(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck34(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function fixture34(string $cc):InstitutionalSimulation{
    $i=new InstitutionalSimulation('world-test',$cc,'2026-11-25');
    if($cc==='GB')$i->enableAccountability('initial-government',['commons'=>100]);
    $i->enableElectoral(ElectoralScenario::config($cc,'2026-11-25'));$i->enablePoliticalAI();return $i;
}
$fr=fixture34('FR');$fr->advanceTo('2026-11-26');
ck34($fr->snapshot()['political_ai']['status']==='awaiting_results','no early negotiation');
$fr->advanceTo('2026-12-03');$s=$fr->snapshot();
ck34($s['electoral']['coalition']===null&&$s['political_ai']['recommendation']['support_seats']===65,'French human advisory only');
ck34($s['political_ai']['recommendation']['parties']===['civic','liberal'],'feasible programme intersection');
foreach($s['political_ai']['legislative_projections'] as $p)ck34(array_sum($p['counts'])===100&&!$p['legal_effect'],'vote projection conserves seats and has no legal effect');
$fr->electoral('scenario_negotiator','electoral_coalition',['parties'=>['civic'],'programme'=>['economy'=>500,'services'=>600],'candidate'=>'civic-leader'],'human-coalition');
$fr->advanceTo('2026-12-04');ck34($fr->snapshot()['electoral']['coalition']['id']==='human-coalition','AI never overwrites human negotiation');
$gb=fixture34('GB');$gb->advanceTo('2026-11-29');$s=$gb->snapshot();
ck34($s['electoral']['coalition']['status']==='majority_agreement'&&$s['electoral']['government']===null,'foreign negotiation without appointment');
ck34($s['accountability']['incumbent']==='initial-government','government unchanged without certification');
ck34($s['electoral']['history'][0]['day']==='2026-11-26','result-day deterministic negotiation');
$seq=fixture34('GB');$seq->advanceTo('2026-11-26');$seq->advanceTo('2026-11-29');ck34($seq->hash()===$gb->hash(),'daily and jump equivalence');
$e=['world_id'=>'world-test','country'=>'GB','evidence_id'=>'verified-formation','coalition_id'=>$s['electoral']['coalition']['id'],
    'candidate'=>$s['electoral']['coalition']['candidate'],'day'=>'2026-11-29','procedure_certified'=>true];
no34(fn()=>$gb->electoral('scenario_negotiator','electoral_certify',$e,'fake'),'no self appointment');
$gb->electoral('constitutional_clerk','electoral_certify',$e,'real-formation');
ck34($gb->snapshot()['accountability']['incumbent']===$e['candidate'],'verified formation updates comparative incumbent');
ck34(InstitutionalSimulation::restore($gb->snapshot())->hash()===$gb->hash(),'certified handover replays');
$bad=$gb->snapshot();$bad['political_ai']['recommendation']['support_seats']=99;
no34(fn()=>InstitutionalSimulation::restore($bad),'tampered AI recommendation denied');
$us=fixture34('US');$us->advanceTo('2026-11-26');
ck34($us->snapshot()['electoral']['coalition']===null&&$us->snapshot()['political_ai']['status']==='presidential_legislature','no parliamentary executive in US');
