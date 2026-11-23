<?php
declare(strict_types=1);
require __DIR__.'/../tools/m13-release-gate.php';
function m13Assert(bool $yes,string $name):void {
    if (!$yes) throw new RuntimeException($name);
    echo "PASS M13 $name\n";
}
$doc=json_decode(file_get_contents(__DIR__.'/../docs/release-evidence-v1.json'),true,16,JSON_THROW_ON_ERROR);
$sha=str_repeat('a',40);
m13Assert(!M13Gate::verify($doc,$sha)['metadata_complete'],'blank manifest blocks release');
$good=$doc; $good['candidate_sha']=$sha;
foreach (array_keys($good['gates']) as $id) {
    $good['gates'][$id]=['result'=>'passed','evidence'=>[[
        'url'=>"https://github.com/Gladius33/Aeternum-Engine/commit/$sha",
        'sha'=>$sha,'reason'=>'synthetic contract test only; NOT a real result'
    ]]];
}
foreach (array_keys($good['checks']) as $name) $good['checks'][$name]=true;
$good['signoff']=['operator'=>'synthetic-test-only','sha'=>$sha,'at'=>'2026-11-23T17:00:00Z'];
m13Assert(M13Gate::verify($good,$sha)['metadata_complete'],'synthetic complete metadata passes format checks');
$bad=$good; $bad['candidate_sha']=str_repeat('b',40);
m13Assert(!M13Gate::verify($bad,$sha)['metadata_complete'],'candidate SHA mismatch blocked');
$bad=$good; unset($bad['gates']['G09']);
m13Assert(!M13Gate::verify($bad,$sha)['metadata_complete'],'missing G09 blocked');
$bad=$good; $bad['gates']['G01']['evidence'][0]['url']='https://example.org/fake';
m13Assert(!M13Gate::verify($bad,$sha)['metadata_complete'],'external evidence URL blocked');
$bad=$good; $bad['gates']['G03']['evidence'][0]['sha']=str_repeat('b',40);
m13Assert(!M13Gate::verify($bad,$sha)['metadata_complete'],'mismatched evidence SHA blocked');
$bad=$good; $bad['checks']['authorized_staging']=false;
m13Assert(!M13Gate::verify($bad,$sha)['metadata_complete'],'staging not proven blocks release');
$bad=$good; $bad['signoff']['sha']=str_repeat('b',40);
m13Assert(!M13Gate::verify($bad,$sha)['metadata_complete'],'missing valid operator approval blocked');
echo "M13 contract checks passed; no V1.0 release authorized.\n";
