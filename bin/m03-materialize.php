<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,PdoScheduler,SystemClock};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc!==3 || $argv[2]!=='--backup-verified'
        || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$argv[1])) {
        throw new InvalidArgumentException('Usage: php bin/m03-materialize.php WORLD --backup-verified');
    }
    $world=$argv[1];
    $pack=ConfiguredWorlds::load()[$world]??null;
    if ($pack===null) throw new DomainException('World not approved');
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database connection');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $rules=$pack->rules();
    $scheduler=new PdoScheduler($pdo,$world,new SystemClock(),$rules);
    $report=$scheduler->materialize();
    $replay=$scheduler->verifyReplay($rules->commands,$rules->events,100000);
    $report['replayedEntries']=$replay->entries;
    $report['replayedDigest']=$replay->stateDigest;
    $report['productionRestoreCertified']=false;
    echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"M03 materialization/replay failed. Restore only from the verified backup after operator review.\n");
    exit(1);
}
