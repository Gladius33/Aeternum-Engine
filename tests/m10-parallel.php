<?php
declare(strict_types=1);
use Aeternum\Core\{PdoIdentity,PdoAdminConsole,FrozenClock};
(static function():void {
    $pdo=db();$world='m10-console-a';
    $actor=(new PdoIdentity($pdo))->authenticate('m10-cosmetic-user',
        'M10 isolated testing password');
    if(!is_string($actor))throw new RuntimeException('Missing M10 concurrent fixture');
    $children=[];
    for($i=0;$i<4;$i++) {
        $process=proc_open([PHP_BINARY,__DIR__.'/m10-event-worker.php',$world,$actor,'m10-race-01'],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Missing concurrent DB worker');
        $children[]=[$process,$pipes];
    }
    foreach($children as [, $pipes]) {
        fwrite($pipes[0],"go\n");fclose($pipes[0]);
    }
    $applied=0;$replayed=0;
    foreach($children as [$process,$pipes]) {
        $out=trim((string)stream_get_contents($pipes[1]));
        $err=trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0)throw new RuntimeException('M10 child: '.$err);
        if($out==='applied')++$applied;
        elseif($out==='replayed')++$replayed;
        else throw new RuntimeException('Unknown M10 worker outcome '.$out);
    }
    check($applied===1 && $replayed===3,
        'M10 four concurrent test payments produce one action and three safe replays');
    $q=$pdo->prepare('SELECT COUNT(*) FROM engine_test_payment_events WHERE world_id=? AND event_id=?');
    $q->execute([$world,'m10-race-01']);
    check((int)$q->fetchColumn()===1,'M10 exactly one durable simulated event under race');
    $q=$pdo->prepare('SELECT COUNT(*) FROM engine_admin_audit WHERE world_id=? AND request_id=?');
    $q->execute([$world,'m10-race-01']);
    check((int)$q->fetchColumn()===1,'M10 exactly one attributed operator audit under race');
    check((new PdoAdminConsole($pdo,$world,new FrozenClock(9100)))
        ->testPayment('m10-manager','m10-race-01',$actor,'profile_badge','grant')===false,
        'M10 replay remains idempotent from a later new connection');
})();
