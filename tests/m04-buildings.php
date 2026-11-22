<?php
declare(strict_types=1);
use Aeternum\Core\{PdoBuildings,BuildStages,PdoMemberships};
$stages=[
    ['costs'=>['wood'=>5,'grain'=>10],'duration'=>60,'requires'=>[]],
    ['costs'=>['wood'=>20,'stone'=>7],'duration'=>120,'requires'=>['workshop'=>1]],
];
$buildings=new PdoBuildings(db(),'entity-A');
$buildings->define('mill',$stages);
$buildings->define('mill',[
    ['costs'=>['grain'=>10,'wood'=>5],'duration'=>60,'requires'=>[]],
    ['costs'=>['stone'=>7,'wood'=>20],'duration'=>120,'requires'=>['workshop'=>1]]
]);
rejects(fn()=> $buildings->define('mill',[
    ['costs'=>['wood'=>500],'duration'=>60,'requires'=>[]]
]),DomainException::class,'M04 building rules immutable after registration');
rejects(fn()=> BuildStages::normalize([['costs'=>['wood'=>0],'duration'=>60,'requires'=>[]]]),
 InvalidArgumentException::class,'M04 building stage refuses zero cost');
rejects(fn()=> BuildStages::normalize([['costs'=>['wood'=>1],'duration'=>0,'requires'=>[]]]),
 InvalidArgumentException::class,'M04 building stage refuses zero duration');
rejects(fn()=> BuildStages::normalize([['costs'=>['wood'=>1],'duration'=>60,'requires'=>['factory'=>0]]]),
 InvalidArgumentException::class,'M04 building stage refuses invalid prerequisite');
$buildings->establish('mill-home','north','unit','alice','mill');
$buildings->establish('mill-home','north','unit','alice','mill');
rejects(fn()=> $buildings->establish('mill-home','north','unit','bob','mill'),
 DomainException::class,'M04 building ownership cannot be transferred on retry');
rejects(fn()=> $buildings->establish('mill-foreign','east','unit','alice','mill'),
 DomainException::class,'M04 cross-owner territory denied');
rejects(fn()=> $buildings->establish('mill-wrong-loc','south','unit','alice','mill'),
 DomainException::class,'M04 unowned and mismatched site denied');
rejects(fn()=> $buildings->establish('mill-missing','north','unit','alice','unknown'),
 DomainException::class,'M04 unapproved building type denied');
$own=$buildings->own('alice','mill-home');
check($own['level']===0 && $own['next']===[
  'costs'=>['grain'=>10,'wood'=>5],'duration'=>60,'requires'=>[]
], 'M04 own building includes canonical multi-resource next stage');
rejects(fn()=> $buildings->own('bob','mill-home'),DomainException::class,
    'M04 foreign building projection denied');
$other=new PdoBuildings(db(),'entity-B');
$other->define('mill',[['costs'=>['steel'=>1],'duration'=>10,'requires'=>[]]]);
$other->establish('mill-home','north','unit','bob','mill');
check($other->own('bob','mill-home')['next']['costs']===['steel'=>1],
 'M04 same building ID in independent world has distinct rules');
(new PdoMemberships(db(),'entity-A'))->revoke('alice');
rejects(fn()=> $buildings->own('alice','mill-home'),DomainException::class,'M04 revoked building read denied');
(new PdoMemberships(db(),'entity-A'))->grant('alice');
check((new PdoBuildings(db(),'entity-A'))->own('alice','mill-home')===$own,
 'M04 reconnect and membership restoration preserve immutable building plan');
