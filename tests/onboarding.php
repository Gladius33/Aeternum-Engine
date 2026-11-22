<?php
declare(strict_types=1);
use Aeternum\Core\{AuthHttpApi,PdoOnboarding,GamePackLoader,PdoSessions,PdoScheduler,PdoIdentity,FrozenClock,PdoMemberships};

$onClock=new FrozenClock(1200);
$onWorld='public-test-world';
$onPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$onApi=new AuthHttpApi(db(),$onClock,120,[$onWorld=>$onPack,'other-public-world'=>$onPack]);
$onPath='/v1/auth/register';
$onData=['login'=>'public-signup','password'=>'public signup test password','world'=>$onWorld];
$onJson=static fn(array $x):string=>json_encode($x,JSON_THROW_ON_ERROR);
$onPost=fn(array $payload,string $peer='198.51.100.11')=>$onApi->handle('POST',$onPath,'',$onJson($payload),$peer);
check((new AuthHttpApi(db(),$onClock))->handle('POST',$onPath,'',$onJson($onData),'198.51.100.1')[0]===404,'public enrollment closed by default');
check($onApi->handle('GET',$onPath,'',$onJson($onData),'198.51.100.1')[0]===405,'public signup POST only');
check($onApi->handle('POST',$onPath,'','{','198.51.100.1')[0]===400,'public malformed JSON rejected');
check($onPost([...$onData,'unexpected'=>true],'198.51.100.1')[0]===400,'public signup rejects extra fields');
check($onPost(['login'=>$onData['login'],'password'=>'short','world'=>$onWorld],'198.51.100.1')[0]===400,'public signup rejects weak password');
check($onPost([...$onData,'world'=>'unknown-world'],'198.51.100.1')[0]===404,'public signup denies unconfigured world');
[$onStatus,$onResult]=$onPost($onData);
check($onStatus===201,'new public registration creates player');
$onActor=$onResult['data']['account'];
check(preg_match('/^[a-f0-9]{64}$/D',$onActor)===1,'public actor ID is server-generated');
check((new PdoSessions(db(),$onClock))->resolve($onResult['data']['token'])===$onActor,'new user has a genuine session');
check((new PdoIdentity(db()))->authenticate($onData['login'],$onData['password'])===$onActor,'public signup authenticates persisted identity');
$onGet=static function(string $actor):array {
    $st=db()->prepare('SELECT world_id,status FROM engine_onboarding WHERE actor_id=?');
    $st->execute([$actor]);
    return $st->fetch(PDO::FETCH_ASSOC);
};
check($onGet($onActor)===['world_id'=>$onWorld,'status'=>'ready'],'public provisioning claim durable ready');
$onRules=$onPack->rules();
$onScheduler=new PdoScheduler(db(),$onWorld,$onClock,$onRules);
foreach($onPack->resources as $resource) {
    check($onScheduler->balanceForActor($onActor,$onActor,$resource->id,$onRules->commands)->stock===$resource->initialStock,'new player has seeded '.$resource->id);
}
$onLedger=db()->prepare('SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id=? AND account_id=?');
$onLedger->execute([$onWorld,$onActor]); $onBefore=(int)$onLedger->fetchColumn();
[$onRetryStatus,$onRetry]=$onPost($onData);
check($onRetryStatus===200 && $onRetry['data']['account']===$onActor,'same credentials safely resume same identity');
$onLedger->execute([$onWorld,$onActor]);
check((int)$onLedger->fetchColumn()===$onBefore,'signup retry never duplicates initial grants');
check($onPost([...$onData,'password'=>'completely wrong password'],'198.51.100.12')[0]===409,'wrong password cannot resume identity');
check($onPost([...$onData,'world'=>'other-public-world'],'198.51.100.12')[0]===409,'existing signup cannot silently join another world');
check($onPost(['login'=>'test-player','password'=>'correct horse battery staple','world'=>$onWorld],'198.51.100.12')[0]===409,'operator-provisioned account cannot enroll through public registration');
$missing=db()->prepare('SELECT COUNT(*) FROM engine_memberships WHERE world_id=? AND actor_id=?');
$missing->execute(['other-public-world',$onActor]);
check((int)$missing->fetchColumn()===0,'foreign world membership never created');

