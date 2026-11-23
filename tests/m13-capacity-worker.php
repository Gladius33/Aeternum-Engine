<?php
declare(strict_types=1);
// Disposable M13 read-only concurrency probe. It must NEVER be exposed over HTTP.
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,EconomyCommandHandler,EconomyEventHandler,ResourceDefinition,RuleSet};
if (PHP_SAPI!=='cli' || count($argv)!==2 || getenv('AETERNUM_TEST_DSN')===false) {
    fwrite(STDERR,"M13 worker requires disposable database configuration\n"); exit(2);
}
$gate=$argv[1];
try {
    if (!str_starts_with($gate,sys_get_temp_dir().DIRECTORY_SEPARATOR.'aeternum-m13-'))
        throw new RuntimeException('Unapproved gate path');
    $deadline=microtime(true)+12;
    while (!is_file($gate) && microtime(true)<$deadline) usleep(10000);
    if (!is_file($gate)) throw new RuntimeException('Concurrency start barrier timed out');
    $pdo=db();
    $economy=new EconomyCommandHandler([
        new ResourceDefinition('ore',2000,2000,0,1),
        new ResourceDefinition('coin',0,2000,0,1)
    ]);
    $rules=new RuleSet('m03-lifetime','1',hash('sha256','m03-lifetime'),
        $economy,new EconomyEventHandler($economy));
    $scheduler=new PdoScheduler($pdo,'m03-scale-world',new FrozenClock(100),$rules);
    $market=[];$balance=[];
    for ($i=0;$i<50;$i++) {
        $t=hrtime(true);
        $page=$scheduler->marketForActor('scale-reader',null,50);
        $market[]=(hrtime(true)-$t)/1e6;
        if (count($page['offers'])!==50 || $page['offers'][0]['id']!=='o0000')
            throw new RuntimeException('Unexpected market projection');
        $t=hrtime(true);
        $stock=$scheduler->balanceForActor('scale-owner-1000','account1000','ore',$economy)->stock;
        $balance[]=(hrtime(true)-$t)/1e6;
        if ($stock!==2000) throw new RuntimeException('Unexpected stock projection');
    }
    echo json_encode(['market_ms'=>$market,'balance_ms'=>$balance,'errors'=>0],
        JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION)."\n";
} catch (Throwable $e) {
    fwrite(STDERR,"M13 worker failed: ".get_class($e)."\n");
    exit(1);
}
