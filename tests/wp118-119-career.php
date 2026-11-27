<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CareerInstitutionLedger.php';
use Aeternum\WorldPolitics\CareerInstitutionLedger as L;
use Aeternum\WorldPolitics\{ConstitutionalRevision,InstitutionalSimulation,FrenchCareerInstitutionBridge};
require_once __DIR__.'/../games/world-politics/src/FrenchCareerInstitutionBridge.php';
function ck(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
function no(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('expected rejection');}
function evt(array $s,string $op,array $p,string $day='2027-05-14'):array{return ['world'=>$s['world'],'country'=>$s['country'],'day'=>$day,'world_revision'=>0,'command'=>'cmd-'.($s['sequence']+1),'actor'=>'institution','operation'=>$op,'payload'=>$p,'evidence_sha'=>str_repeat('a',64)];}
function add(array $s,string $id):array{return L::apply($s,evt($s,'admit',['career'=>$id,'account'=>'acct-'.$id,'character'=>'person-'.$id,'mode'=>'opening']));}
function grant(string $o,string $c,string $end='2032-05-14',bool $interim=false):array{return ['office'=>$o,'career'=>$c,'expires'=>$end,'interim'=>$interim];}
function change(array $s,string $reason,array $close,array $grants,string $day='2027-05-14'):array{return L::apply($s,evt($s,'transition',compact('reason','close','grants'),$day));}
$s=L::initial('test-world','FR','career','2027-05-14');foreach(['a','b','c','d','e'] as $id)$s=add($s,$id);
$s=change($s,'election',[],[grant('president','a'),grant('deputy-1','d'),grant('senator-1','e')]);
$p=$s['active']['president'];ck(L::allows($s,'acct-a',$p,1,'president.request_dissolution','2027-05-14'),'presidential capability');ck(!L::allows($s,'acct-b',$p,1,'president.request_dissolution','2027-05-14'),'wrong principal');
no(fn()=>change($s,'appointment',[],[grant('prime-minister','a')]));
$s=change($s,'appointment',[],[grant('prime-minister','b'),grant('minister-health','c'),grant('minister-education','c')]);
$m=$s['active']['minister-health'];ck(L::allows($s,'acct-c',$m,1,'ministry.health.propose','2027-05-14')&&!L::allows($s,'acct-c',$m,1,'president.request_dissolution','2027-05-14'),'portfolio isolation');
no(fn()=>change($s,'appointment',[],[grant('minister-justice','d')]));
no(fn()=>change($s,'resignation',['prime-minister'],[]));
$s=change($s,'censure',['prime-minister','minister-health','minister-education'],[]);
ck($s['government']==='vacant'&&isset($s['careers']['b'])&&!L::allows($s,'acct-c',$m,1,'ministry.health.propose','2027-05-14'),'cabinet closure preserves careers');
$s=L::apply($s,evt($s,'dissolve_assembly',['presidential_mandate'=>$p],'2027-06-01'));
ck($s['assembly']==='dissolved'&&$s['senate']==='active'&&!isset($s['active']['deputy-1'])&&isset($s['active']['senator-1']),'only assembly dissolved');
no(fn()=>L::apply($s,evt($s,'restore_assembly',[],'2027-06-05')));
$s=L::apply($s,evt($s,'restore_assembly',[],'2027-06-21'));
no(fn()=>L::apply($s,evt($s,'dissolve_assembly',['presidential_mandate'=>$p],'2027-07-01')));
$series=$s['mandates'][$p]['series'];$s=change($s,'succession',['president'],[grant('president','b','2027-07-31',true)],'2027-07-01');
$i=$s['active']['president'];ck($s['series'][$series]['end']==='2027-07-01'&&!L::allows($s,'acct-a',$p,1,'president.represent','2027-07-01')&&!L::allows($s,'acct-b',$i,2,'president.request_dissolution','2027-07-01'),'succession revokes, interim restricted');
$s=change($s,'succession',['president'],[grant('president','c','2032-07-15')],'2027-07-15');ck($s['state_id']==='FR'&&count($s['careers'])===5&&count($s['series'])===2,'state and career continuity');
$r=L::initial('research-world','FR','research','2027-05-14');no(fn()=>add($r,'x'));$r=L::apply($r,evt($r,'research_observe',['regime_label'=>'contested','assembly_status'=>'disrupted','legality'=>'disputed']));ck(!$r['research_observation']['runtime_authority']&&$r['active']===[],'research isolated');
$t=add(L::initial('renewal-world','FR','career','2027-05-14'),'a');$t=change($t,'election',[],[grant('president','a')]);$old=$t['mandates'][$t['active']['president']]['series'];
$t=change($t,'renewal',['president'],[grant('president','a','2037-05-14')],'2032-05-14');ck(count($t['series'])===1&&$t['series'][$old]['end']===null,'lawful continuous renewal preserves series');
no(fn()=>change($t,'renewal',['president'],[grant('president','a','2042-05-14')],'2037-05-14'));
$t=change($t,'resignation',['president'],[],'2033-05-14');
no(fn()=>change($t,'election',[],[grant('president','a','2039-05-14')],'2034-05-14'));
// Existing institutional simulation independently certifies the dissolution.
$institution=new InstitutionalSimulation('test-world','FR','2027-06-01');
$de=evt(L::initial('test-world','FR','career','2027-05-14'),'dissolve_assembly',['presidential_mandate'=>$p],'2027-06-01');
no(fn()=>FrenchCareerInstitutionBridge::evidence($institution->snapshot(),0,$de,$institution->hash()));
foreach(['prime_minister','assembly_speaker','senate_speaker'] as $role)$institution->decide($role,'record_consultation',[],'consult-'.$role);
$institution->decide('president','dissolve_lower_house',[],'dissolve-proof');
$proof=FrenchCareerInstitutionBridge::evidence($institution->snapshot(),4,$de,$institution->hash());
ck(strlen($proof)===64,'committed dissolution bridge');
no(fn()=>FrenchCareerInstitutionBridge::evidence($institution->snapshot(),4,$de,str_repeat('0',64)));
// Exercise an actual replayable Article 89 process, not a supplied next hash.
$v=add(L::initial('revision-world','FR','career','2027-05-14'),'a');
$v=change($v,'election',[],[grant('president','a')]);
$revision=new ConstitutionalRevision('revision-world','2027-05-14');
$d=$revision->draft('rev-one','presidential-seven-year-referendum');$sha=$d['text_sha'];
$re=fn($kind,$data,$id)=>['world_id'=>'revision-world','country'=>'FR','day'=>'2027-05-14','evidence_id'=>$id,'revision_id'=>'rev-one','kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
$makePayload=fn()=>['previous_sha'=>$v['constitution_sha'],'procedure'=>'article89','revision_state_json'=>json_encode($revision->snapshot(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)];
no(fn()=>L::apply($v,evt($v,'constitution_revision',$makePayload())));
$revision->certify($re('file',['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>true,'republican_form_preserved'=>true],'rev-file'));
foreach(['assembly','senate'] as $ch)$revision->certify($re('chamber_vote',['chamber'=>$ch,'text_sha'=>$sha,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0],'vote-'.$ch));
$revision->certify($re('route',['route'=>'referendum','presidential_decision'=>true],'rev-route'));
$revision->certify($re('ratify',['route'=>'referendum','text_sha'=>$sha,'yes'=>600,'no'=>400,'abstain'=>20,'blank_invalid'=>0],'rev-ratify'));
no(fn()=>L::apply($v,evt($v,'constitution_revision',$makePayload())));
$revision->certify($re('promulgate',['text_sha'=>$sha,'published_on'=>'2027-05-14'],'rev-publish'));
$oldMandate=$v['active']['president'];$v=L::apply($v,evt($v,'constitution_revision',$makePayload()));
ck($v['constitution_rules']['next_term_years']===7&&$v['constitution_rules']['term_limit']===2&&$v['mandates'][$oldMandate]['expires']==='2032-05-14','prospective revision preserves incumbent mandate');
$v=change($v,'renewal',['president'],[grant('president','a','2039-05-14')],'2032-05-14');
ck($v['mandates'][$v['active']['president']]['expires']==='2039-05-14','effective seven-year term');
// The live adapter derives the winner from the existing two-round tally, not a supplied office.
$b=add(add(L::initial('ballot-world','FR','career','2027-05-14'),'a'),'b');
$payload=['ballot'=>['registered'=>1000,'first'=>['votes'=>['a'=>600,'b'=>300],'blank_invalid'=>20],'second'=>null],'first_round_day'=>'2027-05-14','second_round_day'=>'2027-05-14'];
$be=evt($b,'presidential_ballot',$payload);$be['actor']='career-election-clerk';$be['evidence_sha']=L::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$b['world'],'day'=>$be['day'],'payload'=>$payload]);
$b=L::apply($b,$be);$bp=$b['active']['president'];ck($b['mandates'][$bp]['career']==='a','winner derived');
$act=function(array $s,string $actor,array $p):array{$e=evt($s,'cabinet_act',$p);$e['actor']=$actor;$e['evidence_sha']=L::digest(['kind'=>'session-cabinet-act-v1','world'=>$s['world'],'day'=>$e['day'],'actor'=>$actor,'payload'=>$p]);return $e;};
$pm=['kind'=>'appoint_pm','mandate'=>$bp,'generation'=>1,'target'=>'b','portfolio'=>null,'proposal'=>null];
no(fn()=>L::apply($b,$act($b,'acct-b',$pm)));
$b=L::apply($b,$act($b,'acct-a',$pm));ck($b['mandates'][$b['active']['prime-minister']]['career']==='b','verified president appoints PM');
$pmid=$b['active']['prime-minister'];$resign=['kind'=>'resign_pm','mandate'=>$pmid,'generation'=>1,'target'=>null,'portfolio'=>null,'proposal'=>null];$b=L::apply($b,$act($b,'acct-b',$resign));
ck($b['government']==='vacant'&&isset($b['careers']['b']),'PM resignation preserves career');
// Synthetic ordinary election is deterministic and enforces term eligibility.
$auto=add(add(add(L::initial('automatic-world','FR','career','2027-05-14'),'a'),'b'),'c');
$auto=change($auto,'election',[],[grant('president','a')]);
$payload=L::automaticBallot($auto,'2032-05-14');
ck($payload===L::automaticBallot($auto,'2032-05-14')&&L::automaticBallot($auto,'2032-05-13')===null,'deterministic due ballot');
$ev=evt($auto,'presidential_ballot',$payload,'2032-05-14');$ev['actor']='career-election-clerk';$ev['evidence_sha']=L::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$auto['world'],'day'=>$ev['day'],'payload'=>$payload]);
$next=L::apply($auto,$ev);ck($next['active']['president']!==$auto['active']['president'],'automatic handover advances generation');
$auto=change($auto,'renewal',['president'],[grant('president','a','2037-05-14')],'2032-05-14');
$payload=L::automaticBallot($auto,'2037-05-14');ck(!isset($payload['ballot']['first']['votes']['a']),'third consecutive term excluded');
// AI roster is deterministic and cannot authenticate as a human.
$npc=L::initial('npc-world','FR','career','2027-05-14');
$np=['careers'=>L::autonomousRoster($npc)];
$ne=evt($npc,'admit_ai_roster',$np);$ne['actor']='career-ai-clerk';$ne['evidence_sha']=L::digest(['kind'=>'fictional-ai-roster-v1','world'=>$npc['world'],'day'=>$ne['day'],'payload'=>$np]);
$npc=L::apply($npc,$ne);ck(count($npc['careers'])===3,'AI roster admitted');no(fn()=>L::apply($npc,$ne));
$ball=L::automaticBallot($npc,'2027-05-14');ck(count($ball['ballot']['first']['votes'])===3,'autonomous opening candidates');
$ne=evt($npc,'presidential_ballot',$ball);$ne['actor']='career-election-clerk';$ne['evidence_sha']=L::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$npc['world'],'day'=>$ne['day'],'payload'=>$ball]);
$npc=L::apply($npc,$ne);$nm=$npc['active']['president'];$winner=$npc['mandates'][$nm]['career'];
ck($npc['careers'][$winner]['account']===null&&!L::allows($npc,$winner,$nm,1,'president.represent','2027-05-14'),'AI winner has no human session capability');
// New production roster uses documentary people, not fictional NPCs.
$real=L::initial('real-world','FR','career','2027-05-14');
$rp=['people'=>L::realAutonomousRoster($real)];
$re=evt($real,'admit_real_ai_roster',$rp);$re['actor']='career-ai-clerk';$re['evidence_sha']=L::digest(['kind'=>'real-ai-roster-v1','world'=>$real['world'],'day'=>$re['day'],'payload'=>$rp]);
$real=L::apply($real,$re);
ck(count($real['careers'])===22&&$real['careers']['real-jean-luc-melenchon']['account']===null,'documented roster, no synthetic accounts');
foreach($real['careers'] as $person)ck(isset($person['identity']['source'],$person['person_reference'])&&$person['controller']==='ai','AI identity provenance');
$rb=L::automaticBallot($real,'2027-05-14');ck(count($rb['ballot']['first']['votes'])===22,'documented identities in fictional ballot');
no(fn()=>L::apply($real,$re));
// Documentary autonomy is limited to current, compatible offices and mapped measures.
require_once __DIR__.'/../games/world-politics/src/CareerProgrammeAutonomy.php';
$planClass=\Aeternum\WorldPolitics\CareerProgrammeAutonomy::class;
$planState=change($real,'appointment',[],[grant('prime-minister','real-jean-luc-melenchon')]);
$plan=$planClass::next($planState,'2027-05-14',[],['pf2027-jean-luc-melenchon-smic'=>true]);
ck($plan!==null&&$plan['career']==='real-jean-luc-melenchon'&&$plan['policy_id']==='pf2027-jean-luc-melenchon-smic','documented PM objective');
ck($planClass::next($planState,'2027-05-14',[],['pf2027-jean-luc-melenchon-smic'=>true],['real-jean-luc-melenchon'=>true])===null,'same-day activity suppresses fallback');
ck($planClass::next($real,'2027-05-14',[],['pf2027-jean-luc-melenchon-smic'=>true])===null,'identity alone grants no action');
no(fn()=>$planClass::normalizeFictional(['name'=>'Fixture','measure_ids'=>['unmapped']],'FR'));
// Government formation for real AI officeholders, two separate authorities.
$ac=change($real,'election',[],[grant('president','real-jean-luc-melenchon')]);
$autoCabinet=function(array $state,string $day):array{
    $payload=L::automaticCabinet($state,$day);
    ck($payload!==null,'automatic cabinet step expected');
    $e=evt($state,'automatic_cabinet',$payload,$day);$e['actor']='career-ai-clerk';
    $e['evidence_sha']=L::digest(['kind'=>'automatic-cabinet-v1','world'=>$state['world'],'day'=>$day,'actor'=>$e['actor'],'payload'=>$payload]);return $e;
};
$ap=$autoCabinet($ac,'2027-05-14');
ck($ap['payload']['kind']==='appoint_pm','vacant PM appointment');
$bad=$ap;$bad['payload']['target']='real-jean-luc-melenchon';$bad['evidence_sha']=L::digest(['kind'=>'automatic-cabinet-v1','world'=>$ac['world'],'day'=>$bad['day'],'actor'=>$bad['actor'],'payload'=>$bad['payload']]);
no(fn()=>L::apply($ac,$bad));
$ac=L::apply($ac,$ap);ck(isset($ac['active']['prime-minister'])&&L::automaticCabinet($ac,'2027-05-14')===null,'one automatic cabinet action per day');
$ap=$autoCabinet($ac,'2027-05-15');ck($ap['payload']['kind']==='propose_minister','PM proposes minister');
$ac=L::apply($ac,$ap);ck(!isset($ac['active']['minister-economy']),'proposal does not appoint');
$ap=$autoCabinet($ac,'2027-05-16');ck($ap['payload']['kind']==='confirm_minister','president confirms separate proposal');
$ac=L::apply($ac,$ap);ck(isset($ac['active']['minister-economy']),'minister installed');
$human=change(add($real,'human'),'election',[],[grant('president','human')]);ck(L::automaticCabinet($human,'2027-05-14')===null,'human presidency never silently appoints');
$humanPm=change(change(add($real,'human'),'election',[],[grant('president','real-jean-luc-melenchon')]),'appointment',[],[grant('prime-minister','human')]);
ck(L::automaticCabinet($humanPm,'2027-05-14')===null,'human PM right to nominate preserved');
// WP112 vacancy calendar with an independently certified interim mandate.
$vac=add(add(add(add(L::initial('vacancy-world','FR','career','2027-05-14'),'a'),'b'),'c'),'d');
$vac=change($vac,'election',[],[grant('president','a')]);
$vac=change($vac,'succession',['president'],[grant('president','b','2027-07-07',true)],'2027-06-01');
$vi=$vac['active']['president'];
ck(L::automaticBallot($vac,'2027-07-06')===null,'no premature vacancy handover');
$vp=L::automaticBallot($vac,'2027-07-07');
ck($vp['first_round_day']==='2027-06-22'&&$vp['second_round_day']==='2027-07-06'&&!isset($vp['ballot']['first']['votes']['a']),'vacancy dates and interrupted incumbent exclusion');
$ve=evt($vac,'presidential_ballot',$vp,'2027-07-07');$ve['actor']='career-election-clerk';$ve['evidence_sha']=L::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$vac['world'],'day'=>$ve['day'],'payload'=>$vp]);
$vv=L::apply($vac,$ve);$vm=$vv['active']['president'];
ck($vv['mandates'][$vi]['end']==='2027-07-07'&&!$vv['mandates'][$vm]['interim']&&$vv['mandates'][$vm]['generation']===3,'vacancy handover revokes interim');
no(fn()=>L::apply($vv,$ve));
// International identity / research isolation and existing constructive succession replay.
foreach(['DE','ES','GB','IT','JP','US'] as $cc){
    $f=add(L::initial('foreign-'.$cc,$cc,'career','2027-05-14'),'a');
    ck($f['country']===$cc&&$f['active']===[],'international opposition only');
    $rp=['people'=>L::realAutonomousRoster($f)];
    $re=evt($f,'admit_real_ai_roster',$rp);$re['actor']='career-ai-clerk';$re['evidence_sha']=L::digest(['kind'=>'real-ai-roster-v1','world'=>$f['world'],'day'=>$re['day'],'payload'=>$rp]);
    $f=L::apply($f,$re);ck(count($f['careers'])>=3&&$f['active']===[],'documentary identities do not grant offices');
    foreach($rp['people'] as $id=>$p)ck($f['careers'][$id]['account']===null&&$p['programme_complete']===false,'foreign identity without fabricated programme');
    no(fn()=>L::apply($f,$re));
    no(fn()=>change($f,'election',[],[grant('president','a')]));
    $r=L::initial('research-'.$cc,$cc,'research','2027-05-14');
    $r=L::apply($r,evt($r,'research_observe',['regime_label'=>'contested','assembly_status'=>'disrupted','legality'=>'disputed']));
    ck($r['state_id']===$cc&&$r['active']===[]&&!$r['research_observation']['runtime_authority'],'research does not transfer powers');
}
$f=add(L::initial('foreign-de','DE','career','2027-05-14'),'a');
$cg=new \Aeternum\WorldPolitics\ComparativeGovernment('foreign-de','DE','2027-05-14','initial-government',['bundestag'=>100]);
$common=['world_id'=>'foreign-de','country'=>'DE','motion_id'=>'motion-one','procedure_certified'=>true];
$cg->apply('accountability_file','parliament_clerk',$common+['evidence_id'=>'file-one','at'=>'2027-05-14T00:00:00Z','chamber'=>'bundestag','signatures'=>30,'candidate'=>'a','kind'=>'no_confidence']);
$cg->advance('2027-05-16');
$cg->apply('accountability_vote','parliament_clerk',$common+['evidence_id'=>'vote-one','at'=>'2027-05-16T00:00:00Z','chamber'=>'bundestag','present'=>100,'yes'=>60,'no'=>40,'abstain'=>0]);
$cg->apply('accountability_resolve','constitutional_clerk',$common+['evidence_id'=>'resolve-one','at'=>'2027-05-16T01:00:00Z','outcome'=>'appoint_successor','successor'=>'a']);
$fp=['government_json'=>json_encode($cg->snapshot(),JSON_THROW_ON_ERROR),'expires'=>'2030-05-16'];
$fe=evt($f,'foreign_government',$fp,'2027-05-16');$fe['actor']='career-comparative-clerk';$fe['evidence_sha']=L::digest(['kind'=>'comparative-government-replay-v1','world'=>$f['world'],'country'=>'DE','payload'=>$fp]);
$f=L::apply($f,$fe);$fm=$f['active']['federal-chancellor'];
ck(L::allows($f,'acct-a',$fm,1,'foreign.government.represent','2027-05-16')&&!L::allows($f,'acct-a',$fm,1,'president.request_dissolution','2027-05-16'),'country-specific authority');
no(fn()=>L::apply($f,$fe));
require_once __DIR__.'/../games/world-politics/src/UniverseIdentityReference.php';
$refs=\Aeternum\WorldPolitics\UniverseIdentityReference::catalogue();
ck(count($refs)===43&&count($refs['FR']['people'])===22,'all managed country references');
foreach($refs as $country=>$ref)ck(count($ref['people'])>=1&&$ref['country']===$country,'documentary coverage');
ck($refs['CH']['government_mode']==='collective_executive_not_single_prime_minister','collective executive preserved');
ck(!\Aeternum\WorldPolitics\UniverseIdentityReference::country('DE')['runtime_authority'],'documentary reference not a grant');
require_once __DIR__.'/../games/world-politics/src/CareerForeignWorld.php';
foreach(['DE','ES','GB','IT','JP','US'] as $cc){
    $f=L::initial('opening-'.strtolower($cc),$cc,'career','2027-05-14');
    $p=['people'=>L::realAutonomousRoster($f)];
    $e=evt($f,'admit_real_ai_roster',$p);$e['actor']='career-ai-clerk';$e['evidence_sha']=L::digest(['kind'=>'real-ai-roster-v1','world'=>$f['world'],'day'=>$e['day'],'payload'=>$p]);
    $f=L::apply($f,$e);
    $inst=\Aeternum\WorldPolitics\CareerForeignWorld::opening($f);
    $gov=$inst->snapshot()['accountability'];$id=$gov['incumbent'];
    ck(isset($f['careers'][$id]['person_reference'])&&$f['careers'][$id]['account']===null,'documented opening government '.$cc);
    $p=['government_json'=>\Aeternum\WorldPolitics\CareerForeignWorld::json($gov),'expires'=>\Aeternum\WorldPolitics\CareerForeignWorld::boundary($cc)];
    $e=evt($f,'foreign_opening',$p);$e['actor']='career-comparative-clerk';$e['evidence_sha']=L::digest(['kind'=>'comparative-opening-scenario-v1','world'=>$f['world'],'country'=>$cc,'payload'=>$p]);
    $next=L::apply($f,$e);
    ck(count($next['active'])===1&&$next['mandates'][array_values($next['active'])[0]]['career']===$id,'opening mandate '.$cc);
    no(fn()=>L::apply($next,$e));
}
$opening=\Aeternum\WorldPolitics\UniverseIdentityReference::openingPerson('cast-fixture','FR');
$cast=\Aeternum\WorldPolitics\UniverseIdentityReference::frenchChallengers('cast-fixture',1,$opening['name']);
ck($opening['kind']==='real'&&count(array_unique(array_column($cast,'name')))===3&&!in_array($opening['name'],array_column($cast,'name'),true),'real distinct scenario challenger casting');
ck($cast===\Aeternum\WorldPolitics\UniverseIdentityReference::frenchChallengers('cast-fixture',1,$opening['name']),'stable casting');
ck(\Aeternum\WorldPolitics\UniverseIdentityReference::openingPerson('cast-fixture','CH')===null,'Swiss collective executive not cast as president');
foreach(['DE','ES','GB','IT','JP','US'] as $cc){
    $base=\Aeternum\WorldPolitics\ComparativeElectionCalendar::config($cc,'2027-05-14');
    $config=\Aeternum\WorldPolitics\DocumentaryForeignBallot::config($cc,$base);
    $cycle=new \Aeternum\WorldPolitics\ElectoralCycle('doc-ballot-'.strtolower($cc),$cc,'2027-05-14',$config);
    $cycle->advance($config['poll_day']);$snap=$cycle->snapshot();
    ck(array_sum($snap['result']['seats'])===100,'documentary ballot conserves seats '.$cc);
    ck(\Aeternum\WorldPolitics\ElectoralCycle::restore($snap)->snapshot()===$snap,'documentary ballot replay '.$cc);
    foreach($config['parties'] as $party)ck(str_starts_with($party['leader'],'observation-')&&$party['programme']===['economy'=>500,'services'=>500],'real identity and neutral fixture');
    $neg=\Aeternum\WorldPolitics\PoliticalNegotiator::evaluate($snap);
    ck($neg['legislative_projections']===[],'no political predictions from unknown programmes '.$cc);
}
ck(\Aeternum\WorldPolitics\DocumentaryForeignBallot::compatible('DE',['spd','cdu-csu']),'observed coalition pattern');
ck(!\Aeternum\WorldPolitics\DocumentaryForeignBallot::compatible('DE',['afd','spd']),'unreviewed coalition blocked');
foreach(['DE','ES','GB','IT','JP','US'] as $cc){
    $state=L::initial('party-'.strtolower($cc),$cc,'career','2027-05-14');
    $p=['people'=>L::realAutonomousRoster($state)];$e=evt($state,'admit_real_ai_roster',$p);$e['actor']='career-ai-clerk';$e['evidence_sha']=L::digest(['kind'=>'real-ai-roster-v1','world'=>$state['world'],'day'=>$e['day'],'payload'=>$p]);$state=L::apply($state,$e);
    $p=['people'=>\Aeternum\WorldPolitics\DocumentaryForeignBallot::extraCareers($cc)];$e=evt($state,'admit_party_ai_roster',$p);$e['actor']='career-ai-clerk';$e['evidence_sha']=L::digest(['kind'=>'documentary-party-careers-v1','world'=>$state['world'],'day'=>$e['day'],'payload'=>$p]);$state=L::apply($state,$e);
    $base=\Aeternum\WorldPolitics\ComparativeElectionCalendar::config($cc,'2027-05-14');
    $config=\Aeternum\WorldPolitics\DocumentaryForeignBallot::config($cc,$base,true);
    foreach($config['parties'] as $party)ck(isset($state['careers'][$party['leader']]['person_reference']),'real candidate has canonical career '.$cc);
    $inst=\Aeternum\WorldPolitics\CareerForeignWorld::opening($state);
    $inst->enableElectoral($config);$inst->enablePoliticalAI();$inst->enableForeignRecurring();$inst->enableFormationProtocol();$inst->enableAutonomousFormation();
    ck(\Aeternum\WorldPolitics\InstitutionalSimulation::restore($inst->snapshot())->snapshot()===$inst->snapshot(),'versioned career electoral bootstrap '.$cc);
}



