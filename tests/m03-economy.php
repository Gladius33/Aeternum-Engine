<?php
declare(strict_types=1);
use Aeternum\Core\{EconomyCommandHandler,ResourceDefinition,Command,ScheduledEvent,ScalarMap};
$m03Handler=new EconomyCommandHandler([
    new ResourceDefinition('ore',100,100,100,10),
    new ResourceDefinition('coin',0,100,0,10)
]);
$m03State=[];
$m03Apply=static function(string $id,string $type,array $data,int $at) use(&$m03State,$m03Handler):array {
    $result=$m03Handler->apply(new Command('unit-m03',$id,$type,$data),$m03State,$at);
    $m03State=$result->state;
    return $result->events;
};
$m03Apply('open-seller','economy.open',['account'=>'seller','resource'=>'ore'],100);
$m03Apply('open-coin','economy.open',['account'=>'seller','resource'=>'coin'],100);
$m03Apply('open-buyer','economy.open',['account'=>'buyer','resource'=>'ore'],100);
$expiring=['offer'=>'expires','seller'=>'seller','giveResource'=>'ore','giveUnits'=>10,
    'wantResource'=>'coin','wantUnits'=>3,'expiresAt'=>120];
$events=$m03Apply('reserve','market.create',$expiring,100);
check(count($events)===1 && $events[0]->type==='market.expire' && $events[0]->dueAt===120,
    'M03 optional expiry schedules deterministic worker event');
check($m03State['econ:seller:ore:stock']===90,'M03 timed offer reserves escrow once');
$expired=$m03Handler->complete($events[0],$m03State);
$m03State=$expired->state;
check($m03State['market:expires:status']==='expired' &&
    $m03State['market:refund:seller:ore']===10 &&
    $m03State['econ:seller:ore:stock']===100,'M03 overflow cannot destroy escrow and produces claimable credit');
check($m03Handler->complete($events[0],$m03State)->state===$m03State,
    'M03 completed expiry never refunds twice');
rejects(fn()=> $m03Handler->apply(new Command('unit-m03','claim-too-early','market.claim',
    ['account'=>'seller','resource'=>'ore']),$m03State,120),DomainException::class,
    'M03 claim cannot overfill capacity');
$m03Apply('reserve-room','market.create',['offer'=>'reserve-room','seller'=>'seller',
    'giveResource'=>'ore','giveUnits'=>10,'wantResource'=>'coin','wantUnits'=>1],120);
$m03Apply('claim','market.claim',['account'=>'seller','resource'=>'ore'],120);
check(!isset($m03State['market:refund:seller:ore']) &&
    $m03State['econ:seller:ore:stock']===100 &&
    $m03State['market:reserve-room:status']==='open',
    'M03 deferred refund is released exactly after storage becomes available');
rejects(fn()=> $m03Handler->apply(new Command('unit-m03','claim-again','market.claim',
    ['account'=>'seller','resource'=>'ore']),$m03State,120),DomainException::class,
    'M03 claimed refund is not duplicable');

// A seller must also be protected when cancelling by hand after production
// saturates the stock. A subsequent expiry for a cancelled offer is a no-op.
$m03Apply('full-cancel-create','market.create',[
    'offer'=>'cancel-full','seller'=>'seller','giveResource'=>'ore','giveUnits'=>10,
    'wantResource'=>'coin','wantUnits'=>1
],120);
$m03Apply('full-cancel','market.cancel',['offer'=>'cancel-full','seller'=>'seller'],130);
check($m03State['market:cancel-full:status']==='cancelled' &&
    $m03State['market:refund:seller:ore']===10,
    'M03 cancellation after storage saturation retains escrow as refundable claim');
$future=$m03Apply('cancel-early','market.create',[
    'offer'=>'cancel-before-expiry','seller'=>'seller','giveResource'=>'ore',
    'giveUnits'=>1,'wantResource'=>'coin','wantUnits'=>1,'expiresAt'=>150
],130);
$m03Apply('cancel-early-complete','market.cancel',
    ['offer'=>'cancel-before-expiry','seller'=>'seller'],130);
$cancelled=$m03State;
check($m03Handler->complete($future[0],$cancelled)->state===$cancelled,
    'M03 scheduled expiry of manually cancelled offer does not refund twice');

$invalid=$expiring;$invalid['offer']='invalid';$invalid['expiresAt']=100;
rejects(fn()=> $m03Handler->apply(new Command('unit-m03','invalid-expiry','market.create',$invalid),
    $m03State,100),InvalidArgumentException::class,'M03 rejects an already expired offer');
$invalid['expiresAt']=100+2592001;
rejects(fn()=> $m03Handler->apply(new Command('unit-m03','long-expiry','market.create',$invalid),
    $m03State,100),InvalidArgumentException::class,'M03 bounds expiration horizon');
$malformed=$expiring;$malformed['arbitrary']='injection';
rejects(fn()=> $m03Handler->apply(new Command('unit-m03','extra','market.create',$malformed),
    $m03State,100),InvalidArgumentException::class,'M03 refuses undeclared expiry fields');
$stress=new EconomyCommandHandler([
    new ResourceDefinition('ore',1200,1200,0,1),
    new ResourceDefinition('coin',0,1200,0,1)
]);
$s=[];
for($i=0;$i<1001;$i++) {
    $a=$stress->apply(new Command('m03-many','account-'.$i,'economy.open',
        ['account'=>'a'.$i,'resource'=>'ore']),$s,100);
    $s=$a->state;
}
check($s['econ:count']===1001 && $s['econ:a1000:ore:stock']===1200,
    'M03 pure engine accepts 1001 persisted-account transitions without former 1000 cap');
$s=$stress->apply(new Command('m03-many','seller-coin','economy.open',
    ['account'=>'a0','resource'=>'coin']),$s,100)->state;
for($i=0;$i<1001;$i++) {
    $s=$stress->apply(new Command('m03-many','offer-'.$i,'market.create',[
       'offer'=>'offer'.$i,'seller'=>'a0','giveResource'=>'ore','giveUnits'=>1,
       'wantResource'=>'coin','wantUnits'=>1
    ]),$s,100)->state;
}
check($s['market:count']===1001 && $s['market:offer1000:status']==='open',
    'M03 pure engine accepts 1001 lifetime offers without former market cap');
check(ScalarMap::decode(ScalarMap::encode($s))==ScalarMap::validate($s),
    'M03 1001 account/offer source snapshot remains serializable for replay');
