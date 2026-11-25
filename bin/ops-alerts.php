<?php
declare(strict_types=1);
// This command can be polled by an operator-owned monitoring agent.
// Exit 0=no alert, 2=needs attention, 1=monitoring failed. No emails,
/// telemetry or external network calls are made by this command.
use Aeternum\Core\{WorldRuntime,OperationsSnapshot,OperationsAlerts,SystemClock};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    if ($argc!==1) throw new InvalidArgumentException('No CLI arguments accepted');
    require __DIR__.'/../vendor/autoload.php';
    $dsn=getenv('AETERNUM_DSN');
    if (!is_string($dsn)||$dsn==='') throw new RuntimeException('Database configuration unavailable');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $runtime=WorldRuntime::load();
    $snapshot=(new OperationsSnapshot($pdo,new SystemClock(),__DIR__.'/../database/migrations'))
        ->snapshot($runtime->packs,$runtime->rules);
    $result=(new OperationsAlerts())->evaluate($snapshot);
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
    exit($result['requiresAttention']?2:0);
} catch (Throwable $e) {
    fwrite(STDERR,"M11 alert evaluation failed: ".get_class($e).PHP_EOL);
    exit(1);
}