$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($s,'c','2028-07-15');
ck($progress['score']===null&&!$progress['rankable']&&$progress['series'][0]['tenure_days']===366&&$progress['series'][0]['maximum_unverified_credit_days']===62&&$progress['series'][0]['verified_credit_days']===null,'career opportunity reported but never awarded without evidence');
$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($s,'b','2028-07-15');
ck($progress['series']===[]&&$progress['score']===null,'interim does not count as presidency');
$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($s,'a','2028-07-15');
ck($progress['series'][0]['through_day']==='2027-07-01'&&$progress['series'][0]['tenure_days']===48,'closed series frozen');
$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($t,'a','2034-05-14');
ck(count($progress['series'])===1&&$progress['series'][0]['through_day']==='2033-05-14','renewed series remains continuous and freezes');
$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($s,'d','2028-07-15');
ck($progress['status']==='pre_mandate'&&$progress['pre_mandate_elapsed_days']===428&&$progress['score']===null,'waiting without accession is not scored');
$claimed=change($real,'election',[],[grant('president','real-jean-luc-melenchon')]);
$claim=['career'=>'real-jean-luc-melenchon','account'=>'acct-claim','edition'=>'campaign2027-jean-luc-melenchon','programme_sha'=>str_repeat('b',64)];
$ce=evt($claimed,'claim_real_career',$claim,'2028-05-14');$ce['actor']='career-admission';$ce['evidence_sha']=L::digest(['kind'=>'approved-real-career-claim-v1','world'=>$claimed['world'],'day'=>$ce['day'],'payload'=>$claim]);
$claimed=L::apply($claimed,$ce);
$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($claimed,'real-jean-luc-melenchon','2029-05-14');
ck($progress['admission_mode']==='late_takeover'&&$progress['series'][0]['tenure_days']===365&&$progress['series'][0]['maximum_unverified_credit_days']===0,'AI history is excluded from human tenure and compensation');

