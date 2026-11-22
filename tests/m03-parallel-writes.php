<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,FrozenClock,Command,
    EconomyCommandHandler,EconomyEventHandler,ResourceDefinition,RuleSet};
$e=new EconomyCommandHandler([
    new ResourceDefinition('ore',100,100,0,1),
    new ResourceDefinition('coin',0,20,0,1)
]);
$rules=new RuleSet('m03-parallel-writes','1',hash('sha256','m03-parallel-writes'),
    $e,new EconomyEventHandler($e));
$world='m03-parallel-writes';
$store=new PdoScheduler(db(),$world,new FrozenClock(100),$rules);
$store->initialize();
$members=new PdoMemberships(db(),$world);
for($i=0;$i<4;$i++) {
    $actor='writer-'.$i;$account='writer-account-'.$i;
    $members->grant($actor);$members->assign($account,$actor);
    foreach(['ore','coin'] as $resource) {
        $store->executeForActor($actor,new Command($world,'open-'.$actor.'-'.$resource,
            'economy.open',['account'=>$account,'resource'=>$resource]),$e);
    }
}
$locker=db();
$locker->beginTransaction();
$lock=$locker->prepare('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE');
$lock->execute([$world]);
if($lock->fetchColumn()!==$world) throw new RuntimeException('M03 lock fixture failed');
$workers=[];
$wallStarted=hrtime(true);
try {
    for($i=0;$i<4;$i++) {
        $proc=proc_open([PHP_BINARY,__DIR__.'/m03-write-worker.php',(string)$i],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($proc)) throw new RuntimeException('M03 worker unavailable');
        fclose($pipes[0]);$workers[]=[$proc,$pipes];
    }
    usleep(200000);
} finally { $locker->commit(); }
foreach($workers as [$proc,$pipes]) {
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($proc)!==0 || trim($stdout)!=='ok 25') {
        throw new RuntimeException('M03 parallel write worker: '.$stdout.$stderr);
    }
}
check((int)db()->query("SELECT COUNT(*) FROM engine_market_offers WHERE world_id='m03-parallel-writes'")->fetchColumn()===100,
    'M03 independent writers commit all 100 escrows after contention');
check((int)db()->query("SELECT COUNT(*) FROM engine_commands WHERE world_id='m03-parallel-writes'")->fetchColumn()===108,
    'M03 writes preserve eight openings and 100 actor receipts');
check((int)db()->query("SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id='m03-parallel-writes' AND reason='market_reserve'")->fetchColumn()===100,
    'M03 parallel writers debit precisely 100 escrow units');
for($i=0;$i<4;$i++) {
    check($store->balanceForActor('writer-'.$i,'writer-account-'.$i,'ore',$e)->stock===75,
        'M03 actor '.$i.' retains his independent after-escrow balance');
}
check($store->verifyReplay($e,$rules->events)->entries===108,
    'M03 parallel writes replay as one deterministic ordered journal');

// A bounded CI-only contention probe: real MariaDB, four independent PHP
// processes, 25 commits per actor. Do not present this as a hosted SLA.
$wallMs=(hrtime(true)-$wallStarted)/1000000;
$throughput=100000/$wallMs;
echo sprintf("M03 load probe: 4 actors, 100 escrow commits, %.1f ms, %.1f commands/s (CI disposable DB)\n",
    $wallMs,$throughput);
check($wallMs<60000,'M03 concurrency probe finishes within 60-second CI safety budget');
