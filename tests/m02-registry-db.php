<?php
declare(strict_types=1);
use Aeternum\Core\{WorldPackRegistry,GamePackLoader,FrozenClock,PdoScheduler,Command,PdoIdentity,PlayerProvisioner,PdoSessions,PlayerDiscoveryApi,EconomyHttpApi};
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
    check($operatorScheduler->balanceForActor($operatorActor,$operatorActor,'wood',$operatorRules->commands)->stock>=100,
        'configured world CLI provision persists authorized actor and at least the starting stock');
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


// End-to-end PHP HTTP dispatcher coverage for the third pack, without
// touching the front controller, and without granting cross-world access.
$apiClock=new FrozenClock(600);
$apiActor=(new PdoIdentity(db()))->register('m02-third-api','valid third-pack test password');
(new PlayerProvisioner(db(),$apiClock))->provision('m02-third-api-world',$apiActor,$pack);
$apiToken=(new PdoSessions(db(),$apiClock))->login('m02-third-api','valid third-pack test password');
check(is_string($apiToken) && strlen($apiToken)===64,'third pack API player authenticated with opaque session');
$apiBearer='Bearer '.$apiToken;
$discovery=new PlayerDiscoveryApi(db(),$apiClock,[
    'm02-new-world'=>$pack,'m02-third-api-world'=>$pack
]);
[$code,$result]=$discovery->handle('GET',$apiBearer);
check($code===200 && count($result['data']['worlds'])===1
    && $result['data']['worlds'][0]['world']==='m02-third-api-world'
    && $result['data']['worlds'][0]['resources']===['amber'],
    'authenticated discovery lists third pack only where membership is active');
$apiRules=$pack->rules();
$economy=new EconomyHttpApi(db(),$apiClock,[
    'm02-new-world'=>$reloaded,
    'm02-third-api-world'=>$apiRules
]);
$apiBase='/v1/worlds/m02-third-api-world/economy/';
[$code,$result]=$economy->handle('GET',$apiBase.'balances/'.$apiActor.'/amber',$apiBearer);
check($code===200 && $result['data']->stock===90,
    'third pack public dispatcher resolves its own resource and authorized account');
check($economy->handle('GET','/v1/worlds/m02-new-world/economy/balances/'.$apiActor.'/amber',$apiBearer)[0]===409,
    'third pack cannot read another configured world without membership');
$httpCommand=json_encode(['id'=>'third-http-upgrade','type'=>'economy.upgrade',
    'payload'=>['account'=>$apiActor,'resource'=>'amber']],JSON_THROW_ON_ERROR);
[$code,$result]=$economy->handle('POST',$apiBase.'commands',$apiBearer,$httpCommand);
check($code===200,'third pack accepts real authenticated progression command through dispatcher');
[$replayCode,$retry]=$economy->handle('POST',$apiBase.'commands',$apiBearer,$httpCommand);
check($replayCode===200 && $retry===$result,'third pack HTTP retry returns same durable receipt');
$apiClock->advanceTo(690);
$apiWorker=new PdoScheduler(db(),'m02-third-api-world',$apiClock,$apiRules);
check(count($apiWorker->processDue($apiRules->events))===1,
    'third pack verified event completes on actual MariaDB after HTTP request');
check($apiWorker->verifyReplay($apiRules->commands,$apiRules->events)->entries===3,
    'third pack HTTP, provisioning and delayed event replay deterministically');
