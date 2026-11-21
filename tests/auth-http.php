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
for ($i=0;$i<10;$i++) check($authApi->handle('POST',$loginPath,'','{}','192.0.2.2')[0]===400,'bounded malformed request');
check($authApi->handle('POST',$loginPath,'',$credentials,'192.0.2.2')[0]===429,'peer throttled');
$authClock->advanceTo(360);
check($authApi->handle('POST',$loginPath,'',$credentials,'192.0.2.2')[0]===200,'peer window resets');
