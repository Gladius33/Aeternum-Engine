<?php
declare(strict_types=1);
// Local operators only; this is never served from public/.
use Aeternum\Core\{ConfiguredWorlds,OperationsSnapshot,SystemClock};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    if ($argc!==1) throw new InvalidArgumentException('No arguments accepted');
    require __DIR__.'/../vendor/autoload.php';
    $dsn=getenv('AETERNUM_DSN');
    if (!is_string($dsn)||$dsn==='') throw new RuntimeException('Missing DSN');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $state=(new OperationsSnapshot($pdo,new SystemClock(),__DIR__.'/../database/migrations'))
        ->snapshot(ConfiguredWorlds::load());
    echo json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($state['healthy']?0:2);
} catch (Throwable $e) {
    fwrite(STDERR,"M11 operations status failed: ".get_class($e).PHP_EOL);
    exit(1);
}
