<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,WorldPackRegistry};
$root=__DIR__.'/../games';
$legacyBytes=file_get_contents($root.'/reference-industrial/manifest.json');
$progressBytes=file_get_contents($root.'/reference-industrial/versions/0.2.0/manifest.json');
check(is_string($legacyBytes) && is_string($progressBytes),'M06 immutable industrial versions exist');
$legacy=GamePackLoader::load($legacyBytes);
$progress=GamePackLoader::load($progressBytes);
check($legacy->id===$progress->id && $legacy->version==='0.1.0' && $progress->version==='0.2.0',
    'M06 industrial pack retains identity with a distinct pinned version');
check($legacy->buildings===[] && count($progress->buildings)===4,
    'M06 legacy pack remains untouched while new infrastructure is opt-in');
check($progress->buildings['steelworks'][0]['requires']===['power_plant'=>1],
    'M06 steelworks requires energy infrastructure');
check($progress->buildings['freight_terminal'][0]['requires']===
    ['power_plant'=>2,'steelworks'=>2,'warehouse'=>2],
    'M06 freight objective requires the full infrastructure chain');
$registry=WorldPackRegistry::load(json_encode(['registry_api'=>'1','worlds'=>[
    ['world'=>'industrial-legacy','pack'=>$legacy->id,'version'=>$legacy->version,'sha256'=>$legacy->digest],
    ['world'=>'industrial-progress','pack'=>$progress->id,'version'=>$progress->version,'sha256'=>$progress->digest]
]],JSON_THROW_ON_ERROR),$root);
check($registry['industrial-legacy']->rules()->key!==$registry['industrial-progress']->rules()->key,
    'M06 independently pinned industrial versions retain different rules');
$bad=json_decode($progressBytes,false,32,JSON_THROW_ON_ERROR);
$bad->buildings[3]->stages[0]->requires->steelworks=3;
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M06 unreachable industrial prerequisites rejected');
$bad=json_decode($progressBytes,false,32,JSON_THROW_ON_ERROR);
$bad->buildings[0]->stages[0]->costs->coal=1;
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),
    LogicException::class,'M06 undeclared fuel resource rejected');
$bad=json_decode($progressBytes,false,32,JSON_THROW_ON_ERROR);
$bad->buildings[0]->stages[0]->duration=0;
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M06 zero-duration construction rejected');