$early=change($real,'appointment',[],[grant('prime-minister','real-jean-luc-melenchon')]);
$earlyEvent=evt($early,'claim_real_career',$claim);$earlyEvent['actor']='career-admission';$earlyEvent['evidence_sha']=L::digest(['kind'=>'approved-real-career-claim-v1','world'=>$early['world'],'day'=>$earlyEvent['day'],'payload'=>$claim]);
$early=L::apply($early,$earlyEvent);
$early=change($early,'resignation',['prime-minister'],[],'2027-07-15');
$early=change($early,'election',[],[grant('president','real-jean-luc-melenchon','2032-07-15')],'2027-07-15');
$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($early,'real-jean-luc-melenchon','2028-07-15');
ck($progress['series'][0]['maximum_unverified_credit_days']===62&&$progress['series'][0]['minimum_tenure_reached'],'opening real claim before accession has same potential credit as fictional career');
$progress=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($early,'real-jean-luc-melenchon','2032-07-16');
ck($progress['series'][0]['status']==='expiry_pending'&&$progress['series'][0]['through_day']==='2032-07-15'&&$progress['score']===null,'unresolved expiry cannot accrue tenure');
echo "PASS WP118–119: careers, distinct powers, compatibility, succession, continuity, research isolation and renewal\n";


