<?php
declare(strict_types=1);
use Aeternum\Core\{AuthHttpApi,FrozenClock,GamePackLoader,PdoIdentity,PdoRecovery,PdoSessions};

// Keep integration fixtures local; later suites rely on the original $actor.
(static function (): void {

$changeClock=new FrozenClock(30200);
$changePack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$changeApi=new AuthHttpApi(db(),$changeClock,120,['change-password-world'=>$changePack]);
$changeLogin='password-change-public';
$oldPassword='first lengthy public password';
$newPassword='second lengthy public password';
$changeBody=static fn(array $payload):string=>json_encode($payload,JSON_THROW_ON_ERROR);
$changeRequest=static fn(string $token,array $payload,string $peer='192.0.2.201'):array=>
    $changeApi->handle('POST','/v1/auth/password/change','Bearer '.$token,$changeBody($payload),$peer);
[$signedStatus,$signedBody]=$changeApi->handle('POST','/v1/auth/register','',
    $changeBody(['login'=>$changeLogin,'password'=>$oldPassword,'world'=>'change-password-world']),'192.0.2.202');
check($signedStatus===201,'password change fixture is a genuinely enrolled public player');
$actor=$signedBody['data']['account'];
$initialCode=$signedBody['data']['recoveryCode'];
$firstToken=$signedBody['data']['token'];
$changeSessions=new PdoSessions(db(),$changeClock);
$secondToken=$changeSessions->login($changeLogin,$oldPassword);
check($secondToken!==null && $firstToken!==$secondToken,'same public player has two independent sessions');
$payload=['currentPassword'=>$oldPassword,'newPassword'=>$newPassword];
check($changeApi->handle('GET','/v1/auth/password/change','', '', '192.0.2.203')[0]===405,'password change accepts POST only');
check($changeRequest(str_repeat('a',64),$payload,'192.0.2.203')[0]===401,'unknown bearer cannot change password');
check($changeRequest($firstToken,['currentPassword'=>$oldPassword],'192.0.2.203')[0]===400,'password change rejects missing new password');
check($changeRequest($firstToken,[...$payload,'unexpected'=>'x'],'192.0.2.203')[0]===400,'password change rejects extra fields');
check($changeRequest($firstToken,[...$payload,'newPassword'=>'short'],'192.0.2.203')[0]===400,'weak replacement password rejected');
check($changeRequest($firstToken,[...$payload,'newPassword'=>$oldPassword],'192.0.2.203')[0]===400,'no-op password reuse rejected');
check($changeRequest($firstToken,[...$payload,'currentPassword'=>'incorrect lengthy password'],'192.0.2.203')[0]===403,'wrong current password rejected without invalidating session');
check($changeSessions->resolve($firstToken)===$actor && $changeSessions->resolve($secondToken)===$actor,'failed changes preserve sessions and current credentials');
[$changedStatus,$changedBody]=$changeRequest($firstToken,$payload,'192.0.2.204');
check($changedStatus===200 && $changedBody['data']['sessionsRevoked']===true,'password change succeeds with current password');
$newCode=$changedBody['data']['recoveryCode'];
check(is_string($newCode) && preg_match('/^[a-f0-9]{64}$/D',$newCode)===1 && $newCode!==$initialCode,'password change rotates one-time recovery secret');
check($changeSessions->resolve($firstToken)===null && $changeSessions->resolve($secondToken)===null,'all previous sessions are revoked in same transaction');
$identity=new PdoIdentity(db());
check($identity->authenticate($changeLogin,$oldPassword)===null,'old password invalid after change');
check($identity->authenticate($changeLogin,$newPassword)===$actor,'new password grants identity');
check($changeRequest($firstToken,['currentPassword'=>$newPassword,'newPassword'=>'third lengthy public password'],'192.0.2.205')[0]===401,'revoked bearer cannot make further changes');
$codeRow=db()->prepare('SELECT token_hash FROM engine_account_recovery WHERE actor_id=?');
$codeRow->execute([$actor]);
check($codeRow->fetchColumn()===hash('sha256',$newCode),'database stores only digest of new recovery secret');
[$obsoleteStatus]=$changeApi->handle('POST','/v1/auth/recover','',
    $changeBody(['login'=>$changeLogin,'code'=>$initialCode,'newPassword'=>'stale offline password rejected']),'192.0.2.206');
check($obsoleteStatus===401,'old recovery code cannot undo a password change');
[$resetStatus,$resetData]=$changeApi->handle('POST','/v1/auth/recover','',
    $changeBody(['login'=>$changeLogin,'code'=>$newCode,'newPassword'=>'third lengthy public password']),'192.0.2.207');
check($resetStatus===200 && $resetData['data']['sessionsRevoked']===true,'fresh code restores changed account');
check($identity->authenticate($changeLogin,'third lengthy public password')===$actor,'fresh recovery code rotates password again');

// Four bearer sessions compete to change ONE identity's password. The identity
// row lock must allow exactly one commit; all pre-change bearers are revoked.
$raceLogin='password-change-race';
$raceOld='first password for racing users';
$raceNew='replacement password for racing users';
$raceActor=$identity->register($raceLogin,$raceOld);
$raceRecovery=new PdoRecovery(db(),$changeClock);
$raceCode=$raceRecovery->issueIfMissing($raceActor);
check($raceCode!==null,'race has its own recovery credential');
$raceTokens=[];
for($i=0;$i<4;$i++) {
    $token=$changeSessions->login($raceLogin,$raceOld);
    check($token!==null,'parallel changer has a distinct active bearer');
    $raceTokens[]=$token;
}
$workers=[];
foreach($raceTokens as $i=>$token) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/password-change-worker.php',(string)$i],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Could not launch password change worker');
    fwrite($pipes[0],$changeBody(['token'=>$token,'currentPassword'=>$raceOld,'newPassword'=>$raceNew]));
    fclose($pipes[0]);
    $workers[]=[$proc,$pipes];
}
$winners=0;
$winnerCode=null;
foreach($workers as [$proc,$pipes]) {
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0,'parallel password changer terminated: '.$errors);
    $result=json_decode($output,true,8,JSON_THROW_ON_ERROR);
    check(in_array($result['status'],[200,401,403],true),'parallel change result is success, revoked bearer or lost password race');
    if($result['status']===200) { $winners++; $winnerCode=$result['recoveryCode']; }
}
check($winners===1,'only one competing password change commits');
foreach($raceTokens as $token) check($changeSessions->resolve($token)===null,'racing old bearer cannot survive change');
check($identity->authenticate($raceLogin,$raceOld)===null && $identity->authenticate($raceLogin,$raceNew)===$raceActor,'concurrent change leaves one valid new password');
check(is_string($winnerCode) && strlen($winnerCode)===64 && $winnerCode!==$raceCode,'single winning changer receives rotated recovery code');
$codeRow->execute([$raceActor]);
check($codeRow->fetchColumn()===hash('sha256',$winnerCode),'concurrent change commits new recovery code with password');
})();
