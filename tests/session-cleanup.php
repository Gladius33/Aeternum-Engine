<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSessions,FrozenClock};
$cleanupClock=new FrozenClock(5000);
$cleanupPdo=db();
$cleanupSvc=new PdoSessions($cleanupPdo,$cleanupClock);
// Clear only previously expired fixture sessions; the database is disposable.
$cleanupSvc->purgeExpired(1000);
$cleanupTokens=[];
for($i=0;$i<5;$i++) $cleanupTokens[]=$cleanupSvc->login('test-player','correct horse battery staple',60);
$cleanupLive=$cleanupSvc->login('test-player','correct horse battery staple',120);
$cleanupRevoked=$cleanupSvc->login('test-player','correct horse battery staple',120);
$cleanupSvc->revoke($cleanupRevoked);
$cleanupClock->advanceTo(5059);
check($cleanupSvc->purgeExpired(2)===0,'no cleanup before exact expiry');
$cleanupClock->advanceTo(5060);
check($cleanupSvc->purgeExpired(2)===2,'bounded expiry cleanup');
check($cleanupSvc->resolve($cleanupLive)===$actor,'live session preserved');
$cleanupCount=$cleanupPdo->prepare('SELECT COUNT(*) FROM engine_sessions WHERE token_hash=?');
$cleanupCount->execute([hash('sha256',$cleanupRevoked)]);
check((int)$cleanupCount->fetchColumn()===1,'unexpired revoked session retained until expiry');
$cleanupChildren=[];
for($i=0;$i<2;$i++) {
    $p=proc_open([PHP_BINARY,__DIR__.'/session-cleanup-worker.php','5060'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p)) throw new RuntimeException('Cannot spawn session cleanup worker');
    fclose($pipes[0]); $cleanupChildren[]=[$p,$pipes];
}
$cleanupTotal=0;
foreach($cleanupChildren as [$p,$pipes]) {
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if(proc_close($p)!==0 || !preg_match('/^[0-2]$/D',trim($out))) throw new RuntimeException('Cleanup subprocess failed: '.$err);
    $cleanupTotal+=(int)trim($out);
}
check($cleanupTotal===3,'concurrent cleanup deletes each remaining expired row once');
check($cleanupSvc->purgeExpired(1)===0,'cleanup retry is a no-op');
foreach($cleanupTokens as $cleanupToken) check($cleanupSvc->resolve($cleanupToken)===null,'expired session remains inaccessible');
check($cleanupSvc->resolve($cleanupLive)===$actor,'concurrent cleanup preserves live session');
rejects(fn()=> $cleanupSvc->purgeExpired(0),InvalidArgumentException::class,'invalid zero cleanup limit');
rejects(fn()=> $cleanupSvc->purgeExpired(1001),InvalidArgumentException::class,'invalid excessive cleanup limit');
$cleanupPdo->beginTransaction();
rejects(fn()=> $cleanupSvc->purgeExpired(),LogicException::class,'cleanup refuses outer transaction');
$cleanupPdo->rollBack();
$cleanupClock->advanceTo(5120);
check($cleanupSvc->purgeExpired()===2,'live and revoked rows removed after their expiry');
