<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,FrozenClock,Command,
    EconomyCommandHandler,EconomyEventHandler,ResourceDefinition,RuleSet};
$scaleClock=new FrozenClock(100);
$scaleEconomy=new EconomyCommandHandler([
    new ResourceDefinition('ore',2000,2000,0,1),
    new ResourceDefinition('coin',0,2000,0,1)
]);
$scaleRules=new RuleSet('m03-lifetime','1',hash('sha256','m03-lifetime'),
    $scaleEconomy,new EconomyEventHandler($scaleEconomy));
$scaleWorld='m03-scale-world';
$scale=new PdoScheduler(db(),$scaleWorld,$scaleClock,$scaleRules);
$scale->initialize();
$members=new PdoMemberships(db(),$scaleWorld);
$members->grant('scale-seller');
$members->grant('scale-reader');
$members->assign('warehouse','scale-seller');
foreach(['ore','coin'] as $resource) {
    $scale->executeForActor('scale-seller',new Command($scaleWorld,'open-'.$resource,
        'economy.open',['account'=>'warehouse','resource'=>$resource]),$scaleEconomy);
}
// Exercises real actor authorization, receipts, ledger, SQL projection and
// keyset pagination; no bypass of the 1000-offer lifetime cap in code.
for($i=0;$i<1001;$i++) {
    $offerId='o'.str_pad((string)$i,4,'0',STR_PAD_LEFT);
    $scale->executeForActor('scale-seller',new Command($scaleWorld,'create-'.$offerId,
        'market.create',['offer'=>$offerId,'seller'=>'warehouse',
            'giveResource'=>'ore','giveUnits'=>1,'wantResource'=>'coin','wantUnits'=>1]),$scaleEconomy);
}
check($scale->state()['market:count']===1001,
    'M03 1001 distinct actor-authorized offers persist without lifetime counter rejection');
$sqlOffers=(int)db()->query("SELECT COUNT(*) FROM engine_market_offers WHERE world_id='m03-scale-world'")->fetchColumn();
check($sqlOffers===1001,'M03 1001 relational market rows committed without truncation');
$listed=[];$cursor=null;
do {
    $page=$scale->marketForActor('scale-reader',$cursor,50);
    foreach($page['offers'] as $offer) $listed[]=$offer['id'];
    $cursor=$page['nextAfter'];
    if (count($listed)>1100) throw new RuntimeException('Market cursor cycle detected');
} while($cursor!==null);
check(count($listed)===1001 && count(array_unique($listed))===1001 &&
    $listed[0]==='o0000' && $listed[1000]==='o1000',
    'M03 SQL keyset pagination traverses 1001 offers without duplicate or lifetime truncation');
$stock=(int)db()->query("SELECT stock_units FROM engine_economy_balances WHERE world_id='m03-scale-world' AND account_id='warehouse' AND resource_id='ore'")->fetchColumn();
check($stock===999,'M03 escrow reserves exactly 1001 physical units in relational stock');
check((int)db()->query("SELECT COUNT(*) FROM engine_commands WHERE world_id='m03-scale-world'")->fetchColumn()===1003,
    'M03 SQL command receipts preserve every distinct opening and escrow action');
check((int)db()->query("SELECT SUM(delta_units) FROM engine_resource_ledger WHERE world_id='m03-scale-world' AND resource_id='ore'")->fetchColumn()===999,
    'M03 ledger reflects all 1001 escrow debits and opening stock, without duplicate postings');


// Open more than 1000 actual resources under one authenticated actor scope,
// using the same production adapter and InnoDB projection (not mock handlers).
for($i=1;$i<=1000;$i++) {
    $account='account'.str_pad((string)$i,4,'0',STR_PAD_LEFT);
    $actor='scale-owner-'.str_pad((string)$i,4,'0',STR_PAD_LEFT);
    $members->grant($actor);
    $members->assign($account,$actor);
    $scale->executeForActor($actor,new Command($scaleWorld,'open-'.$account,
        'economy.open',['account'=>$account,'resource'=>'ore']),$scaleEconomy);
}
check($scale->state()['econ:count']===1002,
    'M03 1002 resource account openings survive real MariaDB transaction boundaries');
$balanceRows=(int)db()->query("SELECT COUNT(*) FROM engine_economy_balances WHERE world_id='m03-scale-world'")->fetchColumn();
check($balanceRows===1002,'M03 more than 1000 indexed relational resource accounts persist');
check($scale->balanceForActor('scale-owner-1000','account1000','ore',$scaleEconomy)->stock===2000,
    'M03 actor can read his 1000th new account after migration and writes');
check((int)db()->query("SELECT COUNT(DISTINCT actor_id) FROM engine_account_owners WHERE world_id='m03-scale-world'")->fetchColumn()===1001,
    'M03 more than 1000 different actors own resource accounts (not one test actor only)');
rejects(fn()=> $scale->balanceForActor('scale-owner-0001','account1000','ore',$scaleEconomy),
    DomainException::class,'M03 distinct actor 1 cannot read actor 1000 private balance');
check((int)db()->query("SELECT COUNT(*) FROM engine_commands WHERE world_id='m03-scale-world' AND actor_id IS NOT NULL AND result_state_json='{}'")->fetchColumn()===2003,
    'M03 actor receipts persist compact output without quadratic copies of the world snapshot');
check((int)db()->query("SELECT COUNT(*) FROM engine_commands WHERE world_id='m03-scale-world'")->fetchColumn()===2003,
    'M03 more than 2000 durable command receipts coexist without ID loss');


// A concurrent writer holds SELECT ... FOR UPDATE on this world's row. Public
// read-only SQL market and stock/overview projections must still complete
// without waiting for global serialization. The snapshot is committed data.
$exclusive=db();
$exclusive->beginTransaction();
try {
    $lock=$exclusive->prepare('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE');
    $lock->execute([$scaleWorld]);
    check($lock->fetchColumn()===$scaleWorld,'M03 contention fixture holds actual world row lock');
    $parallelReader=new PdoScheduler(db(),$scaleWorld,$scaleClock,$scaleRules);
    $started=hrtime(true);
    $offers=$parallelReader->marketForActor('scale-reader',null,5);
    $balance=$parallelReader->balanceForActor('scale-owner-1000','account1000','ore',$scaleEconomy);
    $overview=$parallelReader->overviewForActor('scale-owner-1000','account1000','ore',$scaleEconomy);
    $elapsedMs=(hrtime(true)-$started)/1_000_000;
    check(count($offers['offers'])===5 && $offers['offers'][0]['id']==='o0000'
        && $balance->stock===2000 && $overview['stock']===2000,
        'M03 indexed market, balance and overview remain readable during real writer lock');
    check($elapsedMs<2000,'M03 read-only MVCC queries avoid world row lock wait');
} finally {
    if ($exclusive->inTransaction()) $exclusive->rollBack();
}
