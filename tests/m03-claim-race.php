<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,FrozenClock,Command,
    EconomyCommandHandler,EconomyEventHandler,ResourceDefinition,RuleSet};
$clock=new FrozenClock(100);
$economy=new EconomyCommandHandler([
    new ResourceDefinition('ore',100,100,100,10),
    new ResourceDefinition('coin',0,100,0,10)
]);
$rules=new RuleSet('m03-claim-race','1',hash('sha256','m03-claim-race'),
    $economy,new EconomyEventHandler($economy));
$world='m03-claim-race';
$store=new PdoScheduler(db(),$world,$clock,$rules);
$store->initialize();
$members=new PdoMemberships(db(),$world);
$members->grant('claim-racer');$members->assign('claim-account','claim-racer');
foreach(['ore','coin'] as $resource) {
    $store->executeForActor('claim-racer',new Command($world,'open-'.$resource,
        'economy.open',['account'=>'claim-account','resource'=>$resource]),$economy);
}
$create=fn(string $id,array $payload):Command=>new Command($world,$id,'market.create',$payload);
$store->executeForActor('claim-racer',$create('expire',[
    'offer'=>'race-expire','seller'=>'claim-account','giveResource'=>'ore',
    'giveUnits'=>10,'wantResource'=>'coin','wantUnits'=>1,'expiresAt'=>110
]),$economy);
$clock->advanceTo(110);
check(count($store->processDue($rules->events))===1,
    'M03 concurrent claim fixture commits one full-capacity expiry');
check(($store->state()['market:refund:claim-account:ore']??null)===10,
    'M03 concurrent claim fixture persists ten units outside capacity');
$store->executeForActor('claim-racer',$create('open-room',[
    'offer'=>'race-open','seller'=>'claim-account','giveResource'=>'ore',
    'giveUnits'=>10,'wantResource'=>'coin','wantUnits'=>1
]),$economy);
$workers=[];
for($i=0;$i<4;$i++) {
    $process=proc_open([PHP_BINARY,__DIR__.'/m03-claim-worker.php',(string)$i],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process)) throw new RuntimeException('M03 claim worker unavailable');
    fclose($pipes[0]);$workers[]=[$process,$pipes];
}
$wins=0;$denied=0;
foreach($workers as [$process,$pipes]) {
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0) throw new RuntimeException('M03 worker failed: '.$stderr);
    if(trim($stdout)==='won')$wins++;
    elseif(trim($stdout)==='denied')$denied++;
    else throw new RuntimeException('Unexpected M03 claim worker output');
}
check($wins===1 && $denied===3,'M03 independent PHP processes allow exactly one competing refund claim');
$record=db()->query("SELECT COUNT(*) FROM engine_commands WHERE world_id='m03-claim-race' AND client_request_id LIKE 'claim-competitor-%'")->fetchColumn();
check((int)$record===1,'M03 competing claim creates only one durable receipt');
check((int)db()->query("SELECT COUNT(*) FROM engine_market_refunds WHERE world_id='m03-claim-race'")->fetchColumn()===0,
    'M03 concurrent refund has no leftover or duplicated claim balance');
check($store->balanceForActor('claim-racer','claim-account','ore',$economy)->stock===100,
    'M03 concurrent refund cannot inflate final physical stock above capacity');
check($store->verifyReplay($economy,$rules->events)->entries===6,
    'M03 exactly-once competing claim replays with original pinned rules');
