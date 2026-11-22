<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PdoScheduler,FrozenClock,PdoMemberships,PdoEntities,
    PdoTerritories,PdoBuildings,Command};
$legacyBuildPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
check($legacyBuildPack->buildings===[] && $legacyBuildPack->rules()->commands->buildingStages('mill')===null,
 'M04 older pinned pack remains without construction types');
$buildingPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'));
check(count($buildingPack->buildings)===3
    && $buildingPack->rules()->commands->buildingStages('mill')[0]['costs']===['grain'=>11,'wood'=>7],
 'M04 typed manifest loads multi-resource pinned stages');
$invalidBuildPack=json_decode(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'),true);
$invalidBuildPack['buildings'][0]['stages'][0]['costs']['unknown']=1;
rejects(fn()=>GamePackLoader::load(json_encode($invalidBuildPack,JSON_THROW_ON_ERROR)),
 InvalidArgumentException::class,'M04 rejects cost resources missing from pinned pack');
$invalidBuildPack=json_decode(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'),true);
$invalidBuildPack['buildings'][0]['stages'][1]['requires']['missing']=1;
rejects(fn()=>GamePackLoader::load(json_encode($invalidBuildPack,JSON_THROW_ON_ERROR)),
 InvalidArgumentException::class,'M04 rejects absent prerequisite types');

$buildClock=new FrozenClock(1000);
$buildWorld='m04-construction-world';
$buildRules=$buildingPack->rules();
$construction=new PdoScheduler(db(),$buildWorld,$buildClock,$buildRules);
$construction->initialize();
$buildMembers=new PdoMemberships(db(),$buildWorld);
$buildMembers->grant('alice');$buildMembers->grant('bob');
$buildMembers->assign('build-a','alice');
$buildMembers->assign('build-b','bob');
$buildLocations=new PdoEntities(db(),$buildWorld);
foreach(['build-loc-a','build-loc-b'] as $loc) $buildLocations->addLocation($loc);
$buildLocations->provision('build-entity-a','build-loc-a','alice','settlement');
$buildLocations->provision('build-entity-b','build-loc-b','bob','settlement');
$lands=new PdoTerritories(db(),$buildWorld);
$lands->define('build-land-a','build-loc-a','town');
$lands->define('build-land-b','build-loc-b','town');
$lands->assign('build-land-a','alice');
$lands->assign('build-land-b','bob');
$buildStore=new PdoBuildings(db(),$buildWorld);
foreach ($buildingPack->buildings as $type=>$schedule) $buildStore->define($type,$schedule);
$buildStore->establish('build-mill-a','build-land-a','build-entity-a','alice','mill');
$buildStore->establish('build-work-a','build-land-a','build-entity-a','alice','workshop');
$buildStore->establish('build-vault-a','build-land-a','build-entity-a','alice','vault');
$buildStore->establish('build-mill-b','build-land-b','build-entity-b','bob','mill');
foreach (['build-a'=>'alice','build-b'=>'bob'] as $account=>$owner) {
    foreach ($buildingPack->resources as $resource) {
        $construction->executeForActor($owner,new Command($buildWorld,
            'open-'.$account.'-'.$resource->id,'economy.open',
            ['account'=>$account,'resource'=>$resource->id]),$buildRules->commands);
    }
}
$start=function (string $actor,string $request,string $id,string $account,string $type)
    use ($construction,$buildWorld,$buildRules) {
    return $construction->executeForActor($actor,new Command($buildWorld,$request,'building.start',
        ['building'=>$id,'account'=>$account,'type'=>$type]),$buildRules->commands);
};
rejects(fn()=> $start('bob','foreign-claim','build-mill-a','build-b','mill'),
 DomainException::class,'M04 foreign actor cannot start construction');
rejects(fn()=> $start('alice','wrong-type','build-mill-a','build-a','workshop'),
 DomainException::class,'M04 client cannot substitute unpinned building type');
$beforeWood=$construction->balanceForActor('alice','build-a','wood',$buildRules->commands)->stock;
$beforeGrain=$construction->balanceForActor('alice','build-a','grain',$buildRules->commands)->stock;
check($beforeWood===100 && $beforeGrain===80,'M04 fixture initial balances');
rejects(fn()=> $start('alice','vault-insufficient','build-vault-a','build-a','vault'),
 DomainException::class,'M04 insufficient second resource aborts whole multi-cost debit');
check($construction->balanceForActor('alice','build-a','wood',$buildRules->commands)->stock===100
 && $construction->balanceForActor('alice','build-a','grain',$buildRules->commands)->stock===80
 && $construction->pendingCount()===0,
 'M04 failed second resource leaves both balances and event queue untouched');
$first=$start('alice','build-mill-request','build-mill-a','build-a','mill');
$again=$start('alice','build-mill-request','build-mill-a','build-a','mill');
check($again==$first && count($first->scheduledIds)===1,'M04 same request replays exact original receipt');
check($construction->balanceForActor('alice','build-a','wood',$buildRules->commands)->stock===93
 && $construction->balanceForActor('alice','build-a','grain',$buildRules->commands)->stock===69,
 'M04 two resources atomically consumed and projected');
check($construction->pendingCount()===1,'M04 single durable completion event');
check($buildStore->own('alice','build-mill-a')['pending']===$first->scheduledIds[0],
 'M04 private building projection displays persisted pending stage');
rejects(fn()=> $start('alice','parallel-build','build-mill-a','build-a','mill'),
 DomainException::class,'M04 second command cannot double spend pending stage');
rejects(fn()=> $start('alice','build-mill-request','build-work-a','build-a','workshop'),
 DomainException::class,'M04 same client request with changed envelope is a conflict');
check($construction->balanceForActor('alice','build-a','wood',$buildRules->commands)->stock===93,
 'M04 rejected commands consume zero resources');
$buildClock->advanceTo(1005);
check($construction->processDue($buildRules->events)===[$first->scheduledIds[0]],
 'M04 worker activates level one exactly once at logical due timestamp');
check($buildStore->own('alice','build-mill-a')['level']===1
 && $buildStore->own('alice','build-mill-a')['pending']===null,
 'M04 level one survives durable completion');
rejects(fn()=> $start('alice','premature-mill2','build-mill-a','build-a','mill'),
 DomainException::class,'M04 level two refuses missing workshop prerequisite');
$work=$start('alice','build-work-request','build-work-a','build-a','workshop');
$buildClock->advanceTo(1010);
check($construction->processDue($buildRules->events)===[$work->scheduledIds[0]],
 'M04 independently scheduled workshop completed');
$second=$start('alice','build-mill2-request','build-mill-a','build-a','mill');
$buildClock->advanceTo(1015);
check($construction->processDue($buildRules->events)===[$second->scheduledIds[0]],
 'M04 second stage commits after prerequisite');
check($buildStore->own('alice','build-mill-a')['level']===2
 && $buildStore->own('alice','build-mill-a')['next']===null,
 'M04 persisted max building level');
rejects(fn()=> $start('alice','extra-mill','build-mill-a','build-a','mill'),
 DomainException::class,'M04 terminal level cannot be exceeded');
check($construction->balanceForActor('alice','build-a','wood',$buildRules->commands)->stock===79
 && $construction->balanceForActor('alice','build-a','grain',$buildRules->commands)->stock===38,
 'M04 multi-stage multi-resource cost conservation');
$statement=db()->prepare("SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id=? AND reason='construction_cost'");
$statement->execute([$buildWorld]);
check((int)$statement->fetchColumn()===6,'M04 six construction ledger debit entries are durable');
check($construction->verifyReplay($buildRules->commands,$buildRules->events)->entries===10,
 'M04 all four opens and three building starts plus three completion events replay');
check((new PdoScheduler(db(),$buildWorld,$buildClock,$buildRules))
    ->verifyReplay($buildRules->commands,$buildRules->events)->entries===10,
 'M04 reconnect replays same journal and ledger across connections');
check((new PdoBuildings(db(),$buildWorld))->own('alice','build-mill-a')['level']===2,
 'M04 durable private building state survives reconnect');