// Recovery from a committed signup claim whose multi-step provisioning was interrupted.
$onPartial='public-partial';
$onPartialPass='another strong test password';
$onPartialActor=(new PdoIdentity(db()))->register($onPartial,$onPartialPass);
$insert=db()->prepare("INSERT INTO engine_onboarding(actor_id,world_id,status,created_at) VALUES (?,?,'pending',?)");
$insert->execute([$onPartialActor,$onWorld,1200]);
check((new PdoSessions(db(),$onClock))->login($onPartial,$onPartialPass)===null,'pending public identity denied a bearer');
(new PdoMemberships(db(),$onWorld))->grant($onPartialActor); // interrupted immediately after grant
[$onRecoveredStatus,$onRecovered]=$onPost(['login'=>$onPartial,'password'=>$onPartialPass,'world'=>$onWorld],'198.51.100.13');
check($onRecoveredStatus===200 && $onRecovered['data']['account']===$onPartialActor,'replay resumes partially provisioned identity');
check($onGet($onPartialActor)['status']==='ready','interrupted claim completes to ready');
check((new PdoSessions(db(),$onClock))->login($onPartial,$onPartialPass)!==null,'resumed public identity can now log in');
foreach($onPack->resources as $resource) {
    check($onScheduler->balanceForActor($onPartialActor,$onPartialActor,$resource->id,$onRules->commands)->stock===$resource->initialStock,'recovered player has initial '.$resource->id);
}
// Recovery from a later interruption, after ownership and the FIRST resource
// were already committed, must not grant it again.
$onLateActor=(new PdoIdentity(db()))->register('public-later','a later signup recovery password');
$insert->execute([$onLateActor,$onWorld,1200]);
$onLateMembership=new PdoMemberships(db(),$onWorld);
$onLateMembership->grant($onLateActor);
$onLateMembership->assign($onLateActor,$onLateActor);
$onLateEntities=new \Aeternum\Core\PdoEntities(db(),$onWorld);
$onLateEntities->addLocation($onLateActor);
$onLateEntities->provision($onLateActor,$onLateActor,$onLateActor,'settlement');
$onScheduler->executeForActor($onLateActor,
    new \Aeternum\Core\Command($onWorld,'provision:wood','economy.open',['account'=>$onLateActor,'resource'=>'wood']),
    $onRules->commands);
$onLedger->execute([$onWorld,$onLateActor]);
check((int)$onLedger->fetchColumn()===1,'injected interruption after one initial credit');
[$onLateStatus,$onLateResult]=$onPost(['login'=>'public-later','password'=>'a later signup recovery password','world'=>$onWorld],'198.51.100.24');
check($onLateStatus===200 && $onLateResult['data']['account']===$onLateActor,'signup resumes interrupted second-resource provisioning');
$onLedger->execute([$onWorld,$onLateActor]);
check((int)$onLedger->fetchColumn()===count($onPack->resources),'late restart never duplicates first resource');
check($onGet($onLateActor)['status']==='ready','late interrupted claim ready');
for($n=0;$n<6;$n++) {
    $onPost(['login'=>'bad-'.$n,'password'=>'strong test password','world'=>$onWorld],'198.51.100.18');
}
check($onPost(['login'=>'last','password'=>'strong test password','world'=>$onWorld],'198.51.100.18')[0]===429,'public signup peer limiter enforces hourly budget');

// Four independent processes concurrently retry the same new signup identity.
$raceWorld='public-concurrent-world';
$racePack=$onPack;
// Workers race to initialize the same entirely absent world; no operator bootstrapping.
$workers=[];
for($i=0;$i<4;$i++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/onboarding-worker.php',$raceWorld,(string)$i],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Unable to spawn signup worker');
    fclose($pipes[0]); $workers[]=[$proc,$pipes];
}
$accounts=[];
foreach($workers as [$proc,$pipes]) {
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit=proc_close($proc);
    check($exit===0,'concurrent signup worker finished: '.$err);
    $entry=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    check(in_array($entry['status'],[200,201],true),'concurrent signup received success');
    $accounts[]=$entry['account'];
}
check(count(array_unique($accounts))===1,'four processes created one identity');
check($onGet($accounts[0])===['world_id'=>$raceWorld,'status'=>'ready'],'concurrent identity fully provisioned');
$ledger=db()->prepare('SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id=? AND account_id=?');
$ledger->execute([$raceWorld,$accounts[0]]);
check((int)$ledger->fetchColumn()===count($onPack->resources),'concurrent signup grants resources once');


// Per-world admission quota is durable, independent across approved worlds,
// and cannot be bypassed by rotating the caller IP address.
$capWorld='public-capacity-world';
$capOther='public-capacity-other';
$capApi=new AuthHttpApi(db(),new FrozenClock(8200),120,[$capWorld=>$onPack,$capOther=>$onPack],false,2);
for($i=1;$i<=2;$i++) {
    [$code,$out]=$capApi->handle('POST',$onPath,'',$onJson([
        'login'=>'cap-user-'.$i,'password'=>'capacity user long password','world'=>$capWorld
    ]),'198.51.100.'.(70+$i));
    check($code===201 && isset($out['data']['account']),'world cap admits its first two complete players');
}
check($capApi->handle('POST',$onPath,'',$onJson([
    'login'=>'cap-overflow','password'=>'capacity user long password','world'=>$capWorld
]),'198.51.100.73')[0]===429,'third separate peer cannot exceed world cap');
check($capApi->handle('POST',$onPath,'',$onJson([
    'login'=>'cap-isolated','password'=>'capacity user long password','world'=>$capOther
]),'198.51.100.74')[0]===201,'one world quota does not block another world');
rejects(fn()=>new AuthHttpApi(db(),$onClock,120,[$capWorld=>$onPack],false,0),InvalidArgumentException::class,'world signup quota cannot be disabled by zero');
rejects(fn()=>new AuthHttpApi(db(),$onClock,120,[$capWorld=>$onPack],false,100001),InvalidArgumentException::class,'world signup quota has upper bound');

