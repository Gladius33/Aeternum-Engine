<?php
declare(strict_types=1);
$manifest=__DIR__.'/../games/m04-construction-fixture/manifest.json';
$packData=json_decode(file_get_contents($manifest),true,16,JSON_THROW_ON_ERROR);
$registryFile=tempnam(sys_get_temp_dir(),'aeternum-m04-map-registry');
if (!is_string($registryFile)) throw new RuntimeException('Unable to create disposable registry');
file_put_contents($registryFile,json_encode([
    'registry_api'=>'1','worlds'=>[[
        'world'=>'m04-construction-world',
        'pack'=>$packData['id'],
        'version'=>$packData['version'],
        'sha256'=>hash_file('sha256',$manifest)
    ]]
],JSON_THROW_ON_ERROR));
$buildingCliEnv=[
    'AETERNUM_DSN'=>(string)getenv('AETERNUM_TEST_DSN'),
    'AETERNUM_DB_USER'=>getenv('AETERNUM_TEST_USER') ?: '',
    'AETERNUM_DB_PASSWORD'=>getenv('AETERNUM_TEST_PASSWORD') ?: '',
    'AETERNUM_WORLD_REGISTRY'=>$registryFile
];
try {
    check(executeMap(['m04-construction-world','building-type','mill'],$buildingCliEnv)[0]===0,
        'M04 CLI imports only world-pinned building stages');
    check(executeMap(['m04-construction-world','building-type','mill'],$buildingCliEnv)[0]===0,
        'M04 CLI building type registration is idempotent');
    check(executeMap(['m04-construction-world','building-type','missing'],$buildingCliEnv)[0]!==0,
        'M04 CLI refuses types outside pinned rules');
    check(executeMap([
        'm04-construction-world','foundation','cli-mill-a','build-land-a','build-entity-a','alice','mill'
    ],$buildingCliEnv)[0]===0,'M04 CLI places an approved foundation owned by actor');
    check(executeMap([
        'm04-construction-world','foundation','cli-mill-a','build-land-a','build-entity-a','alice','mill'
    ],$buildingCliEnv)[0]===0,'M04 CLI foundation replay idempotent');
    check(executeMap([
        'm04-construction-world','foundation','cli-mill-bad','build-land-a','build-entity-a','bob','mill'
    ],$buildingCliEnv)[0]!==0,'M04 CLI cannot place a foreign foundation');
    [$status,$payload]=executeMap([
        'm04-construction-world','building-inspect','alice','cli-mill-a'
    ],$buildingCliEnv);
    $report=json_decode($payload,true,16,JSON_THROW_ON_ERROR);
    check($status===0 && $report['data']['level']===0
        && $report['data']['next']['costs']===['grain'=>11,'wood'=>7],
        'M04 CLI privately inspects immutable next-level costs');
    check(executeMap([
        'm04-construction-world','building-inspect','bob','cli-mill-a'
    ],$buildingCliEnv)[0]!==0,'M04 CLI refuses other actor building inspection');
    check(executeMap(['m04-other-world','building-type','mill'],$buildingCliEnv)[0]!==0,
        'M04 CLI denies world outside approved registry');
} finally {
    unlink($registryFile);
}
