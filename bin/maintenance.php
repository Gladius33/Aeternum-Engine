<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSessions,SystemClock};
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    if ($argc!==2 || !preg_match('/^[1-9][0-9]{0,3}$/D',$argv[1]) || (int)$argv[1]>1000) throw new InvalidArgumentException('Usage: php bin/maintenance.php LIMIT (1..1000)');
    require __DIR__.'/../vendor/autoload.php';
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    $deleted=(new PdoSessions($pdo,new SystemClock()))->purgeExpired((int)$argv[1]);
    echo json_encode(['expired_sessions_deleted'=>$deleted],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Maintenance failed; check arguments and database configuration.\n");
    exit(1);
}
