<?php
declare(strict_types=1);
use Aeternum\Core\{AuthHttpApi,FrozenClock};
$globalClock=new FrozenClock(600);
$globalApi=new AuthHttpApi(db(),$globalClock,2);
$globalPath='/v1/auth/login';
check($globalApi->handle('POST',$globalPath,'','{','192.0.2.9')[0]===400,'malformed login does not acquire verification quota');
$globalWrong=json_encode(['login'=>'unknown-player-a','password'=>'not the password'],JSON_THROW_ON_ERROR);
check($globalApi->handle('POST',$globalPath,'',$globalWrong,'192.0.2.10')[0]===401,'unknown login consumes global verification quota');
$globalWrong=json_encode(['login'=>'unknown-player-b','password'=>'not the password'],JSON_THROW_ON_ERROR);
check($globalApi->handle('POST',$globalPath,'',$globalWrong,'192.0.2.11')[0]===401,'separate IP and login share global quota');
$globalGood=json_encode(['login'=>'test-player','password'=>'correct horse battery staple'],JSON_THROW_ON_ERROR);
check((new AuthHttpApi(db(),$globalClock,2))->handle('POST',$globalPath,'',$globalGood,'192.0.2.12')[0]===429,'global quota persists across connections');
check($globalApi->handle('POST','/v1/auth/logout','Bearer '.str_repeat('a',64),'','192.0.2.13')[0]===200,'logout is not blocked by verification quota');
$globalClock->advanceTo(660);
check($globalApi->handle('POST',$globalPath,'',$globalGood,'192.0.2.12')[0]===200,'global quota rolls over at exact boundary');
rejects(fn()=> new AuthHttpApi(db(),$globalClock,0),InvalidArgumentException::class,'global verification limit must be positive');
rejects(fn()=> new AuthHttpApi(db(),$globalClock,100001),InvalidArgumentException::class,'global verification limit upper bound');
$globalChildren=[];
for($i=0;$i<4;$i++) {
    $p=proc_open([PHP_BINARY,__DIR__.'/auth-global-worker.php',(string)$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p)) throw new RuntimeException('Cannot spawn global auth worker');
    fclose($pipes[0]); $globalChildren[]=[$p,$pipes];
}
$globalStatuses=[];
foreach($globalChildren as [$p,$pipes]) {
    $out=trim(stream_get_contents($pipes[1])); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if(proc_close($p)!==0 || !in_array($out,['401','429'],true)) throw new RuntimeException('Global auth worker failed: '.$err);
    $globalStatuses[]=(int)$out;
}
sort($globalStatuses);
check($globalStatuses===[401,401,429,429],'global verification quota enforced across four independent peers and processes');