// All mapped programme proposals use the same portfolio gate for human and
// replacement actions; unknown ministries and foreign powers never qualify.
$pc=$planClass::normalizeFictional(['name'=>'Economic minister','measure_ids'=>['pf2027-dlf-production-tax','pf2027-philippe-teacher-pay']],'FR');
$cs=L::initial('portfolio-world','FR','career','2027-05-14');
$cs=add(add($cs,'president'),'pm');
$cs=L::apply($cs,evt($cs,'admit',['career'=>'minister','account'=>'acct-minister','character'=>'person-minister','mode'=>'opening','fictional_profile'=>$pc]));
$cs=change($cs,'election',[],[grant('president','president')]);
$cs=change($cs,'appointment',[],[grant('prime-minister','pm'),grant('minister-economy','minister')]);
$catalog=array_fill_keys(['pf2027-dlf-production-tax','pf2027-philippe-teacher-pay'],true);
$plan=$planClass::next($cs,'2027-05-14',[],$catalog);
ck($plan['career']==='minister'&&$plan['policy_id']==='pf2027-dlf-production-tax','economic minister fallback respects portfolio');
ck($planClass::next($cs,'2027-05-14',['pf2027-dlf-production-tax'=>true],$catalog)===null,'economy cannot borrow education portfolio');
ck($planClass::next($cs,'2027-05-14',[],$catalog,['minister'=>true])===null,'human economic proposal suppresses replacement');
foreach(\Aeternum\WorldPolitics\SocialProgramme::target2027() as $id=>$definition)ck($planClass::permitted('prime-minister',$id)&&!$planClass::permitted('president',$id),'reviewed catalogue authority '.$id);
no(fn()=>$planClass::portfolio('pf2022-marine-le-pen-2'));

