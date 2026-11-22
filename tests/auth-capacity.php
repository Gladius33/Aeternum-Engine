<?php
declare(strict_types=1);
use Aeternum\Core\{PdoIdentity,PdoSessions,FrozenClock,AuthHttpApi};
$authJson=static fn(array $payload):string=>json_encode($payload,JSON_THROW_ON_ERROR);

// Authentication and signup now share the SAME bounded Argon2id slot pool.
// Login, recovery, rotation and password change must all reject an overload
// before computing password hashes, and a normal sign-out must still work.
$authSaturationActor=(new PdoIdentity(db()))->register('auth-saturation-user','auth saturation original strong password');
// The preceding real-time operator CLI advances the durable rate-limit floor
// near wall time. Use that same real clock, not an arbitrary simulated epoch;
// otherwise admission correctly rejects a rollback with 429 before slot checks.
$authSaturationClock=new \Aeternum\Core\SystemClock();
$authSaturationApi=new AuthHttpApi(db(),$authSaturationClock,120,[],false,30,2);
$authOriginalPassword='auth saturation original strong password';
$authNewPassword='auth saturation new secure password';
$authCode=(new \Aeternum\Core\PdoRecovery(db(),$authSaturationClock))->issueIfMissing($authSaturationActor);
check(is_string($authCode) && strlen($authCode)===64,'auth saturation fixture has independent recovery secret');
$authBearer=(new PdoSessions(db(),$authSaturationClock))->login('auth-saturation-user',$authOriginalPassword);
check(is_string($authBearer),'auth saturation fixture has authenticated bearer');
$authHolders=[];
for($i=0;$i<2;$i++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/signup-slot-worker.php','2'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Unable to start auth resource worker');
    fclose($pipes[0]);
    check(trim((string)fgets($pipes[1]))==='locked','auth resource worker acquired the shared slot');
    $authHolders[]=[$proc,$pipes];
}
$authLoginBody=$authJson(['login'=>'auth-saturation-user','password'=>$authOriginalPassword]);
$authResetBody=$authJson(['login'=>'auth-saturation-user','code'=>$authCode,'newPassword'=>$authNewPassword]);
$authChangeBody=$authJson(['currentPassword'=>$authOriginalPassword,'newPassword'=>$authNewPassword]);
$authRotateBody=$authJson(['password'=>$authOriginalPassword]);
foreach([
    ['/v1/auth/login','',$authLoginBody,'192.0.2.240'],
    ['/v1/auth/recover','',$authResetBody,'192.0.2.241'],
    ['/v1/auth/password/change','Bearer '.$authBearer,$authChangeBody,'192.0.2.242'],
    ['/v1/auth/recovery/rotate','Bearer '.$authBearer,$authRotateBody,'192.0.2.243']
] as [$route,$bearer,$payload,$source]) {
    [$busy,$response]=$authSaturationApi->handle('POST',$route,$bearer,$payload,$source);
    check($busy===503 && ($response['error']??null)==='authentication_busy',
        'expensive identity operation refuses saturation '.$route);
}
check((new PdoIdentity(db()))->authenticate('auth-saturation-user',$authOriginalPassword)===$authSaturationActor,
    'credential overload never changes existing password');
check((new PdoSessions(db(),$authSaturationClock))->resolve($authBearer)===$authSaturationActor,
    'overload never invalidates existing bearer');
check($authSaturationApi->handle('POST','/v1/auth/logout','Bearer '.$authBearer,'','192.0.2.244')[0]===200,
    'logout remains available while all credential slots are saturated');
check((new PdoSessions(db(),$authSaturationClock))->resolve($authBearer)===null,
    'saturated-server logout actually revokes the bearer');
foreach($authHolders as [$proc,$pipes]) {
    $rest=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0,'auth resource worker safely released slot: '.$err.$rest);
}
[$authLoginStatus,$authLoginResponse]=$authSaturationApi->handle('POST','/v1/auth/login','',$authLoginBody,'192.0.2.245');
check($authLoginStatus===200 && (new PdoSessions(db(),$authSaturationClock))->resolve($authLoginResponse['data']['token'])===$authSaturationActor,
    'login succeeds only after shared hash capacity returns');
[$authResetStatus,$authResetResponse]=$authSaturationApi->handle('POST','/v1/auth/recover','',$authResetBody,'192.0.2.246');
check($authResetStatus===200 && strlen($authResetResponse['data']['recoveryCode']??'')===64,
    'recovery code remained usable after capacity rejection');
check((new PdoIdentity(db()))->authenticate('auth-saturation-user',$authOriginalPassword)===null,
    'old password rejected after the successful post-saturation recovery');
check((new PdoIdentity(db()))->authenticate('auth-saturation-user',$authNewPassword)===$authSaturationActor,
    'post-saturation recovered password persists');
