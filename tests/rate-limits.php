<?php
declare(strict_types=1);
use Aeternum\Core\{PdoRateLimiter,FrozenClock};
$rateClock=new FrozenClock(120);
$rate=new PdoRateLimiter(db(),$rateClock);
check($rate->admit('login:one',2,60),'first admission');
check((new PdoRateLimiter(db(),$rateClock))->admit('login:one',2,60),'persistent admission');
check(!$rate->admit('login:one',2,60),'limit enforced');
check($rate->admit('login:two',2,60),'independent bucket');
$rateClock->advanceTo(179);
check(!$rate->admit('login:one',2,60),'window remains blocked');
$rateClock->advanceTo(180);
check($rate->admit('login:one',2,60),'window reset');
check($rate->admit('login:one',2,60),'second new window admission');
check(!(new PdoRateLimiter(db(),new FrozenClock(120)))->admit('login:one',2,60),'rollback cannot reset exhausted bucket');
rejects(fn()=> $rate->admit('',2,60),InvalidArgumentException::class,'invalid bucket');
