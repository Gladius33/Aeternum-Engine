<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CareerInstitutionLedger.php';
use Aeternum\WorldPolitics\CareerInstitutionLedger as L;
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
echo "PASS WP118–119: careers, distinct powers, compatibility, succession, continuity, research isolation and renewal\n";
