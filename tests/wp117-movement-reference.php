<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/PoliticalMovementReference.php';
use Aeternum\WorldPolitics\PoliticalMovementReference as R;
function ck117(bool $x,string $s):void{if(!$x)throw new RuntimeException('WP117: '.$s);}
function reject117(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Expected rejection');}
$r=new R();$s=$r->summary();
ck117($s['countries']===195&&$s['electoral_observations']===1095&&$s['countries_with_electoral_labels']>=40&&!$s['exhaustive']&&!$s['runtime_admission'],'frozen source counts');
ck117($s['organizations']===23&&$s['people']===2&&$s['affiliations']===3&&$s['documents']===2,'curated inventory');
ck117($s['campaign_sources']===22&&$s['office_observations']>100,'dated person references');
ck117((new R(null,$r->pin()['sha256']))->pin()===$r->pin(),'reproducible pin');
reject117(fn()=>new R(null,str_repeat('0',64)));
reject117(fn()=>$r->country('XX'));
ck117($r->country('FR')['coverage']==='partial_documentary_evidence'&&!$r->country('FR')['runtime_admission'],'France curated');
ck117($r->country('VA')['coverage']==='no_movement_evidence'&&!$r->country('VA')['exhaustive'],'explicit gaps not zero parties');
$org=array_column($r->page('organizations','FR')['rows'],null,'id');
ck117(isset($org['fr-craf'],$org['fr-aaf'],$org['fr-af-committee'],$org['fr-alliance-royale'],$org['fr-nar'],$org['fr-uclf'],$org['fr-gar']),'royalist identities distinct');
ck117($org['fr-gar']['kind']==='advocacy_structure','GAR not a party');
$roles=array_column($r->page('affiliations')['rows'],null,'id');
ck117($roles['fr-gautier-committee-2022']['organization']==='fr-af-committee'&&!$roles['fr-gautier-committee-2022']['current_role_verified'],'historical secretary not current AAF leader');
ck117($roles['fr-benedetti-pnf-2019']['organization']!==$roles['fr-benedetti-nationalistes-2023']['organization'],'historical roles separate');
$docs=$r->page('documents')['rows'];
ck117(count($docs)===2&&array_sum(array_map(fn($d)=>count($d['section_ids']),$docs))===50,'50 documentary section references');
foreach($docs as $d)ck117(!$d['runtime_admission']&&$d['restricted_documentary']&&$d['content_status']==='section_index_only','no executable or full source text');
$seen=[];$after=null;
do{$p=$r->page('electoral_observations',null,$after,37);foreach($p['rows'] as $v){ck117(!isset($seen[$v['id']])&&$v['organization_id']===null&&!$v['current_membership_verified']&&!$v['runtime_admission'],'no label identity inference');$seen[$v['id']]=true;}$after=$p['next'];}while($after!==null);
ck117(count($seen)===1095,'bounded pagination complete');
reject117(fn()=>$r->page('people','XX'));
reject117(fn()=>$r->page('electoral_observations',null,'invalid'));
reject117(fn()=>$r->page('people',null,null,101));
reject117(fn()=>$r->page('documents','FR'));
$root=sys_get_temp_dir().'/wp117-'.bin2hex(random_bytes(8));mkdir($root);
try{
    foreach($r->pin()['inputs'] as $name=>$hash)copy(__DIR__.'/../games/world-politics/data/'.$name,$root.'/'.$name);
    $file=$root.'/wp117-movement-seed-v1.json';$bytes=file_get_contents($file);$d=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);
    $d['organizations'][0]['runtime_admission']=true;file_put_contents($file,json_encode($d,JSON_THROW_ON_ERROR));reject117(fn()=>new R($root));
    $d=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);$d['affiliations'][0]['organization']='missing';file_put_contents($file,json_encode($d,JSON_THROW_ON_ERROR));reject117(fn()=>new R($root));
    file_put_contents($file,$bytes);
    file_put_contents($root.'/wp115-ipu-party-seats-2026-11-27-v2.json',' ',FILE_APPEND);reject117(fn()=>new R($root));
}finally{foreach(glob($root.'/*') as $f)unlink($f);rmdir($root);}
echo "PASS WP117: immutable documentary movement registry, provenance, gaps, roles, cursor and tamper checks\n";
echo json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
