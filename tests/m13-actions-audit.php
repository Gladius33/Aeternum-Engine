<?php
declare(strict_types=1);
require __DIR__.'/../tools/m13-actions-audit.php';
function m13ActionCheck(bool $ok,string $name): void {
    if (!$ok) throw new RuntimeException($name);
    echo "PASS M13 Actions $name\n";
}
$sha=str_repeat('a',40);
$other=str_repeat('b',40);
$make=static fn(string $name,int $id,string $result='success',string $head=$sha):array=>[
    'name'=>$name,'id'=>$id,'head_sha'=>$head,'event'=>'pull_request',
    'status'=>'completed','conclusion'=>$result
];
$all=[];
foreach (M13ActionsAudit::REQUIRED as $i=>$name) $all[]=$make($name,101+$i);
m13ActionCheck(M13ActionsAudit::evaluate($all,$sha)['all_required_ci_green'],
    'six distinct workflows succeed on exact candidate SHA');
$missing=$all;array_pop($missing);
m13ActionCheck(!M13ActionsAudit::evaluate($missing,$sha)['all_required_ci_green']
    && count(M13ActionsAudit::evaluate($missing,$sha)['missing'])===1,
    'missing workflow cannot be inferred from other green CI');
$bad=$all;$bad[0]=$make(M13ActionsAudit::REQUIRED[0],101,'success',$other);
m13ActionCheck(!M13ActionsAudit::evaluate($bad,$sha)['all_required_ci_green'],
    'green workflow on older/different SHA does not count');
$bad=$all;$bad[1]=$make(M13ActionsAudit::REQUIRED[1],102,'failure');
m13ActionCheck(!M13ActionsAudit::evaluate($bad,$sha)['all_required_ci_green'],
    'failed Chromium blocks exact-head CI approval');
$bad=$all;$bad[2]=array_merge($bad[2],['status'=>'in_progress','conclusion'=>null]);
m13ActionCheck(!M13ActionsAudit::evaluate($bad,$sha)['all_required_ci_green'],
    'running workflow is never green');
$bad=$all;$bad[]=$make(M13ActionsAudit::REQUIRED[0],999,'cancelled');
m13ActionCheck(!M13ActionsAudit::evaluate($bad,$sha)['all_required_ci_green'],
    'latest cancelled run supersedes earlier successful attempt');
$bad=$all;$bad[]=array_merge($make(M13ActionsAudit::REQUIRED[0],999),['event'=>'workflow_dispatch']);
m13ActionCheck(M13ActionsAudit::evaluate($bad,$sha)['all_required_ci_green'],
    'unapproved event type cannot replace required PR/push proof');
echo "M13 GitHub Actions evaluation tests passed; live API verification remains separate.\n";
