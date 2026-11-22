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

$topologyA=new \Aeternum\Core\PdoTopology(db(),'entity-A');
$topologyB=new \Aeternum\Core\PdoTopology(db(),'entity-B');
$a->addLocation('forest');
$topologyA->connect('home','forest',3);
$topologyA->connect('home','forest',3);
check($topologyA->neighbors('alice','home')===[['location'=>'forest','cost'=>3]],'M04 durable directed route');
check($topologyA->neighbors('bob','forest')===[],'M04 direction preserved');
$a->addLocation('quarry');
$topologyA->connect('home','quarry',7);
check($topologyA->neighbors('alice','home',1)===[['location'=>'forest','cost'=>3]],'M04 deterministic first cursor page');
check($topologyA->neighbors('alice','home',1,'forest')===[['location'=>'quarry','cost'=>7]],'M04 deterministic next cursor page');
check($topologyA->neighbors('alice','home',1,'quarry')===[],'M04 cursor exhaustion');
rejects(fn()=> $topologyA->neighbors('alice','home',101),InvalidArgumentException::class,'M04 bounded pagination');
rejects(fn()=> $topologyA->neighbors('alice','home',1,'bad cursor'),InvalidArgumentException::class,'M04 cursor validation');
rejects(fn()=> $topologyA->connect('home','forest',4),DomainException::class,'M04 immutable edge cost');
rejects(fn()=> $topologyA->connect('home','missing',3),DomainException::class,'M04 unknown endpoint');
rejects(fn()=> $topologyA->connect('home','home',1),InvalidArgumentException::class,'M04 self loop');
rejects(fn()=> $topologyA->connect('home','forest',0),InvalidArgumentException::class,'M04 zero cost');
rejects(fn()=> $topologyB->connect('home','forest',3),DomainException::class,'M04 foreign world endpoint');
check($topologyB->neighbors('bob','home')===[],'M04 topology isolated between worlds');
$topologyA->connect('forest','quarry',2);
check($topologyA->shortestPath('alice','home','quarry')===[
    'cost'=>5,'locations'=>['home','forest','quarry']
],'M04 cheapest route follows weighted directed edges');
check($topologyA->shortestPath('alice','quarry','forest')===null,
    'M04 unreachable direction has no route');
check($topologyA->shortestPath('alice','home','home')===[
    'cost'=>0,'locations'=>['home']
],'M04 zero-length route allowed');
rejects(fn()=> $topologyA->shortestPath('alice','home','missing'),DomainException::class,
    'M04 path cannot cross unknown location');
rejects(fn()=> $topologyA->shortestPath('alice','home','quarry',1),DomainException::class,
    'M04 path expansion bounded');
(new PdoMemberships(db(),'entity-A'))->revoke('alice');
rejects(fn()=> $topologyA->neighbors('alice','home'),DomainException::class,'M04 revoked topology read');
check((new \Aeternum\Core\PdoTopology(db(),'entity-A'))->neighbors('bob','home')===[['location'=>'forest','cost'=>3],['location'=>'quarry','cost'=>7]],'M04 reconnect persistence');
