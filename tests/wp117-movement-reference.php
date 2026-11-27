<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/PoliticalMovementReference.php';
use Aeternum\WorldPolitics\PoliticalMovementReference as R;
function ck117(bool $x,string $s):void{if(!$x)throw new RuntimeException('WP117: '.$s);}
function reject117(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Expected rejection');}
$r=new R();$s=$r->summary();
ck117($s['countries']===195&&$s['electoral_observations']===1095&&$s['countries_with_electoral_labels']>=40&&!$s['exhaustive']&&!$s['runtime_admission'],'frozen source counts');
ck117($s['organizations']===53&&$s['people']===16&&$s['affiliations']===17&&$s['documents']===18&&$s['crosswalks']===9,'curated inventory');
ck117($s['campaign_sources']===22&&$s['office_observations']>100,'dated person references');
ck117((new R(null,$r->pin()['sha256']))->pin()===$r->pin(),'reproducible pin');
reject117(fn()=>new R(null,str_repeat('0',64)));
reject117(fn()=>$r->country('XX'));
ck117($r->country('FR')['coverage']==='partial_documentary_evidence'&&!$r->country('FR')['runtime_admission'],'France curated');
ck117(!$r->country('VA')['exhaustive'],'explicit gaps not zero parties');
ck117($s['partyfacts_references']===7199&&$s['countries_with_partyfacts']>100&&count($r->country('FR')['partyfacts_ids'])>0,'academic source expansion');
ck117($s['cnccfp_references']===575&&count($r->country('FR')['cnccfp_ids'])===575,'official accounting source');
$cf=$r->page('cnccfp_references','FR',null,17);ck117(count($cf['rows'])===17&&$cf['next']!==null,'bounded CNCCFP page');
foreach($cf['rows'] as $p)ck117($p['country']==='FR'&&$p['organization_id']===null&&!$p['runtime_admission'],'official source distinct from runtime identities');
$pf=$r->page('partyfacts_references','FR',null,17);ck117(count($pf['rows'])===17&&$pf['next']!==null,'bounded academic country page');
foreach($pf['rows'] as $p)ck117($p['country']==='FR'&&$p['organization_id']===null&&!$p['runtime_admission'],'source IDs without inferred merge');
$org=array_column($r->page('organizations','FR')['rows'],null,'id');
ck117(isset($org['fr-craf'],$org['fr-aaf'],$org['fr-af-committee'],$org['fr-alliance-royale'],$org['fr-nar'],$org['fr-uclf'],$org['fr-gar']),'royalist identities distinct');
ck117($org['fr-gar']['kind']==='advocacy_structure','GAR not a party');
$roles=array_column($r->page('affiliations')['rows'],null,'id');
ck117($roles['fr-gautier-committee-2022']['organization']==='fr-af-committee'&&!$roles['fr-gautier-committee-2022']['current_role_verified'],'historical secretary not current AAF leader');
ck117($roles['fr-benedetti-pnf-2019']['organization']!==$roles['fr-benedetti-nationalistes-2023']['organization'],'historical roles separate');
$docs=array_values(array_filter($r->page('documents')['rows'],fn($d)=>$d['content_status']==='section_index_only'));
ck117(count($docs)===2&&array_sum(array_map(fn($d)=>count($d['section_ids']),$docs))===50,'50 documentary section references');
foreach($docs as $d)ck117(!$d['runtime_admission']&&$d['restricted_documentary']&&$d['content_status']==='section_index_only','no executable or full source text');
ck117(count($r->country('CA')['organization_ids'])===14&&count($r->country('NZ')['organization_ids'])===16,'national register observations');
foreach($r->page('organizations','NZ')['rows'] as $o)ck117($o['registration_observation']['source']==='nz-electoral-register'&&!$o['runtime_admission'],'dated registration, no authority');
$catalog=array_values(array_filter($r->page('documents')['rows'],fn($d)=>$d['content_status']==='catalogue_reference_only'));
ck117(count($catalog)===16,'official statutes indexes');
foreach($catalog as $d)ck117($d['section_ids']===[]&&!$d['runtime_admission'],'no inferred document content');
foreach($r->page('crosswalks','FR')['rows'] as $c)ck117(!$c['legal_identity_verified']&&!$c['runtime_admission']&&str_starts_with($c['source_reference_id'],'cnccfp-'),'crosswalk not legal identity');
ck117($r->page('crosswalks','CA')['rows']===[],'country isolated crosswalk');
ck117($s['coverage_audit_countries']===195&&$s['unverified_label_correspondences']>0,'global discovery indexes');
$coverage=[];$after=null;
do{$p=$r->page('coverage_audit',null,$after,37);foreach($p['rows'] as $v){ck117(!isset($coverage[$v['country']])&&!$v['exhaustive']&&!$v['runtime_admission']&&!$v['programme_completeness_verified'],'coverage audit gaps explicit');$coverage[$v['country']]=$v['counts'];}$after=$p['next'];}while($after!==null);
ck117(count($coverage)===195&&$coverage['CA']['registered_party_observations']===14&&$coverage['NZ']['document_references']===16&&$coverage['FR']['reviewed_name_correspondences']===9,'national coverage counts');
$hints=[];$after=null;
do{$p=$r->page('label_correspondences',null,$after,43);foreach($p['rows'] as $v){ck117(!isset($hints[$v['id']])&&count($v['reference_ids'])>=2&&!$v['legal_identity_verified']&&!$v['runtime_admission'],'label candidate only');$hints[$v['id']]=true;}$after=$p['next'];}while($after!==null);
ck117(count($hints)===$s['unverified_label_correspondences'],'all label hints paginated');
foreach($r->page('label_correspondences','FR')['rows'] as $v)ck117($v['country']==='FR','country filter');
$seen=[];$after=null;
do{$p=$r->page('electoral_observations',null,$after,37);foreach($p['rows'] as $v){ck117(!isset($seen[$v['id']])&&$v['organization_id']===null&&!$v['current_membership_verified']&&!$v['runtime_admission'],'no label identity inference');$seen[$v['id']]=true;}$after=$p['next'];}while($after!==null);
ck117(count($seen)===1095,'bounded pagination complete');
reject117(fn()=>$r->page('people','XX'));
reject117(fn()=>$r->page('electoral_observations',null,'invalid'));
reject117(fn()=>$r->page('people',null,null,101));
reject117(fn()=>$r->page('documents','FR'));
$root=sys_get_temp_dir().'/wp117-'.bin2hex(random_bytes(8));mkdir($root);
try{
    foreach($r->pin()['inputs'] as $name=>$hash){if(!is_dir(dirname($root.'/'.$name)))mkdir(dirname($root.'/'.$name),0777,true);copy(__DIR__.'/../games/world-politics/data/'.$name,$root.'/'.$name);}
    $file=$root.'/wp117-movement-seed-v1.json';$bytes=file_get_contents($file);$d=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);
    $d['organizations'][0]['runtime_admission']=true;file_put_contents($file,json_encode($d,JSON_THROW_ON_ERROR));reject117(fn()=>new R($root));
    $d=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);$d['affiliations'][0]['organization']='missing';file_put_contents($file,json_encode($d,JSON_THROW_ON_ERROR));reject117(fn()=>new R($root));
    file_put_contents($file,$bytes);
    file_put_contents($root.'/wp117-national-register-supplement-v1.json',' ',FILE_APPEND);reject117(fn()=>new R($root));
    copy(__DIR__.'/../games/world-politics/data/wp117-national-register-supplement-v1.json',$root.'/wp117-national-register-supplement-v1.json');
    file_put_contents($root.'/wp115-ipu-party-seats-2026-11-27-v2.json',' ',FILE_APPEND);reject117(fn()=>new R($root));
}finally{$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);}
echo "PASS WP117: immutable documentary movement registry, provenance, gaps, roles, cursor and tamper checks\n";
echo json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
