<?php
declare(strict_types=1);
use Aeternum\Core\{PdoEmailActivation,FrozenClock,GamePackLoader,PdoIdentity};
(static function():void {
    $mail=new PdoEmailActivation(db(),new FrozenClock(48000));
    $claim=$mail->begin('email-activation-concurrent','concurrent-email-confirmation',
        'parallel email proof and activation','parallel-activation@example.org');
    check($claim!==null,'concurrent mailbox fixture inserted');
    $processes=[];
    for($i=0;$i<4;$i++){
        $p=proc_open([PHP_BINARY,__DIR__.'/email-activation-worker.php',(string)$i],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($p))throw new RuntimeException('Unable to launch activation worker');
        fwrite($pipes[0],$claim['code']);fclose($pipes[0]);
        $processes[]=[$p,$pipes];
    }
    $statuses=[];
    foreach($processes as [$p,$pipes]){
        $out=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        check(proc_close($p)===0 && in_array($out,['200','401','503'],true),
            'activation worker returned controlled status: '.$err);
        $statuses[]=(int)$out;
    }
    check(count(array_filter($statuses,static fn(int $status):bool=>$status===200))===1,
        'same mailbox code creates exactly one successful activation');
    $actor=(new PdoIdentity(db()))->authenticate('concurrent-email-confirmation','parallel email proof and activation');
    check($actor!==null,'concurrent activation uses only one identity');
    $s=db()->prepare("SELECT COUNT(*) FROM engine_onboarding WHERE actor_id=? AND status='ready'");
    $s->execute([$actor]);check((int)$s->fetchColumn()===1,'only one durable ready claim');
    $s=db()->prepare('SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id=? AND account_id=?');
    $s->execute(['email-activation-concurrent',$actor]);
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
    check((int)$s->fetchColumn()===count($pack->resources),
        'concurrent activation cannot duplicate initial resources');
})();
