<?php
declare(strict_types=1);
// One bounded CRON execution, not a daemon. Schedule from a private system
// crontab (not HTTP), with file/secret permission checks from the operator.
use Aeternum\Core\{WorldRuntime,OperationsCron,SystemClock};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    if ($argc!==1) throw new InvalidArgumentException('No CLI arguments accepted');
    require __DIR__.'/../vendor/autoload.php';
    $dsn=getenv('AETERNUM_DSN');
    if (!is_string($dsn)||$dsn==='') throw new RuntimeException('Missing DSN');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $runtime=WorldRuntime::load();
    $result=(new OperationsCron($pdo,new SystemClock()))->run(ConfiguredWorlds::load());
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
    // No raw SQL, DSN, credentials or user input in operational logs.
    fwrite(STDERR,"M11 bounded CRON failed: ".get_class($e).PHP_EOL);
    exit(1);
}
