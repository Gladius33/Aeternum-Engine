<?php
declare(strict_types=1);
use Aeternum\Core\{PdoTerritories,PdoMemberships,PdoEntities};

$territories=new PdoTerritories(db(),'entity-A');
$otherTerritories=new PdoTerritories(db(),'entity-B');
// These real-world fixtures were created by entities.php; the last actor
// revocation there must be explicitly reversed before operator assignment.
(new PdoMemberships(db(),'entity-A'))->grant('alice');
$territories->define('east','forest','mining');
$territories->define('north','home','settlement');
$territories->define('south','quarry','quarry');
$territories->define('east','forest','mining');
rejects(fn()=> $territories->define('east','home','mining'),DomainException::class,'M04 conflicting location is immutable');
rejects(fn()=> $territories->define('east','forest','other'),DomainException::class,'M04 conflicting territory type is immutable');
rejects(fn()=> $territories->define('ghost','missing','site'),DomainException::class,'M04 unknown territory location rejected');
$territories->assign('north','alice');
$territories->assign('north','alice');
$territories->assign('east','bob');
rejects(fn()=> $territories->assign('north','bob'),DomainException::class,'M04 ownership cannot be stolen');
rejects(fn()=> $territories->assign('ghost','bob'),DomainException::class,'M04 unknown territory assignment rejected');
rejects(fn()=> $territories->assign('south','nobody'),DomainException::class,'M04 nonmember assignment rejected');
$alicePage=$territories->pageFor('alice');
check($alicePage===[
    'territories'=>[
        ['id'=>'east','location'=>'forest','kind'=>'mining','control'=>'occupied'],
        ['id'=>'north','location'=>'home','kind'=>'settlement','control'=>'own'],
        ['id'=>'south','location'=>'quarry','kind'=>'quarry','control'=>'free'],
    ],'nextAfter'=>null
],'M04 map hides other owner identities');
$bobPage=(new PdoTerritories(db(),'entity-A'))->pageFor('bob',1);
check($bobPage['territories'][0]['control']==='own' && $bobPage['nextAfter']==='east','M04 deterministic page one');
$bobPage2=$territories->pageFor('bob',1,$bobPage['nextAfter']);
check($bobPage2['territories'][0]['control']==='occupied' && $bobPage2['nextAfter']==='north','M04 deterministic page two');
check($territories->pageFor('bob',1,'south')['territories']===[],'M04 cursor exhaustion');
rejects(fn()=> $territories->pageFor('alice',101),InvalidArgumentException::class,'M04 bounded map page');
rejects(fn()=> $territories->pageFor('alice',1,'invalid cursor'),InvalidArgumentException::class,'M04 invalid cursor');
$otherTerritories->define('north','home','factory');
$otherTerritories->assign('north','bob');
check($otherTerritories->pageFor('bob')['territories']===[
    ['id'=>'north','location'=>'home','kind'=>'factory','control'=>'own']
],'M04 equal territory IDs remain world-isolated');
rejects(fn()=> $otherTerritories->assign('east','bob'),DomainException::class,'M04 cross-world territory invisible');
(new PdoMemberships(db(),'entity-A'))->revoke('alice');
rejects(fn()=> $territories->pageFor('alice'),DomainException::class,'M04 revoked map membership denied');
rejects(fn()=> $territories->assign('south','alice'),DomainException::class,'M04 revoked owner may not acquire land');
check($territories->pageFor('bob')['territories'][1]['control']==='occupied','M04 revocation does not transfer land');
(new PdoMemberships(db(),'entity-A'))->grant('alice');
check((new PdoTerritories(db(),'entity-A'))->pageFor('alice')['territories'][1]['control']==='own',
    'M04 ownership survives reconnect and renewed membership');
