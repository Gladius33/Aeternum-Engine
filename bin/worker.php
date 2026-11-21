<?php
declare(strict_types=1);
use Aeternum\Core\{WorldWorker,GamePackLoader,SystemClock};
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    require __DIR__.'/../vendor/autoload.php';
    $pack=$argv[1]??'';
    $world=$argv[2]??'';
    if ($argc!==3 || !in_array($pack,['reference-medieval','reference-industrial'],true) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)) throw new InvalidArgumentException('Usage: php bin/worker.php PACK WORLD');
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    $manifest=GamePackLoader::load(file_get_contents(__DIR__.'/../games/'.$pack.'/manifest.json'));
    $ids=(new WorldWorker($pdo,new SystemClock()))->run($world,$manifest);
    echo json_encode(['world'=>$world,'processed'=>count($ids)],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Worker failed; check configuration, database availability and pinned rules.\n");
    exit(1);
}
