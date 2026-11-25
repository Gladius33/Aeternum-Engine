<?php
declare(strict_types=1);
use Aeternum\Core\{WorldWorker,WorldRuntime,SystemClock};
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    require __DIR__.'/../vendor/autoload.php';
    $id=$argv[1]??'';
    $world=$argv[2]??'';
    if ($argc!==3 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)) {
        throw new InvalidArgumentException('Usage: php bin/worker.php PACK WORLD');
    }
    $pack=ConfiguredWorlds::load()[$world]??null;
    if ($pack===null || $pack->id!==$id) throw new InvalidArgumentException('Unapproved pack/world pair');
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    $processed=(new WorldWorker($pdo,new SystemClock()))->run($world,$pack,approvedRules:$runtime->rule($world));
    echo json_encode(['world'=>$world,'processed'=>count($processed)],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Worker failed; check approved registry, database availability and pinned rules.\n");
    exit(1);
}
