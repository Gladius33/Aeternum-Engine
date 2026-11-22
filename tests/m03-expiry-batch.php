<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,FrozenClock,Command,
    EconomyCommandHandler,EconomyEventHandler,ResourceDefinition,RuleSet};
$clock=new FrozenClock(100);
$economy=new EconomyCommandHandler([
    new ResourceDefinition('ore',30,30,30,10),
    new ResourceDefinition('coin',0,30,0,10)
]);
$rules=new RuleSet('m03-expiry-batch','1',hash('sha256','m03-expiry-batch'),
    $economy,new EconomyEventHandler($economy));
$world='m03-expiry-batch';
$store=new PdoScheduler(db(),$world,$clock,$rules);
$store->initialize();
$members=new PdoMemberships(db(),$world);
$members->grant('batch-seller');$members->assign('batch-account','batch-seller');
foreach(['ore','coin'] as $r) {
    $store->executeForActor('batch-seller',new Command($world,'open-'.$r,
        'economy.open',['account'=>'batch-account','resource'=>$r]),$economy);
}
for($i=0;$i<10;$i++) {
    $store->executeForActor('batch-seller',new Command($world,'reserve-'.$i,'market.create',[
        'offer'=>'batch-'.$i,'seller'=>'batch-account','giveResource'=>'ore','giveUnits'=>1,
        'wantResource'=>'coin','wantUnits'=>1,'expiresAt'=>120
    ]),$economy);
}
check($store->balanceForActor('batch-seller','batch-account','ore',$economy)->stock===20,
    'M03 ten future escrow offers reserve ten units before expiry');
$clock->advanceTo(120);
check(count($store->processDue($rules->events,3,10000))===3,
    'M03 capped expiry worker commits exactly three deterministic events');
check((int)db()->query("SELECT COUNT(*) FROM engine_events WHERE world_id='m03-expiry-batch' AND completed=0")===7,
    'M03 bounded event worker leaves seven valid durable expirations');
check(count($store->processDue($rules->events,20,10000))===7,
    'M03 continuation commits remaining seven expirations');
check($store->processDue($rules->events)===[],
    'M03 expiry worker cannot refund a completed event twice');
check((int)db()->query("SELECT claimable_units FROM engine_market_refunds WHERE world_id='m03-expiry-batch'")===10,
    'M03 full-capacity batched expiry preserves all escrow units');
check($store->balanceForActor('batch-seller','batch-account','ore',$economy)->stock===30,
    'M03 batch escrow return never breaches resource capacity');
check($store->marketForActor('batch-seller')['offers']===[],
    'M03 ten expired offers disappear from indexed market');
check($store->verifyReplay($economy,$rules->events)->entries===22,
    'M03 batched expiry journal and ledger replay after reconnect');
