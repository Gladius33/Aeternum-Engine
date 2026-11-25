<?php
declare(strict_types=1);
use Aeternum\Core\{WorldRuntime,PdoScheduler,PdoEconomyProjection,ScalarMap,SystemClock,OperationsSnapshot};
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try {
    if($argc!==1||getenv('AETERNUM_VERIFY_QUIESCED')!=='YES')
        throw new InvalidArgumentException('Quiesce all writers and acknowledge before verification');
    require __DIR__.'/../vendor/autoload.php';
    $runtime=WorldRuntime::load();
    $dsn=getenv('AETERNUM_DSN');if(!$dsn)throw new RuntimeException('Database configuration missing');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $clock=new SystemClock();
    $snapshot=(new OperationsSnapshot($pdo,$clock,__DIR__.'/../database/migrations'))->snapshot($runtime->packs,$runtime->rules);
    if(!$snapshot['healthy'])throw new RuntimeException('Schema or world binding not verified');
    $worlds=[];
    foreach($runtime->packs as $world=>$pack){
        $rules=$runtime->rule($world);$scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
        $before=$scheduler->verifyReplay($rules->commands,$rules->events,100000);
        $state=$scheduler->state();
        $economy=(new PdoEconomyProjection($pdo,$world))->assertConsistent($state);
        $scores=$pack->progression===null?null:$scheduler->auditScores();
        $after=$scheduler->verifyReplay($rules->commands,$rules->events,100000);
        if($before!=$after)throw new RuntimeException('World changed during verification');
        $worlds[]=['world'=>$world,'rulesKey'=>$rules->key,'packDigest'=>$pack->digest,
            'journalEntries'=>$after->entries,'stateDigest'=>hash('sha256',ScalarMap::encode($state)),
            'pendingEvents'=>$scheduler->pendingCount(),'economy'=>$economy,'scoreRows'=>$scores];
    }
    echo json_encode(['verified'=>true,'scope'=>'quiesced-worlds','worlds'=>$worlds],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){
    fwrite(STDERR,'Verification failed: '.get_class($e).PHP_EOL);exit(1);
}
