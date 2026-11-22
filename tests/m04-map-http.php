<?php
declare(strict_types=1);
use Aeternum\Core\{WorldMapHttpApi,PdoIdentity,PdoMemberships,PdoSessions,FrozenClock};
$mapClock=new FrozenClock(210);
$mapIdentity=new PdoIdentity(db());
$mapActor=$mapIdentity->register('map-http-player','map HTTP integration test password');
$mapMembership=new PdoMemberships(db(),'entity-A');
$mapMembership->grant($mapActor);
$mapToken=(new PdoSessions(db(),$mapClock))->login('map-http-player','map HTTP integration test password');
$mapAuth='Bearer '.$mapToken;
$mapApi=new WorldMapHttpApi(db(),$mapClock,['entity-A'=>true,'entity-B'=>true]);
$territoryPath='/v1/worlds/entity-A/map/territories';
$linkPath='/v1/worlds/entity-A/map/locations/home/links';
check($mapApi->handle('GET',$territoryPath,'')[0]===401,'M04 map rejects missing bearer');
check($mapApi->handle('GET',$territoryPath,'Bearer dead')[0]===401,'M04 map rejects malformed bearer');
check($mapApi->handle('POST',$territoryPath,$mapAuth)[0]===405,'M04 map never grants owner rights via HTTP');
[$mapStatus,$mapBody]=$mapApi->handle('GET',$territoryPath,$mapAuth);
check($mapStatus===200 && count($mapBody['data']['territories'])===3
    && $mapBody['data']['territories'][0]['control']==='occupied'
    && !array_key_exists('owner_actor_id',$mapBody['data']['territories'][0]),
    'M04 API returns a private-identity-free world map');
[$mapStatus,$mapBody]=$mapApi->handle('GET',$linkPath,$mapAuth);
check($mapStatus===200 && $mapBody['data']['neighbors']===[
    ['location'=>'forest','cost'=>3],['location'=>'quarry','cost'=>7]
], 'M04 API returns real directed topology');
check($mapApi->handle('GET',$linkPath.'/forest',$mapAuth)[1]['data']['neighbors'] === [
    ['location'=>'quarry','cost'=>7]
], 'M04 API link cursor behaves deterministically');
check($mapApi->handle('GET',$linkPath.'/ghost',$mapAuth)[1]['data']['neighbors']===[],
    'M04 API cursor exhaustion');
check($mapApi->handle('GET','/v1/worlds/entity-B/map/territories',$mapAuth)[0]===404,
    'M04 map denies membership from another world');
check($mapApi->handle('GET','/v1/worlds/unknown/map/territories',$mapAuth)[0]===404,
    'M04 map refuses unconfigured world');
check($mapApi->handle('GET','/v1/worlds/entity-A/map/locations/ghost/links',$mapAuth)[0]===404,
    'M04 map refuses unknown location');
$mapMembership->revoke($mapActor);
check($mapApi->handle('GET',$territoryPath,$mapAuth)[0]===404,
    'M04 map denies revoked player');
(new PdoSessions(db(),$mapClock))->revoke($mapToken);
check($mapApi->handle('GET',$territoryPath,$mapAuth)[0]===401,
    'M04 map denies revoked session');
