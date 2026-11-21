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

$a->attach('unit','storage',['capacity'=>100,'enabled'=>true]);
$a->attach('unit','storage',['enabled'=>true,'capacity'=>100]);
rejects(fn()=> $a->attach('unit','storage',['capacity'=>200]),DomainException::class,'component conflict');
rejects(fn()=> $a->attach('missing','storage',[]),DomainException::class,'unknown entity');
rejects(fn()=> $a->attach('unit','bad',['nested'=>[]]),UnexpectedValueException::class,'invalid component');
rejects(fn()=> $a->attach('unit','large',['data'=>str_repeat('x',8192)]),InvalidArgumentException::class,'oversize component');
rejects(fn()=> $a->ownComponent('alice','unit','storage'),DomainException::class,'revoked component access');
rejects(fn()=> $a->ownComponent('bob','unit','storage'),DomainException::class,'foreign owner component');
rejects(fn()=> $b->ownComponent('bob','unit','storage'),DomainException::class,'foreign world component');
(new PdoMemberships(db(),'entity-A'))->grant('alice');
check((new PdoEntities(db(),'entity-A'))->ownComponent('alice','unit','storage')===['capacity'=>100,'enabled'=>true],'component reconnect and canonical retry');
$b->attach('unit','storage',['capacity'=>200]);
check($b->ownComponent('bob','unit','storage')===['capacity'=>200],'separate world component');
