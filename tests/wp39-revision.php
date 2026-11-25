<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{ConstitutionalRevision,InstitutionalSimulation};
function ck39(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP39 FAIL: '.$s);echo "PASS WP39: $s\n";}
function no39(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck39(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function e39(string $kind,array $data,string $id,?string $revision='revision-one',string $day='2026-11-25'):array{
    return ['world_id'=>'world-test','country'=>'FR','day'=>$day,'evidence_id'=>$id,'revision_id'=>$revision,
        'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
}
function file39(ConstitutionalRevision $r,bool $project=true):void{
    $r->certify(e39('file',['initiative'=>$project?'government_project':'parliamentary_proposal',
        'sponsor'=>$project?'president':'deputy-one','prime_minister_proposal'=>$project,'republican_form_preserved'=>true],'file-one'));
}
function chamber39(ConstitutionalRevision $r,string $ch,string $sha):void{
    $r->certify(e39('chamber_vote',['chamber'=>$ch,'text_sha'=>$sha,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0],$ch.'-vote'));
}
function rat39(ConstitutionalRevision $r,string $route,string $sha,int $yes,int $no):array{
    return $r->certify(e39('ratify',['route'=>$route,'text_sha'=>$sha,'yes'=>$yes,'no'=>$no,'abstain'=>20,'blank_invalid'=>0],'ratification'));
}
$r=new ConstitutionalRevision('world-test','2026-11-25');
$p=$r->draft('revision-one','environment-report');$sha=$p['text_sha'];
ck39($p['status']==='draft'&&$r->snapshot()['constitution_version']===0,'draft has no constitutional effect');
no39(fn()=>$r->certify(e39('file',['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>false,'republican_form_preserved'=>true],'bad-file')),'requires PM proposal');
no39(fn()=>$r->certify(e39('file',['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>true,'republican_form_preserved'=>false],'bad-form')),'republican form protected');
file39($r);
chamber39($r,'assembly',$sha);
no39(fn()=>$r->certify(e39('route',['route'=>'congress','presidential_decision'=>true],'early-route')),'one chamber insufficient');
no39(fn()=>chamber39($r,'senate',str_repeat('0',64)),'different text rejected');
$r->certify(e39('context',['territorial_integrity'=>false,'presidential_vacancy'=>false,'definitive_incapacity'=>false],'territory',null));
no39(fn()=>chamber39($r,'senate',$sha),'territorial integrity suspends process');
$r->certify(e39('context',['territorial_integrity'=>true,'presidential_vacancy'=>true,'definitive_incapacity'=>false],'vacancy',null));
no39(fn()=>chamber39($r,'senate',$sha),'vacancy suspends process');
$r->certify(e39('context',['territorial_integrity'=>true,'presidential_vacancy'=>false,'definitive_incapacity'=>true],'incapacity',null));
no39(fn()=>chamber39($r,'senate',$sha),'definitive incapacity suspends process');
$r->certify(e39('context',['territorial_integrity'=>true,'presidential_vacancy'=>false,'definitive_incapacity'=>false],'restore',null));
chamber39($r,'senate',$sha);
$r->certify(e39('route',['route'=>'congress','presidential_decision'=>true],'route'));
$adopt=rat39($r,'congress',$sha,480,320);
ck39($adopt['procedure']['status']==='adopted'&&$adopt['procedure']['final_vote']['threshold']===480&&$r->snapshot()['constitution_version']===0,'three fifths expressed and adoption distinct from effectiveness');
$r->advance('2026-11-26');
$r->certify(e39('promulgate',['text_sha'=>$sha,'published_on'=>'2026-11-26'],'publication','revision-one','2026-11-26'));
ck39($r->snapshot()['constitution_version']===1&&$r->snapshot()['effective_clauses']['fictional-89-a']['text_sha']===$sha,'effective text version updated');
ck39(ConstitutionalRevision::restore($r->snapshot())->snapshot()===$r->snapshot(),'exact replay');
$bad=$r->snapshot();$bad['constitution_version']=9;no39(fn()=>ConstitutionalRevision::restore($bad),'tampered version rejected');
foreach([true,false] as $project){
    $x=new ConstitutionalRevision('world-test','2026-11-25');$x->draft('revision-one','institutional-report');$hash=$x->snapshot()['procedures']['revision-one']['text_sha'];
    file39($x,$project);chamber39($x,'assembly',$hash);chamber39($x,'senate',$hash);
    if(!$project)no39(fn()=>$x->certify(e39('route',['route'=>'congress','presidential_decision'=>true],'illegal-route')),'parliamentary proposal cannot use Congress');
    $route=$project?'congress':'referendum';
    $x->certify(e39('route',['route'=>$route,'presidential_decision'=>true],'route'));
    $a=rat39($x,$route,$hash,$project?479:500,$project?321:500);
    ck39($a['procedure']['status']==='rejected'&&$x->snapshot()['effective_clauses']===[],'failed ratification '.$route);
}
$i=new InstitutionalSimulation('world-test','FR','2026-11-25');$i->enableRevision();
no39(fn()=>$i->revision('scenario_revision_author','revision_evidence',e39('context',[],'fake',null),'fake'),'player cannot certify');
$i->revision('scenario_revision_author','revision_draft',['template'=>'environment-report'],'revision-one');
$i->advanceTo('2026-11-26');
ck39(InstitutionalSimulation::restore($i->snapshot())->hash()===$i->hash(),'nested institutional replay');