// Participation is sealed only from contiguous clock-owned intervals.
$part=add(add(L::initial('participation-world','FR','career','2027-05-14'),'a'),'b');
$pe=function(array $state,string $operation,array $payload,string $day):array{
    $e=evt($state,$operation,$payload,$day);$e['actor']='career-participation-clerk';
    $e['world_revision']=\Aeternum\WorldPolitics\CareerScoreContract::elapsed('2027-05-14',$day);
    $e['evidence_sha']=\Aeternum\WorldPolitics\CareerParticipation::digest($e);return $e;
};
$part=L::apply($part,$pe($part,'participation_enable',['version'=>\Aeternum\WorldPolitics\CareerParticipation::VERSION],'2027-05-14'));
$tickPart=fn($s,$day,$next,$members)=>L::apply($s,$pe($s,'participation_tick',['through'=>$next,'members'=>$members],$day));
$part=$tickPart($part,'2027-05-14','2027-05-15',['a']);
no(fn()=>$tickPart($part,'2027-05-16','2027-05-17',['a','b']));
$part=$tickPart($part,'2027-05-15','2027-05-16',['a','b']);
$partA=change($part,'election',[],[grant('president','a')],'2027-05-16');
$partB=change($part,'election',[],[grant('president','b')],'2027-05-16');
$partA=$tickPart($partA,'2027-05-16','2027-05-17',['a','b']);
$pa=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($partA,'a','2027-05-17');
ck($pa['series'][0]['verified_credit_days']===2&&$pa['series'][0]['tenure_participation_verified']&&$pa['score']===null,'daily participation seals exact wait but no unsupported income score');
$pb=\Aeternum\WorldPolitics\CareerScoreContract::ledgerProgress($partB,'b','2027-05-16');
ck($pb['series'][0]['verified_credit_days']===null,'pre-accession gap cannot be laundered into opening credit');

