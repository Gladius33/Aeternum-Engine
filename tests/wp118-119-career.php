<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CareerInstitutionLedger.php';
use Aeternum\WorldPolitics\CareerInstitutionLedger as L;
use Aeternum\WorldPolitics\{ConstitutionalRevision,InstitutionalSimulation,FrenchCareerInstitutionBridge};
require_once __DIR__.'/../games/world-politics/src/FrenchCareerInstitutionBridge.php';
function ck(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
function no(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('expected rejection');}
function evt(array $s,string $op,array $p,string $day='2027-05-14'):array{return ['world'=>$s['world'],'country'=>'FR','day'=>$day,'world_revision'=>0,'command'=>'cmd-'.($s['sequence']+1),'actor'=>'institution','operation'=>$op,'payload'=>$p,'evidence_sha'=>str_repeat('a',64)];}
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
echo "PASS WP118–119: careers, distinct powers, compatibility, succession, continuity, research isolation and renewal\n";
