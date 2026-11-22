<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,PdoEntities,PdoTerritories,
    PdoBuildings,GamePackLoader,Command,FrozenClock};

// A new world avoids fixtures that relied on administrator-created foundations.
$placementPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'));
$placementRules=$placementPack->rules();
$placementWorld='m04-player-placement';
$placementClock=new FrozenClock(2000);
$placement=new PdoScheduler(db(),$placementWorld,$placementClock,$placementRules);
$placement->initialize();
$members=new PdoMemberships(db(),$placementWorld);
$entity=new PdoEntities(db(),$placementWorld);
$land=new PdoTerritories(db(),$placementWorld);
$buildings=new PdoBuildings(db(),$placementWorld);
foreach ($placementPack->buildings as $type=>$stages) $buildings->define($type,$stages);
foreach (['alice','bob'] as $who) {
    $members->grant($who);
    $members->assign('place-'.$who,$who);
    $entity->addLocation('land-'.$who);
    $entity->provision('entity-'.$who,'land-'.$who,$who,'settlement');
    $land->define('plot-'.$who,'land-'.$who,'village');
    $land->assign('plot-'.$who,$who);
    foreach ($placementPack->resources as $resource) {
        $placement->executeForActor($who,new Command($placementWorld,
            'place-open-'.$who.'-'.$resource->id,'economy.open',[
                'account'=>'place-'.$who,'resource'=>$resource->id
            ]),$placementRules->commands);
    }
}
$placePayload=static fn(string $who,string $building):array=>[
    'account'=>'place-'.$who,'building'=>$building,'entity'=>'entity-'.$who,
    'territory'=>'plot-'.$who,'type'=>'mill'
];
$place=static fn(string $who,string $request,array $payload)=>
    $placement->executeForActor($who,new Command($placementWorld,$request,'building.place',$payload),
        $placementRules->commands);
$one=$place('alice','place-first',$placePayload('alice','alice-mill'));
check($one->scheduledIds===[],'M04 player foundation placement has no time event or resource payment');
check($place('alice','place-first',$placePayload('alice','alice-mill'))==$one,
    'M04 player foundation placement retries same immutable receipt');
check($buildings->own('alice','alice-mill')['level']===0,
    'M04 actor creates own durable foundation without operator CLI');
rejects(fn()=> $place('bob','foreign-actor-entity',[
    'account'=>'place-bob','building'=>'steal-entity','entity'=>'entity-alice',
    'territory'=>'plot-bob','type'=>'mill'
]),DomainException::class,'M04 actor cannot use foreign entity');
rejects(fn()=> $place('bob','foreign-actor-land',[
    'account'=>'place-bob','building'=>'steal-land','entity'=>'entity-bob',
    'territory'=>'plot-alice','type'=>'mill'
]),DomainException::class,'M04 actor cannot place on foreign land');
rejects(fn()=> $place('alice','unknown-type',[
    'account'=>'place-alice','building'=>'unknown-type-id','entity'=>'entity-alice',
    'territory'=>'plot-alice','type'=>'unknown'
]),DomainException::class,'M04 actor cannot forge building rules');
rejects(fn()=> $place('alice','same-request-conflict',$placePayload('alice','alice-mill')),
    DomainException::class,'M04 competing request cannot overwrite registered foundation');
rejects(fn()=> $place('alice','place-first',$placePayload('alice','different-mill')),
    DomainException::class,'M04 same request identifier cannot alter placement payload');
check((int)db()->query("SELECT COUNT(*) FROM engine_buildings WHERE world_id='m04-player-placement'")->fetchColumn()===1,
    'M04 denied placement never writes a partial row');
$two=$place('bob','place-second',$placePayload('bob','bob-mill'));
check($buildings->own('bob','bob-mill')['level']===0,
    'M04 second actor independently establishes own building');
rejects(fn()=> $buildings->own('alice','bob-mill'),DomainException::class,
    'M04 actor cannot inspect foreign building');
$firstStart=$placement->executeForActor('alice',new Command($placementWorld,'start-a','building.start',[
    'account'=>'place-alice','building'=>'alice-mill','type'=>'mill'
]),$placementRules->commands);
$secondStart=$placement->executeForActor('bob',new Command($placementWorld,'start-b','building.start',[
    'account'=>'place-bob','building'=>'bob-mill','type'=>'mill'
]),$placementRules->commands);
check(count($firstStart->scheduledIds)===1 && count($secondStart->scheduledIds)===1,
    'M04 two independently placed foundations schedule two real completions');
check($placement->balanceForActor('alice','place-alice','wood',$placementRules->commands)->stock===93
    && $placement->balanceForActor('bob','place-bob','grain',$placementRules->commands)->stock===69,
    'M04 two player foundations consume their separate resource ledgers');
$placementClock->advanceTo(2005);
check(count($placement->processDue($placementRules->events))===2,
    'M04 bounded worker independently completes two player constructions');
$aliceDevelopment=$entity->ownDevelopment('alice','entity-alice');
$bobDevelopment=$entity->ownDevelopment('bob','entity-bob');
check($aliceDevelopment['development']['levelSum']===1
    && $bobDevelopment['development']['levelSum']===1
    && $aliceDevelopment['development']['buildingCount']===1
    && $bobDevelopment['development']['buildingCount']===1
    && $aliceDevelopment['development']['types']===['mill'=>1],
    'M04 live entity development component reflects two distinct completed buildings');
rejects(fn()=> $entity->ownDevelopment('alice','entity-bob'),
    DomainException::class,'M04 dynamic component refuses foreign entity');
check($buildings->own('alice','alice-mill')['level']===1 &&
    $buildings->own('bob','bob-mill')['level']===1,
    'M04 two player-built structures retain independent progression');
check($placement->verifyReplay($placementRules->commands,$placementRules->events)->entries===10,
    'M04 two-player placement, expenditure and completion replay deterministically');
check((new PdoScheduler(db(),$placementWorld,$placementClock,$placementRules))
    ->verifyReplay($placementRules->commands,$placementRules->events)->entries===10,
    'M04 replay survives database reconnect');