require_once __DIR__.'/../games/world-politics/src/UniverseIdentityReference.php';
// Composable non-authoritative research observations preserve the legal
// reference and state identity; documentary coverage never grants offices.
$rt=\Aeternum\WorldPolitics\ResearchInstitutionTransition::class;
foreach(\Aeternum\WorldPolitics\UniverseIdentityReference::catalogue() as $country=>$ref){
    $s=L::initial('research-transition-'.strtolower($country),$country,'research','2027-05-14');
    $original=$s;
    $p=['version'=>$rt::VERSION,'previous_sha'=>L::digest(null),'constitutional_order'=>'parliamentary_republic',
        'effective_order'=>'provisional_government','territorial_form'=>'unitary','assembly_status'=>'disrupted',
        'senate_status'=>'unknown','legality'=>'disputed','recognition'=>'mixed','service_continuity_bps'=>8500,
        'classification'=>'counterfactual_aggregate_observation'];
    $e=evt($s,'research_transition',$p);$e['actor']='research-clerk';$e['evidence_sha']=$rt::digest($e);
    $s=L::apply($s,$e);
    ck($s['state_id']===$country&&$s['constitution_sha']===$original['constitution_sha']&&$s['regime']===$original['regime']
        &&$s['active']===[]&&!$s['research_transition']['rankable']&&!$s['research_transition']['runtime_authority'],'research continuity '.$country);
    no(fn()=>L::apply($s,$e));
    $p['previous_sha']=L::digest($s['research_transition']);$p['effective_order']='parliamentary_republic';
    $p['assembly_status']='restored';$p['legality']='recognized';$p['service_continuity_bps']=10000;
    $e2=evt($s,'research_transition',$p,'2027-05-15');$e2['actor']='research-clerk';$e2['evidence_sha']=$rt::digest($e2);
    $next=L::apply($s,$e2);
    ck($next['research_transition']['ordinal']===2&&$next['research_transition']['state_id']===$country&&$next['research_transition']['assembly_status']==='restored','research restoration '.$country);
    $tampered=$e2;$tampered['payload']['recognition']='unrecognized';no(fn()=>L::apply($s,$tampered));
    if(!\Aeternum\WorldPolitics\InstitutionalRulebook::hasRules($country))no(fn()=>L::initial('unsupported-career',$country,'career','2027-05-14'));
}

