<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{ElectoralCycle,ElectoralScenario,InstitutionalSimulation};
function ck33(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP33 FAIL: '.$s);echo "PASS WP33: $s\n";}
function reject33(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck33(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
$day='2026-11-25';
$fr=new ElectoralCycle('world-test','FR',$day,ElectoralScenario::config('FR',$day));
reject33(fn()=>$fr->coalition('early',['civic'],['economy'=>500,'services'=>600],'civic-leader'),'no coalition before election');
$fr->advance('2026-11-26');$s=$fr->snapshot();
ck33($s['status']==='runoff_pending'&&$s['first_result']['vacant_seats']===100&&count($s['first_result']['pending_districts'])===100,'first round qualification and no invented early seats');
$fr->advance('2026-12-03');$r=$fr->snapshot()['result'];
ck33($r['seats']===['civic'=>40,'liberal'=>25,'regional'=>0,'social'=>35]&&$r['majority']===51&&$r['vacant_seats']===0,'second round and seat conservation');
ck33($r['ballots']<=$r['registered']&&$r['valid_votes']<=$r['ballots'],'turnout conservation');
$bad=$fr->coalition('bad-deal',['civic','social'],['economy'=>650,'services'=>450],'civic-leader');
ck33($bad['status']==='negotiation_failed'&&$bad['rejected']===['social'],'fictional party refuses incompatible programme');
$minor=$fr->coalition('minority',['civic'],['economy'=>500,'services'=>600],'civic-leader');
ck33($minor['status']==='minority_agreement'&&!$minor['appointed'],'minority not automatic appointment');
$good=$fr->coalition('good-deal',['civic','liberal'],['economy'=>575,'services'=>525],'civic-leader');
ck33($good['status']==='majority_agreement'&&$good['support_seats']===65&&$fr->snapshot()['government']===null,'majority not government');
$e=['world_id'=>'world-test','country'=>'FR','evidence_id'=>'formation-one','coalition_id'=>'good-deal','candidate'=>'civic-leader','day'=>'2026-12-03','procedure_certified'=>true];
$wrong=$e;$wrong['country']='DE';reject33(fn()=>$fr->certify('wrong',$wrong),'cross-country certificate rejected');
ck33($fr->certify('formation',$e)['status']==='certified_scenario_formation','separate trusted certificate');
ck33(ElectoralCycle::restore($fr->snapshot())->snapshot()===$fr->snapshot(),'full deterministic replay');
$de=new ElectoralCycle('world-test','DE',$day,ElectoralScenario::config('DE',$day));$de->advance('2026-11-26');
$d=$de->snapshot()['result'];ck33(array_sum($d['seats'])===100&&$d['seats']['regional']===0,'proportional seat allocation and threshold');
$gb=new ElectoralCycle('world-test','GB',$day,ElectoralScenario::config('GB',$day));$gb->advance('2026-11-26');ck33($gb->snapshot()['result']['seats']['civic']===40,'single-round district plurality');
$us=new ElectoralCycle('world-test','US',$day,ElectoralScenario::config('US',$day));$us->advance('2026-11-26');
reject33(fn()=>$us->coalition('us-deal',['civic','liberal'],['economy'=>575,'services'=>525],'civic-leader'),'US legislative seats do not elect government');
$config=ElectoralScenario::config('FR',$day);$config['districts']['district-001']['second']['votes']['regional']=1;
reject33(fn()=>new ElectoralCycle('world-test','FR',$day,$config),'eliminated candidate cannot receive runoff votes');
$config=ElectoralScenario::config('GB',$day);$config['districts']['district-001']['first']['votes']['civic']=2000;
reject33(fn()=>new ElectoralCycle('world-test','GB',$day,$config),'invalid turnout rejected');
$i=new InstitutionalSimulation('world-test','FR',$day);$i->enableElectoral(ElectoralScenario::config('FR',$day));$i->advanceTo('2026-12-03');
$i->electoral('scenario_negotiator','electoral_coalition',['parties'=>['civic','liberal'],'programme'=>['economy'=>575,'services'=>525],'candidate'=>'civic-leader'],'agreement');
ck33(InstitutionalSimulation::restore($i->snapshot())->hash()===$i->hash(),'nested institutional snapshot replay');
