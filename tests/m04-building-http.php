<?php
declare(strict_types=1);
use Aeternum\Core\{PdoIdentity,PdoMemberships,PdoEntities,PdoTerritories,PdoBuildings,
    PdoSessions,WorldBuildingHttpApi,FrozenClock};
$buildingClock=new FrozenClock(240);
$buildingActor=(new PdoIdentity(db()))->register('building-http-actor','building authorization password');
(new PdoMemberships(db(),'entity-A'))->grant($buildingActor);
$entityFixture=new PdoEntities(db(),'entity-A');
$entityFixture->addLocation('building-api-land');
$entityFixture->provision('building-api-unit','building-api-land',$buildingActor,'settlement');
$territoryFixture=new PdoTerritories(db(),'entity-A');
$territoryFixture->define('building-api-plot','building-api-land','plot');
$territoryFixture->assign('building-api-plot',$buildingActor);
$buildingFixture=new PdoBuildings(db(),'entity-A');
$buildingFixture->define('api-workshop',[[
    'costs'=>['wood'=>3,'grain'=>2],'duration'=>60,'requires'=>[]
]]);
$buildingFixture->establish('building-api-site','building-api-plot','building-api-unit',$buildingActor,'api-workshop');
$tokenBuilding=(new PdoSessions(db(),$buildingClock))->login('building-http-actor','building authorization password');
$authBuilding='Bearer '.$tokenBuilding;
$buildingApi=new WorldBuildingHttpApi(db(),$buildingClock,['entity-A'=>true,'entity-B'=>true]);
$buildingPath='/v1/worlds/entity-A/buildings/building-api-site';
$developmentApi=new \Aeternum\Core\HttpApi(db(),$buildingClock,['entity-A'=>true,'entity-B'=>true]);
[$devCode,$devBody]=$developmentApi->handle('GET',
    '/v1/worlds/entity-A/entities/building-api-unit/development',$authBuilding);
check($devCode===200 && $devBody['data']->development['buildingCount']===1
    && $devBody['data']->development['levelSum']===0,
    'M04 private HTTP development component reflects durable building foundations');
check($developmentApi->handle('GET',
    '/v1/worlds/entity-A/entities/unit/development',$authBuilding)[0]===404,
    'M04 private HTTP development component rejects foreign entity');

$optionsPath='/v1/worlds/entity-A/construction/options';
check($buildingApi->handle('GET',$optionsPath,'')[0]===401,
 'M04 choice discovery rejects anonymous request');
check($buildingApi->handle('POST',$optionsPath,$authBuilding)[0]===405,
 'M04 choice discovery cannot mutate world');
[$optionsCode,$optionsBody]=$buildingApi->handle('GET',$optionsPath,$authBuilding);
check($optionsCode===200 && in_array(['id'=>'api-workshop','first'=>[
    'costs'=>['grain'=>2,'wood'=>3],'duration'=>60,'requires'=>[]
]],$optionsBody['data']['types'],true),
 'M04 choice discovery returns pinned building catalog');
check(in_array([
    'territory_id'=>'building-api-plot','location_id'=>'building-api-land',
    'entity_id'=>'building-api-unit'
],$optionsBody['data']['sites'],true),
 'M04 choice discovery enumerates only actor-owned matching site and entity');
check(in_array([
    'id'=>'building-api-site','type'=>'api-workshop','territory'=>'building-api-plot'
],$optionsBody['data']['buildings'],true),
 'M04 choice discovery lists own building IDs');
$progressPath='/v1/worlds/entity-A/construction/progression';
check($buildingApi->handle('GET',$progressPath,'')[0]===401,
 'M05 settlement progression denies anonymous access');
check($buildingApi->handle('POST',$progressPath,$authBuilding)[0]===405,
 'M05 settlement progression is read-only');
[$progressCode,$progressBody]=$buildingApi->handle('GET',$progressPath,$authBuilding);
check($progressCode===200 && count($progressBody['data']['buildings'])===1
    && $progressBody['data']['buildings'][0]['id']==='building-api-site'
    && $progressBody['data']['buildings'][0]['ready']===true
    && $progressBody['data']['buildings'][0]['missing']===[],
 'M05 authorized progression returns actionable stage readiness');
// A corrupt journal projection must never masquerade as a completed settlement.
$originalState=db()->query("SELECT state_json FROM engine_worlds WHERE world_id='entity-A'")->fetchColumn();
check(is_string($originalState),'M05 persisted world snapshot is available for corruption tests');
$writeState=db()->prepare('UPDATE engine_worlds SET state_json=? WHERE world_id=?');
try {
    $corrupt=\Aeternum\Core\ScalarMap::decode($originalState);
    $corrupt['build:building-api-site:level']=2;
    $writeState->execute([\Aeternum\Core\ScalarMap::encode($corrupt),'entity-A']);
    rejects(fn()=>$buildingFixture->progressionFor($buildingActor),
        UnexpectedValueException::class,'M05 progression rejects levels beyond pinned catalog');
    $corrupt['build:building-api-site:level']=1;
    $corrupt['build:building-api-site:pending']='build:'.str_repeat('a',64);
    $writeState->execute([\Aeternum\Core\ScalarMap::encode($corrupt),'entity-A']);
    rejects(fn()=>$buildingFixture->progressionFor($buildingActor),
        UnexpectedValueException::class,'M05 progression rejects pending work on a max-level building');
} finally {
    $writeState->execute([$originalState,'entity-A']);
}
check($buildingFixture->progressionFor($buildingActor)['buildings'][0]['ready']===true,
    'M05 snapshot restored after negative progression tests');
check($buildingApi->handle('GET','/v1/worlds/entity-B/construction/progression',$authBuilding)[0]===404,
 'M05 progression rejects cross-world access');
check($buildingApi->handle('GET','/v1/worlds/entity-B/construction/options',$authBuilding)[0]===404,
 'M04 site discovery cannot cross world');

check($buildingApi->handle('GET',$buildingPath,'')[0]===401,
 'M04 building GET requires authorization');
check($buildingApi->handle('POST',$buildingPath,$authBuilding)[0]===405,
 'M04 building API cannot modify or start construction');
[$buildingCode,$buildingData]=$buildingApi->handle('GET',$buildingPath,$authBuilding);
check($buildingCode===200 && $buildingData['data']['next']['costs']===['grain'=>2,'wood'=>3]
    && $buildingData['data']['level']===0,
 'M04 browser-ready building preview exposes immutable multi-resource rules');
check($buildingApi->handle('GET','/v1/worlds/entity-B/buildings/building-api-site',$authBuilding)[0]===404,
 'M04 building GET isolates world');
check($buildingApi->handle('GET','/v1/worlds/entity-A/buildings/mill-home',$authBuilding)[0]===404,
 'M04 building GET hides another actor property');
check($buildingApi->handle('GET','/v1/worlds/unknown/buildings/building-api-site',$authBuilding)[0]===404,
 'M04 building GET rejects unapproved world');
(new PdoMemberships(db(),'entity-A'))->revoke($buildingActor);
check($buildingApi->handle('GET',$buildingPath,$authBuilding)[0]===404,
 'M04 building GET denies revoked actor membership');
check($buildingApi->handle('GET',$progressPath,$authBuilding)[0]===404,
 'M05 progression denies revoked actor membership');
(new PdoSessions(db(),$buildingClock))->revoke($tokenBuilding);
check($buildingApi->handle('GET',$buildingPath,$authBuilding)[0]===401,
 'M04 building GET rejects revoked session');