$expanded=L::initial('world-roster-expanded','FR','career','2027-05-14');
$rp=['people'=>L::realAutonomousRoster($expanded)];
$re=evt($expanded,'admit_real_ai_roster',$rp);$re['actor']='career-ai-clerk';$re['evidence_sha']=L::digest(['kind'=>'real-ai-roster-v1','world'=>$expanded['world'],'day'=>$re['day'],'payload'=>$rp]);
$expanded=L::apply($expanded,$re);
require_once __DIR__.'/../games/world-politics/src/FrenchPoliticalDirectory.php';
$rp=['people'=>\Aeternum\WorldPolitics\FrenchPoliticalDirectory::runtimeSupplementV1()];
$re=evt($expanded,'admit_fr_supplement_roster',$rp);$re['actor']='career-ai-clerk';$re['evidence_sha']=L::digest(['kind'=>'fr-supplement-roster-v1','world'=>$expanded['world'],'day'=>$re['day'],'payload'=>$rp]);
$expanded=L::apply($expanded,$re);ck(count($expanded['careers'])===36,'new roster expands without changing old roster event');
$rb=L::automaticBallot($expanded,'2027-05-14');ck(count($rb['ballot']['first']['votes'])===36,'expanded synthetic ballot capacity');
$be=evt($expanded,'presidential_ballot',$rb);$be['actor']='career-election-clerk';$be['evidence_sha']=L::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$expanded['world'],'day'=>$be['day'],'payload'=>$rb]);
ck(isset(L::apply($expanded,$be)['active']['president']),'expanded election can be certified');
