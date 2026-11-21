<?php
declare(strict_types=1);
use Aeternum\Core\{PdoEntities,PdoMemberships,PdoScheduler,FrozenClock};

foreach (['entity-A','entity-B'] as $world) {
    (new PdoScheduler(db(),$world,new FrozenClock(100)))->initialize();
    $members=new PdoMemberships(db(),$world);
    $members->grant('alice');
    $members->grant('bob');
}
$a=new PdoEntities(db(),'entity-A');
$b=new PdoEntities(db(),'entity-B');
$a->addLocation('home');
$a->addLocation('home');
rejects(fn()=> $b->provision('unit','home','alice','village'),DomainException::class,'foreign location denied');
$a->provision('unit','home','alice','village');
$a->provision('unit','home','alice','village');
rejects(fn()=> $a->provision('unit','home','bob','village'),DomainException::class,'conflicting entity');
rejects(fn()=> $a->own('bob','unit'),DomainException::class,'foreign owner denied');
rejects(fn()=> $b->own('alice','unit'),DomainException::class,'foreign world denied');
$b->addLocation('home');
$b->provision('unit','home','bob','factory');
check($b->own('bob','unit')['kind']==='factory','independent world entity');
check((new PdoEntities(db(),'entity-A'))->own('alice','unit')['kind']==='village','entity reconnect persistence');
(new PdoMemberships(db(),'entity-A'))->revoke('alice');
rejects(fn()=> $a->own('alice','unit'),DomainException::class,'revoked entity access');
rejects(fn()=> $a->provision('other','home','alice','village'),DomainException::class,'revoked provisioning');
