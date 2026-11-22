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
(new PdoSessions(db(),$buildingClock))->revoke($tokenBuilding);
check($buildingApi->handle('GET',$buildingPath,$authBuilding)[0]===401,
 'M04 building GET rejects revoked session');
