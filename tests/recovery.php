<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSessions,PdoIdentity,AuthHttpApi};
$rc=$onResult['data']['recoveryCode'];
check(is_string($rc) && preg_match('/^[a-f0-9]{64}$/D',$rc)===1,'signup returns a one-time 256-bit recovery code');
$recoverySql=db()->prepare('SELECT token_hash FROM engine_account_recovery WHERE actor_id=?');
$recoverySql->execute([$onActor]);
check($recoverySql->fetchColumn()===hash('sha256',$rc),'only recovery token digest is stored');
$rcPost=fn(string $path,array $body,string $peer='203.0.113.10')=>$onApi->handle('POST',$path,'',$onJson($body),$peer);
$rcPayload=['login'=>$onData['login'],'code'=>$rc,'newPassword'=>'new public password for reset'];
check($rcPost('/v1/auth/recover',[...$rcPayload,'code'=>str_repeat('a',64)])[0]===401,'wrong recovery code denied');
check($rcPost('/v1/auth/recover',[...$rcPayload,'code'=>'invalid'])[0]===401,'malformed recovery code denied');
[$rcStatus,$rcBody]=$rcPost('/v1/auth/recover',$rcPayload);
check($rcStatus===200 && $rcBody['data']['sessionsRevoked']===true,'valid recovery resets password');
check((new PdoSessions(db(),$onClock))->resolve($onResult['data']['token'])===null,'password recovery revokes every prior session');
check((new PdoIdentity(db()))->authenticate($onData['login'],$onData['password'])===null,'old password no longer authenticates');
check((new PdoIdentity(db()))->authenticate($onData['login'],$rcPayload['newPassword'])===$onActor,'replacement password authenticates');
check($rcPost('/v1/auth/recover',$rcPayload)[0]===401,'spent recovery code cannot be replayed');
$newCode=$rcBody['data']['recoveryCode'];
check(is_string($newCode) && strlen($newCode)===64 && $newCode!==$rc,'recovery rotates code atomically');
$session=(new PdoSessions(db(),$onClock))->login($onData['login'],$rcPayload['newPassword']);
check($session!==null,'replacement password obtains fresh session');
[$wrongRotateStatus]=$onApi->handle('POST','/v1/auth/recovery/rotate','Bearer '.$session,$onJson(['password'=>'wrong current password for recovery']),'203.0.113.19');
check($wrongRotateStatus===403,'incorrect rotation password does not masquerade as expired bearer');
check((new PdoSessions(db(),$onClock))->resolve($session)===$onActor,'wrong rotation password preserves valid session');
[$rotateStatus,$rotateBody]=$onApi->handle('POST','/v1/auth/recovery/rotate','Bearer '.$session,$onJson(['password'=>$rcPayload['newPassword']]),'203.0.113.20');
check($rotateStatus===200 && $rotateBody['data']['recoveryCode']!==$newCode,'authenticated password verification rotates recovery code');
check((new PdoSessions(db(),$onClock))->resolve($session)===$onActor,'rotating recovery code does not silently log out session');
$rotatedCode=$rotateBody['data']['recoveryCode'];
check($rcPost('/v1/auth/recover',[...$rcPayload,'code'=>$newCode],'203.0.113.21')[0]===401,'rotated code becomes invalid');
// Concurrent reuse of the SAME valid code must allow exactly one password reset.
$children=[];
for($n=0;$n<4;$n++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/recovery-worker.php',$onData['login'],(string)$n],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Unable to create recovery worker');
    fwrite($pipes[0],$rotatedCode); fclose($pipes[0]);
    $children[]=[$proc,$pipes];
}
$wins=0;
foreach($children as [$proc,$pipes]) {
    $output=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0,'recovery process completed: '.$err);
    $status=(int)trim($output);
    check(in_array($status,[200,401],true),'parallel recovery accepted only known results');
    if($status===200) $wins++;
}
check($wins===1,'parallel one-time recovery code commits once');
check((new PdoSessions(db(),$onClock))->resolve($session)===null,'concurrent recovery revokes active session');
check((new PdoIdentity(db()))->authenticate($onData['login'],'concurrent recovery test password')===$onActor,'single concurrent winner changed password');


// Race: an old-password login must never survive the moment of password reset.
// A login that finishes first is deleted by reset; one starting later fails.
$resetRaceLogin='reset-login-race';
$resetRaceOld='old concurrent session password';
$resetRaceNew='new concurrent session password';
$resetRaceActor=(new PdoIdentity(db()))->register($resetRaceLogin,$resetRaceOld);
$recoveryService=new \Aeternum\Core\PdoRecovery(db(),$onClock);
$resetRaceCode=$recoveryService->issueIfMissing($resetRaceActor);
check($resetRaceCode!==null,'race fixture has single-use recovery code');
$loginWorkers=[];
for($n=0;$n<4;$n++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/recovery-login-worker.php',$resetRaceLogin],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Unable to spawn competing login');
    fwrite($pipes[0],$resetRaceOld.PHP_EOL); fclose($pipes[0]);
    $loginWorkers[]=[$proc,$pipes];
}
$raceNext=$recoveryService->reset($resetRaceLogin,$resetRaceCode,$resetRaceNew);
check($raceNext!==null && $raceNext!==$resetRaceCode,'password reset completes under concurrent login contention');
$liveSessions=new PdoSessions(db(),$onClock);
foreach($loginWorkers as [$proc,$pipes]) {
    $token=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0,'competing login completes: '.$err);
    check($token==='NONE' || preg_match('/^[a-f0-9]{64}$/D',$token)===1,'competing login result valid');
    if($token!=='NONE') check($liveSessions->resolve($token)===null,'old-password token cannot survive reset');
}
check((new PdoIdentity(db()))->authenticate($resetRaceLogin,$resetRaceOld)===null,'old password permanently rejected after concurrent reset');
check($liveSessions->login($resetRaceLogin,$resetRaceNew)!==null,'new password can create a post-reset session');

 
// The recovery limit is global across source IPs and cannot consume a valid
// code merely because the server has exhausted its verification budget.
$recoveryQuota=new \Aeternum\Core\PdoRateLimiter(db(),$onClock);
$recoveryGranted=0;
while($recoveryQuota->admit('auth:recovery-global',100,3600)) {
    if (++$recoveryGranted>100) throw new RuntimeException('Recovery global budget did not close');
}
$quotaRecovery=['login'=>$resetRaceLogin,'code'=>$raceNext,'newPassword'=>'recovery after global quota rollover'];
check($rcPost('/v1/auth/recover',$quotaRecovery,'203.0.113.77')[0]===429,
    'recovery global quota rejects another peer with still-valid code');
$onClock->advanceTo(3600);
[$recoveredAfterQuota,$recoveredAfterQuotaData]=$rcPost('/v1/auth/recover',$quotaRecovery,'203.0.113.77');
check($recoveredAfterQuota===200 && is_string($recoveredAfterQuotaData['data']['recoveryCode']??null),
    'recovery code is not consumed by throttling and works after quota reset');
check((new PdoIdentity(db()))->authenticate($resetRaceLogin,$quotaRecovery['newPassword'])===$resetRaceActor,
    'global recovery rate limiter preserves valid identity across a window boundary');
