<?php
declare(strict_types=1);
use Aeternum\Core\{PdoRateLimiter,SystemClock};
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    if ($argc!==2 || !preg_match('/^[1-9][0-9]{0,3}$/D',$argv[1]) || (int)$argv[1]>1000) throw new InvalidArgumentException('Usage: php bin/rate-maintenance.php LIMIT (1..1000)');
    require __DIR__.'/../vendor/autoload.php';
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    $deleted=(new PdoRateLimiter($pdo,new SystemClock()))->purgeStale((int)$argv[1]);
    echo json_encode(['stale_rate_buckets_deleted'=>$deleted],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Rate-limit maintenance failed; check arguments, clock and database configuration.\n");
    exit(1);
}