// Multiple independent PHP processes contend on the exact same real MariaDB
// bucket; only two fully provisioned accounts may be committed.
$capRaceWorld='public-capacity-concurrent';
$capWorkers=[];
for($i=0;$i<4;$i++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/onboarding-capacity-worker.php',$capRaceWorld,(string)$i],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Unable to start capacity worker');
    fclose($pipes[0]); $capWorkers[]=[$proc,$pipes];
}
$capStatuses=[];
foreach($capWorkers as [$proc,$pipes]) {
    $out=trim(stream_get_contents($pipes[1])); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($proc)===0 && in_array($out,['201','429'],true),'capacity worker completed normally: '.$err);
    $capStatuses[]=(int)$out;
}
sort($capStatuses);
check($capStatuses===[201,201,429,429],'world quota is atomic under four concurrent separate peers');
$capCount=db()->prepare('SELECT COUNT(*) FROM engine_onboarding WHERE world_id=?');
$capCount->execute([$capRaceWorld]);
check((int)$capCount->fetchColumn()===2,'rejected capacity race produces no orphaned accounts');


// A database-scoped semaphore bounds concurrent Argon2id hashes and world
// provisioning across PHP workers. Saturation is a retryable 503, not a half
// created identity; slots are freed on exit or a dead DB connection.
$signupSlotWorld='signup-slot-lab';
$signupSlots=new \Aeternum\Core\PdoSignupSlots(db(),2);
rejects(fn()=>new \Aeternum\Core\PdoSignupSlots(db(),0),InvalidArgumentException::class,'signup slot lower bound enforced');
rejects(fn()=>new \Aeternum\Core\PdoSignupSlots(db(),33),InvalidArgumentException::class,'signup slot upper bound enforced');
rejects(fn()=> $signupSlots->run(static function(){throw new DomainException('injected');}),
    DomainException::class,'slot callback error propagates');
check($signupSlots->run(static fn()=>42)===42,'failed callback releases signup slot for retry');
$slotWorkers=[];
for($i=0;$i<2;$i++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/signup-slot-worker.php','2'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Unable to start signup slot worker');
    fclose($pipes[0]);
    $ready=trim((string)fgets($pipes[1]));
    check($ready==='locked','separate process acquired signup slot');
    $slotWorkers[]=[$proc,$pipes];
}
$slotApi=new AuthHttpApi(db(),new FrozenClock(23000),120,[$signupSlotWorld=>$onPack],false,30,2);
$slotData=['login'=>'signup-slot-retry','password'=>'signup slot password for retry','world'=>$signupSlotWorld];
[$busyStatus,$busyBody]=$slotApi->handle('POST',$onPath,'',$onJson($slotData),'192.0.2.232');
check($busyStatus===503 && ($busyBody['error']??null)==='registration_busy','full PHP signup pool fails closed and retryably');
$slotExisting=db()->prepare('SELECT COUNT(*) FROM engine_identities WHERE login=?');
$slotExisting->execute([$slotData['login']]);
check((int)$slotExisting->fetchColumn()===0,'slot rejection creates no identity');
foreach($slotWorkers as [$proc,$pipes]) {
    $rest=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($proc)===0,'slot worker released its connection and lock: '.$err.$rest);
}
[$retryStatus,$retryBody]=$slotApi->handle('POST',$onPath,'',$onJson($slotData),'192.0.2.232');
check($retryStatus===201 && preg_match('/^[a-f0-9]{64}$/D',$retryBody['data']['account']??'')===1,
    'new signup succeeds after capacity is released');
check((new PdoSessions(db(),new FrozenClock(23000)))->resolve($retryBody['data']['token'])===$retryBody['data']['account'],
    'signup completes a real bearer session under released slot capacity');


// Authentication and signup now share the SAME bounded Argon2id slot pool.
// Login, recovery, rotation and password change must all reject an overload
// before computing password hashes, and a normal sign-out must still work.
$authSaturationActor=(new PdoIdentity(db()))->register('auth-saturation-user','auth saturation original strong password');
$authSaturationClock=new FrozenClock(800000);
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
$authLoginBody=$onJson(['login'=>'auth-saturation-user','password'=>$authOriginalPassword]);
$authResetBody=$onJson(['login'=>'auth-saturation-user','code'=>$authCode,'newPassword'=>$authNewPassword]);
$authChangeBody=$onJson(['currentPassword'=>$authOriginalPassword,'newPassword'=>$authNewPassword]);
$authRotateBody=$onJson(['password'=>$authOriginalPassword]);
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
