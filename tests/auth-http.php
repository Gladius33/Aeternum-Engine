<?php
declare(strict_types=1);
use Aeternum\Core\{AuthHttpApi,FrozenClock,PdoSessions};
$authClock=new FrozenClock(300);
$authApi=new AuthHttpApi(db(),$authClock);
$loginPath='/v1/auth/login';
$credentials=json_encode(['login'=>'test-player','password'=>'correct horse battery staple'],JSON_THROW_ON_ERROR);
check($authApi->handle('GET',$loginPath,'',$credentials,'192.0.2.1')[0]===405,'auth POST only');
check($authApi->handle('POST',$loginPath,'','{','192.0.2.1')[0]===400,'malformed JSON rejected');
[$status,$body]=$authApi->handle('POST',$loginPath,'',$credentials,'192.0.2.1');
check($status===200,'HTTP login succeeds');
$authToken=$body['data']['token'];
check((new PdoSessions(db(),$authClock))->resolve($authToken)===$actor,'HTTP token resolves');
check($authApi->handle('POST','/v1/auth/logout','Bearer '.$authToken,'','192.0.2.1')[0]===200,'HTTP logout');
check((new PdoSessions(db(),$authClock))->resolve($authToken)===null,'HTTP logout revokes');

(static function () use ($authApi,$authClock,$actor): void {
    $sessions=new PdoSessions(db(),$authClock);
    $first=$sessions->login('test-player','correct horse battery staple');
    $second=$sessions->login('test-player','correct horse battery staple');
    $foreign=(new \Aeternum\Core\PdoIdentity(db()))->register('logout-other-user','other separate password phrase');
    $foreignToken=$sessions->login('logout-other-user','other separate password phrase');
    check($first!==null && $second!==null && $foreignToken!==null,'global logout fixtures have independent live sessions');
    check($authApi->handle('GET','/v1/auth/logout/all','Bearer '.$first,'','192.0.2.10')[0]===405,'global logout accepts POST only');
    check($authApi->handle('POST','/v1/auth/logout/all','Bearer '.str_repeat('a',64),'','192.0.2.10')[0]===401,'global logout rejects unknown bearer');
    [$code,$body]=$authApi->handle('POST','/v1/auth/logout/all','Bearer '.$first,'','192.0.2.10');
    check($code===200 && $body['data']['revokedAll']===true,'global logout accepts active bearer');
    check($sessions->resolve($first)===null && $sessions->resolve($second)===null,'global logout revokes every session for its actor');
    check($sessions->resolve($foreignToken)===$foreign && $foreign!==$actor,'global logout cannot revoke another actor session');
    check($authApi->handle('POST','/v1/auth/logout/all','Bearer '.$first,'','192.0.2.11')[0]===401,'stale global logout bearer is denied');
    $newSession=$sessions->login('test-player','correct horse battery staple');
    check($newSession!==null && $sessions->resolve($newSession)===$actor,'password remains valid after all devices sign out');
    rejects(fn()=> $sessions->revokeAllForActor('invalid'),InvalidArgumentException::class,'global logout rejects invalid actor identifier');
})();

for ($i=0;$i<10;$i++) check($authApi->handle('POST',$loginPath,'','{}','192.0.2.2')[0]===400,'bounded malformed request');
check($authApi->handle('POST',$loginPath,'',$credentials,'192.0.2.2')[0]===429,'peer throttled');
$authClock->advanceTo(360);
check($authApi->handle('POST',$loginPath,'',$credentials,'192.0.2.2')[0]===200,'peer window resets');
