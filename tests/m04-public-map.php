<?php
declare(strict_types=1);
use Aeternum\Core\{PublicWorldMapHttpApi,FrozenClock};
$publicMap=new PublicWorldMapHttpApi(db(),new FrozenClock(200),['entity-A'=>true]);
$publicBase='/v1/public/worlds/entity-A/map/territories';
[$publicStatus,$publicData]=$publicMap->handle('GET',$publicBase,'198.51.100.1');
check($publicStatus===200 && $publicData['data']['territories']===[
    ['id'=>'east','location'=>'forest','kind'=>'mining'],
    ['id'=>'north','location'=>'home','kind'=>'settlement'],
    ['id'=>'south','location'=>'quarry','kind'=>'quarry']
] && $publicData['data']['nextAfter']===null,
 'M04 anonymous discovery shows only public terrain metadata');
check(!str_contains(json_encode($publicData,JSON_THROW_ON_ERROR),'alice')
    && !str_contains(json_encode($publicData,JSON_THROW_ON_ERROR),'bob')
    && !str_contains(json_encode($publicData,JSON_THROW_ON_ERROR),'occupied'),
 'M04 public map never exposes membership or ownership status');
check($publicMap->handle('GET',$publicBase.'/north','198.51.100.1')[1]['data']['territories']===[
    ['id'=>'south','location'=>'quarry','kind'=>'quarry']
], 'M04 anonymous cursor lists only later terrain IDs');
check($publicMap->handle('GET',$publicBase.'/south','198.51.100.1')[1]['data']['territories']===[],
 'M04 public cursor exhausts correctly');
check($publicMap->handle('GET','/v1/public/worlds/entity-B/map/territories','198.51.100.1')[0]===404,
 'M04 anonymous discovery denies unapproved world');
check($publicMap->handle('POST',$publicBase,'198.51.100.1')[0]===405,
 'M04 public map is strictly read-only');
check($publicMap->handle('GET',$publicBase,'')[0]===429,
 'M04 public map requires a bounded resolved peer');
