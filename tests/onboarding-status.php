<?php
declare(strict_types=1);
use Aeternum\Core\{PdoOnboardingStatus,PdoIdentity,FrozenClock,SystemClock};
// A deliberately pending identity tests the 15-minute stalled-claim indicator.
$obsActor=(new PdoIdentity(db()))->register('metrics-pending-test','metrics pending secret password');
$obs=db()->prepare("INSERT INTO engine_onboarding(actor_id,world_id,status,created_at) VALUES (?,?,'pending',?)");
$obs->execute([$obsActor,'public-test-world',100]);
$statusService=new PdoOnboardingStatus(db(),new FrozenClock(2000));
$status=$statusService->snapshot('public-test-world');
check($status['enrollment']['pending']===1 && $status['enrollment']['pendingOver15Minutes']===1,
    'operator status detects pending registration older than fifteen minutes');
check($status['enrollment']['oldestPendingAgeSeconds']===1900,'oldest pending age derives from injected clock');
check($status['enrollment']['ready']>=3,'operator status includes genuine ready player enrollments');
check($status['invitations']===['claimed'=>0,'available'=>0,'revoked'=>0,'expired'=>0],
    'operator status isolates invitation totals to the specified world');
check($statusService->snapshot('empty-operator-world')['enrollment']['ready']===0,
    'operator status safely reports empty world');
rejects(fn()=> $statusService->snapshot('../invalid'),InvalidArgumentException::class,'status rejects invalid world');
check(!str_contains(json_encode($status,JSON_THROW_ON_ERROR),'metrics-pending-test'),
    'status output never discloses individual logins');

// The real operator CLI must apply the approved-world allowlist and emit only
// numeric aggregates, with no tokens or account identifiers.
$env=getenv();
$env['AETERNUM_DSN']=getenv('AETERNUM_TEST_DSN');
$env['AETERNUM_DB_USER']=getenv('AETERNUM_TEST_USER')?:'';
$env['AETERNUM_DB_PASSWORD']=getenv('AETERNUM_TEST_PASSWORD')?:'';
$env['AETERNUM_MEDIEVAL_WORLD']='public-test-world';
$env['AETERNUM_INDUSTRIAL_WORLD']='';
$runStatusCli=static function(array $args) use($env):array {
    $p=proc_open([PHP_BINARY,__DIR__.'/../bin/onboarding-status.php',...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if (!is_resource($p)) throw new RuntimeException('Unable to run onboarding status CLI');
    fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($p),$out,$err];
};
[$exit,$out,$err]=$runStatusCli(['public-test-world']);
check($exit===0 && $err==='', 'read-only approved world status CLI succeeds');
$report=json_decode($out,true,8,JSON_THROW_ON_ERROR);
$expected=(new PdoOnboardingStatus(db(),new SystemClock()))->snapshot('public-test-world');
check($report['world']==='public-test-world'
    && $report['enrollment']===$expected['enrollment']
    && $report['invitations']===$expected['invitations']
    && $report['activeSessions']===$expected['activeSessions'],
    'CLI reports same live MariaDB aggregates as direct observation');
check(!str_contains($out,$obsActor) && !str_contains($out,'metrics-pending-test') && !str_contains($out,'token_hash'),
    'CLI never emits actors, logins or secret column names');
foreach([[],['unapproved-world'],['public-test-world','extra']] as $args) {
    [$exit,$out,$err]=$runStatusCli($args);
    check($exit!==0 && $out==='' && $err!=='','status CLI refuses malformed or unapproved request');
}
