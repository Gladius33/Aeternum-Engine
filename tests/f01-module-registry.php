<?php
declare(strict_types=1);
use Aeternum\Core\{BilateralTruceCommands,GamePackLoader,ReviewedModuleCatalog,WorldModuleRegistry,WorldRuntime};

(static function():void {
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/versions/0.4.0/manifest.json'));
    $plain=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/manifest.json'));
    $packs=['f01-enabled'=>$pack,'f01-disabled'=>$pack,'f01-old'=>$plain];
    $module=new BilateralTruceCommands();
    $rules=$pack->rules([$module]);
    $entry=['world'=>'f01-enabled','pack_sha256'=>$pack->digest,'rules_key'=>$rules->key,
        'modules'=>[['id'=>'bilateral-truce-v1','sha256'=>$module->codeDigest()]]];
    $cfg=['module_api'=>'1','worlds'=>[$entry]];
    $encode=static fn(array $v):string=>json_encode($v,JSON_THROW_ON_ERROR);
    $runtime=WorldModuleRegistry::load($encode($cfg),$packs);
    check($runtime->rule('f01-enabled')->key===$rules->key
        && $runtime->rule('f01-disabled')->key===$pack->rules()->key
        && $runtime->rule('f01-old')->key===$plain->rules()->key,
        'F01 explicit module selection preserves approved and historical identities');
    check($runtime->rule('f01-enabled')->commands->allowsActorPackCommand('pack.diplomacy.offer')
        && !$runtime->rule('f01-disabled')->commands->allowsActorPackCommand('pack.diplomacy.offer'),
        'F01 module admission isolated to selected world');
    check(WorldModuleRegistry::load($encode(['module_api'=>'1','worlds'=>[]]),$packs)->rule('f01-enabled')->key===$pack->rules()->key,
        'F01 empty module registry preserves default rules');
    foreach (['pack_sha256','rules_key'] as $field) {
        $bad=$cfg;$bad['worlds'][0][$field]=str_repeat('0',64);
        rejects(fn()=>WorldModuleRegistry::load($encode($bad),$packs),DomainException::class,'F01 rejects changed '.$field);
    }
    $bad=$cfg;$bad['worlds'][0]['modules'][0]['sha256']=str_repeat('0',64);
    rejects(fn()=>WorldModuleRegistry::load($encode($bad),$packs),DomainException::class,'F01 rejects unapproved executable digest');
    $bad=$cfg;$bad['worlds'][0]['modules'][0]['id']='../../untrusted.php';
    rejects(fn()=>WorldModuleRegistry::load($encode($bad),$packs),InvalidArgumentException::class,'F01 no path or class selection from JSON');
    $bad=$cfg;$bad['worlds'][0]['modules'][]=$entry['modules'][0];
    rejects(fn()=>WorldModuleRegistry::load($encode($bad),$packs),InvalidArgumentException::class,'F01 duplicate module rejected');
    $bad=$cfg;$bad['worlds'][]=$entry;
    rejects(fn()=>WorldModuleRegistry::load($encode($bad),$packs),InvalidArgumentException::class,'F01 duplicate world rejected');
    $bad=$cfg;$bad['worlds'][0]['world']='unknown';
    rejects(fn()=>WorldModuleRegistry::load($encode($bad),$packs),InvalidArgumentException::class,'F01 unknown world rejected');
    rejects(fn()=>ReviewedModuleCatalog::module('bilateral-truce-v1',$plain),InvalidArgumentException::class,'F01 incompatible old pack rejected');
    rejects(fn()=>WorldModuleRegistry::load('{"module_api":"1","module_api":"1","worlds":[]}',$packs),JsonException::class,'F01 duplicate registry keys rejected');
    rejects(fn()=>WorldModuleRegistry::loadFile('relative.json',$packs),InvalidArgumentException::class,'F01 relative config path rejected');

    // Exercise the actual shared bootstrap using private temporary configuration.
    $worldFile=tempnam(sys_get_temp_dir(),'f01-world-');
    $moduleFile=tempnam(sys_get_temp_dir(),'f01-module-');
    if ($worldFile===false || $moduleFile===false) throw new RuntimeException('Cannot create F01 fixture');
    $env=['AETERNUM_WORLD_REGISTRY','AETERNUM_MODULE_REGISTRY','AETERNUM_MEDIEVAL_WORLD','AETERNUM_INDUSTRIAL_WORLD'];
    $old=[];foreach ($env as $name) $old[$name]=getenv($name);
    try {
        file_put_contents($worldFile,$encode(['registry_api'=>'1','worlds'=>[
            ['world'=>'f01-enabled','pack'=>$pack->id,'version'=>$pack->version,'sha256'=>$pack->digest]
        ]]));
        file_put_contents($moduleFile,$encode($cfg));
        putenv('AETERNUM_MEDIEVAL_WORLD');putenv('AETERNUM_INDUSTRIAL_WORLD');
        putenv('AETERNUM_WORLD_REGISTRY='.$worldFile);putenv('AETERNUM_MODULE_REGISTRY='.$moduleFile);
        check(WorldRuntime::load()->rule('f01-enabled')->key===$rules->key,'F01 actual bootstrap reads both private registries');
        putenv('AETERNUM_MODULE_REGISTRY');
        check(WorldRuntime::load()->rule('f01-enabled')->key===$pack->rules()->key,'F01 unconfigured module registry retains default');
    } finally {
        foreach ($old as $name=>$value) putenv($value===false?$name:$name.'='.$value);
        unlink($worldFile);unlink($moduleFile);
    }
})();
