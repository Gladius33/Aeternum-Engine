<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,WorldPackRegistry};
$root=__DIR__.'/../games';
$oldBytes=file_get_contents($root.'/reference-medieval/manifest.json');
$newBytes=file_get_contents($root.'/reference-medieval/versions/0.2.0/manifest.json');
check(is_string($oldBytes) && is_string($newBytes),'M05 both immutable medieval artifacts exist');
$old=GamePackLoader::load($oldBytes);
$new=GamePackLoader::load($newBytes);
check($old->id===$new->id && $old->version==='0.1.0' && $new->version==='0.2.0',
    'M05 revision retains medieval identity without rewriting legacy manifest');
check($old->buildings===[] && count($new->buildings)===4,
    'M05 only the new medieval revision opts into settlement construction');
check($new->buildings['keep'][0]['requires']===['farm'=>1,'lumberyard'=>1],
    'M05 keep first level requires both economic foundations');
check($new->buildings['keep'][1]['requires']===['farm'=>2,'granary'=>1,'lumberyard'=>2],
    'M05 keep objective requires advanced settlement chain');
$registry=json_encode(['registry_api'=>'1','worlds'=>[
    ['world'=>'medieval-legacy','pack'=>'reference-medieval','version'=>$old->version,'sha256'=>$old->digest],
    ['world'=>'medieval-progress','pack'=>'reference-medieval','version'=>$new->version,'sha256'=>$new->digest]
]],JSON_THROW_ON_ERROR);
$worlds=WorldPackRegistry::load($registry,$root);
check($worlds['medieval-legacy']->rules()->key!==$worlds['medieval-progress']->rules()->key
    && $worlds['medieval-legacy']->buildings===[],
    'M05 old and new medieval worlds coexist with distinct pinned rules');
$invalid=json_decode($newBytes,true,32,JSON_THROW_ON_ERROR);
$invalid['buildings'][3]['stages'][1]['requires']['granary']=0;
rejects(fn()=>GamePackLoader::load(json_encode($invalid,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M05 malformed settlement prerequisite fails closed');
$invalid=json_decode($newBytes,true,32,JSON_THROW_ON_ERROR);
$invalid['buildings'][0]['stages'][0]['costs']['iron']=1;
rejects(fn()=>GamePackLoader::load(json_encode($invalid,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M05 settlement cannot spend an undeclared resource');
