<?php
declare(strict_types=1);
use Aeternum\Core\{WorldPackRegistry,GamePackLoader,FrozenClock,PdoScheduler,Command};
$root=__DIR__.'/fixtures/m02-packs';
$bytes=file_get_contents($root.'/sdk-fixture/manifest.json');
$config=json_encode(['registry_api'=>'1','worlds'=>[
    ['world'=>'m02-new-world','pack'=>'sdk-fixture','version'=>'1.0.0','sha256'=>hash('sha256',$bytes)]
]],JSON_THROW_ON_ERROR);
$pack=WorldPackRegistry::load($config,$root)['m02-new-world'];
$clock=new FrozenClock(100);
$rules=$pack->rules();
$game=new PdoScheduler(db(),'m02-new-world',$clock,$rules);
$game->initialize();
$open=$pack->sdk()->command('m02-new-world','sdk-open','economy.open',['account'=>'m02-account','resource'=>'amber']);
$game->execute($open,$rules->commands);
$upgrade=$pack->sdk()->command('m02-new-world','sdk-upgrade','economy.upgrade',['account'=>'m02-account','resource'=>'amber']);
$receipt=$game->execute($upgrade,$rules->commands);
check(count($receipt->scheduledIds)===1,'third pack schedules a real timed action via typed SDK');
$clock->advanceTo(250);
$reloaded=WorldPackRegistry::load($config,$root)['m02-new-world']->rules();
$game=new PdoScheduler(db(),'m02-new-world',$clock,$reloaded);
check($game->processDue($reloaded->events)===$receipt->scheduledIds,'third pack event hook validated under real MariaDB');
$balance=$reloaded->commands->balanceAt($game->state(),'m02-account','amber',$clock->now());
check($balance->stock===125 && $balance->amount===35,'third pack applies verified upgrade and lazy production');
check($game->verifyReplay($reloaded->commands,$reloaded->events)->entries===3,'third pack deterministic replay survives reload');
$changed=GamePackLoader::load($bytes."\n")->rules();
$wrong=new PdoScheduler(db(),'m02-new-world',$clock,$changed);
rejects(fn()=> $wrong->state(),DomainException::class,'replay-pinned third world refuses even whitespace artifact alteration');
$modifiedBytes=str_replace('"version": "1.0.0"','"version": "1.0.1"',$bytes);
$modified=GamePackLoader::load($modifiedBytes)->rules();
$wrong=new PdoScheduler(db(),'m02-new-world',$clock,$modified);
rejects(fn()=> $wrong->initialize(),DomainException::class,'world migration to new pack version is never implicit');
$old=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'))->rules();
$legacyWorld=new PdoScheduler(db(),'m02-legacy-rule-world',$clock,$old);
$legacyWorld->initialize();
$legacyWorld->execute(new Command('m02-legacy-rule-world','open','economy.open',['account'=>'old-account','resource'=>'wood']),$old->commands);
$legacyReload=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'))->rules();
$legacyWorld=new PdoScheduler(db(),'m02-legacy-rule-world',$clock,$legacyReload);
check($legacyWorld->verifyReplay($legacyReload->commands,$legacyReload->events)->entries===1,'pre-M02 rule identity remains compatible with old worlds');


// Exercise the real operator binaries on an approved registry, including a
// fresh persisted account. No shell interpolation and no public endpoints.
$referenceFile=__DIR__.'/../games/reference-medieval/manifest.json';
$referenceBytes=file_get_contents($referenceFile);
$referencePack=GamePackLoader::load($referenceBytes);
$operatorConfig=json_encode(['registry_api'=>'1','worlds'=>[
    ['world'=>'m02-cli-world','pack'=>$referencePack->id,
     'version'=>$referencePack->version,'sha256'=>$referencePack->digest]
]],JSON_THROW_ON_ERROR);
$operatorPath=tempnam(sys_get_temp_dir(),'aeternum-m02-');
if ($operatorPath===false || file_put_contents($operatorPath,$operatorConfig)===false) {
    throw new RuntimeException('Cannot create temporary approved world registry');
}
$operatorEnv=[
    'AETERNUM_WORLD_REGISTRY'=>$operatorPath,
    'AETERNUM_DSN'=>getenv('AETERNUM_TEST_DSN'),
    'AETERNUM_DB_USER'=>getenv('AETERNUM_TEST_USER')?:'',
    'AETERNUM_DB_PASSWORD'=>getenv('AETERNUM_TEST_PASSWORD')?:'',
    'PATH'=>getenv('PATH')?:'/usr/bin'
];
$invokeRegistryCli=static function(array $arguments,string $stdin='') use ($operatorEnv):array {
    $proc=proc_open([PHP_BINARY,...$arguments],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$operatorEnv);
    if (!is_resource($proc)) throw new RuntimeException('Cannot spawn world operator CLI');
    fwrite($pipes[0],$stdin);
    fclose($pipes[0]);
    $stdout=stream_get_contents($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($proc),$stdout,$stderr];
};
try {
    [$result,$output]=$invokeRegistryCli([__DIR__.'/../bin/registry-status.php',$operatorPath]);
    $validated=json_decode($output,true,8,JSON_THROW_ON_ERROR);
    check($result===0 && $validated['worlds'][0]['rulesKey']===$referencePack->rules()->key,
        'real registry-status CLI validates immutable rules key');
    [$result,$output]=$invokeRegistryCli([
        __DIR__.'/../bin/provision.php','reference-medieval','m02-cli-world','m02-cli-player'
    ],"strong m02 test secret\n");
    check($result===0,'real operator CLI provisions configured world without hardcoded world name');
    $provisioned=json_decode($output,true,8,JSON_THROW_ON_ERROR);
    $operatorActor=$provisioned['account'];
    $operatorRules=$referencePack->rules();
    $operatorScheduler=new PdoScheduler(db(),'m02-cli-world',new FrozenClock(time()),$operatorRules);
    check($operatorScheduler->balanceForActor($operatorActor,$operatorActor,'wood',$operatorRules->commands)->stock===100,
        'configured world CLI provision persists authorized actor and starting stock');
    check($operatorScheduler->verifyReplay($operatorRules->commands,$operatorRules->events)->entries===count($referencePack->resources),
        'operator-created world replays all resource provisioning');
    [$result,$output]=$invokeRegistryCli([__DIR__.'/../bin/worker.php','reference-medieval','m02-cli-world']);
    $workerJson=json_decode($output,true,8,JSON_THROW_ON_ERROR);
    check($result===0 && $workerJson['world']==='m02-cli-world',
        'real scheduled-worker CLI resolves approved configurable world');
    check($invokeRegistryCli([__DIR__.'/../bin/worker.php','reference-industrial','m02-cli-world'])[0]!==0,
        'worker rejects a pack not assigned to its approved world');
    check($invokeRegistryCli([__DIR__.'/../bin/provision.php','reference-medieval','unknown-world','m02-rogue'],
        "strong m02 test secret\n")[0]!==0,'provision CLI rejects unapproved world before identity creation');
} finally {
    unlink($operatorPath);
}
