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
