<?php
declare(strict_types=1);
use Aeternum\Core\{WorldRuntime,PdoScheduler,SystemClock};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc!==3 || !in_array($argv[1],['audit','rebuild'],true))
        throw new InvalidArgumentException('Usage: score-index.php audit|rebuild WORLD');
    $runtime=WorldRuntime::load();$rules=$runtime->rule($argv[2]);
    $dsn=getenv('AETERNUM_DSN');if (!$dsn) throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $scheduler=new PdoScheduler($pdo,$argv[2],new SystemClock(),$rules);
    $count=$argv[1]==='audit'?$scheduler->auditScores():$scheduler->rebuildScores();
    echo json_encode(['operation'=>$argv[1],'world'=>$argv[2],'nonzero_scores'=>$count],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,"Score index operation failed: ".get_class($e).PHP_EOL);exit(1);
}
