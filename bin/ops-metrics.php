<?php
declare(strict_types=1);
use Aeternum\Core\{WorldRuntime,OperationsSnapshot,OperationsAlerts,SystemClock};
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try{
    if($argc!==1)throw new InvalidArgumentException('No arguments accepted');
    require __DIR__.'/../vendor/autoload.php';
    $runtime=WorldRuntime::load();$dsn=getenv('AETERNUM_DSN');if(!$dsn)throw new RuntimeException('Missing database');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $s=(new OperationsSnapshot($pdo,new SystemClock(),__DIR__.'/../database/migrations'))->snapshot($runtime->packs,$runtime->rules);
    $a=(new OperationsAlerts())->evaluate($s);
    $lines=['# Private Aeternum operations metrics; do not expose this CLI over HTTP.',
        'aeternum_schema_verified '.(int)$s['migrations']['verified'],
        'aeternum_healthy '.(int)$s['healthy'],'aeternum_alerts '.count($a['alerts']),
        'aeternum_checked_timestamp_seconds '.$s['checkedAt']];
    foreach($s['worlds'] as $w){
        // World IDs are syntax-validated; no actor, token or raw SQL labels.
        $label='{world="'.$w['world'].'"} ';
        foreach(['initialized'=>'world_initialized','rulesVerified'=>'world_rules_verified','dueEvents'=>'due_events',
            'dueEventsCapped'=>'due_events_capped','oldestDueSeconds'=>'oldest_due_seconds',
            'outboxPending'=>'outbox_pending','outboxPendingCapped'=>'outbox_pending_capped'] as $key=>$name)
            if(isset($w[$key]))$lines[]='aeternum_'.$name.$label.(int)$w[$key];
    }
    echo implode(PHP_EOL,$lines).PHP_EOL;
    exit($a['requiresAttention']?2:0);
}catch(Throwable $e){fwrite(STDERR,'Metrics failed: '.get_class($e).PHP_EOL);exit(1);}
