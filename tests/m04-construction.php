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
$buildClock->advanceTo(1016);
$bobPending=$start('bob','bob-pending-cancel','build-mill-b','build-b','mill');
check($construction->balanceForActor('bob','build-b','wood',$buildRules->commands)->stock===93,
 'M04 cancellation fixture debited before compensation');
$buildClock->advanceTo(1017);
$bobCancel=new Command($buildWorld,'bob-cancel-before-due','building.cancel',[
    'building'=>'build-mill-b','account'=>'build-b','type'=>'mill'
]);
$cancelled=$construction->executeForActor('bob',$bobCancel,$buildRules->commands);
check($cancelled->scheduledIds===[],'M04 cancellation schedules no new event');
check($construction->executeForActor('bob',$bobCancel,$buildRules->commands)==$cancelled,
 'M04 identical cancellation receipt is idempotent');
check($construction->balanceForActor('bob','build-b','wood',$buildRules->commands)->stock===100
 && $construction->balanceForActor('bob','build-b','grain',$buildRules->commands)->stock===80,
 'M04 cancellation restores both resources without hidden loss');
check($buildStore->own('bob','build-mill-b')['pending']===null
 && $buildStore->own('bob','build-mill-b')['level']===0,
 'M04 canceled stage has no visible pending construction');
rejects(fn()=> $construction->executeForActor('bob',
    new Command($buildWorld,'bob-double-cancel','building.cancel',[
        'building'=>'build-mill-b','account'=>'build-b','type'=>'mill'
    ]),$buildRules->commands),DomainException::class,
 'M04 repeated distinct cancellation cannot double reimburse');
$buildClock->advanceTo(1021);
rejects(fn()=> $construction->executeForActor('bob',
    new Command($buildWorld,'cancel-after-due','building.cancel',[
        'building'=>'build-mill-b','account'=>'build-b','type'=>'mill'
    ]),$buildRules->commands),DomainException::class,
 'M04 due-event processing barrier remains intact for canceled events');
check($construction->processDue($buildRules->events)===[$bobPending->scheduledIds[0]],
 'M04 original canceled event finishes as deterministic no-op');
check($buildStore->own('bob','build-mill-b')['level']===0,
 'M04 worker cannot complete canceled construction');
$refunds=db()->prepare("SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id=? AND reason='construction_refund'");
$refunds->execute([$buildWorld]);
check((int)$refunds->fetchColumn()===2,'M04 both refunded resources have ledger evidence');
check((new PdoScheduler(db(),$buildWorld,$buildClock,$buildRules))
    ->verifyReplay($buildRules->commands,$buildRules->events)->entries===13,
 'M04 replay includes original start, compensating cancellation and no-op event');


/* Capacity saturation: cancellation must preserve every paid unit in durable
 * credits, even when later transfers filled all physical storage. */
$capJson=json_decode(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'),true,16,JSON_THROW_ON_ERROR);
$capJson['buildings']=array_values(array_filter($capJson['buildings'],static fn(array $row):bool=>$row['type']!=='vault'));
foreach ($capJson['resources'] as &$resource) {
    $resource['capacity']=100;
    $resource['initial_stock']=100;
}
unset($resource);
$capPack=GamePackLoader::load(json_encode($capJson,JSON_THROW_ON_ERROR));
$capRules=$capPack->rules();
$capWorld='m04-refund-capacity';
$capClock=new FrozenClock(1100);
$capScheduler=new PdoScheduler(db(),$capWorld,$capClock,$capRules);
$capScheduler->initialize();
$capMembers=new PdoMemberships(db(),$capWorld);
$capMembers->grant('alice');$capMembers->grant('bob');
$capMembers->assign('cap-alice','alice');
$capMembers->assign('cap-bob','bob');
$capEntities=new PdoEntities(db(),$capWorld);
$capEntities->addLocation('cap-place');
$capEntities->provision('cap-entity','cap-place','alice','settlement');
$capPlots=new PdoTerritories(db(),$capWorld);
$capPlots->define('cap-plot','cap-place','town');
$capPlots->assign('cap-plot','alice');
$capBuildings=new PdoBuildings(db(),$capWorld);
$capBuildings->define('mill',$capPack->buildings['mill']);
$capBuildings->establish('cap-mill','cap-plot','cap-entity','alice','mill');
foreach (['cap-alice'=>'alice','cap-bob'=>'bob'] as $account=>$owner) {
    foreach ($capPack->resources as $resource) {
        $capScheduler->executeForActor($owner,new Command($capWorld,'cap-open-'.$account.'-'.$resource->id,
            'economy.open',['account'=>$account,'resource'=>$resource->id]),$capRules->commands);
    }
}
$capExecute=function(string $actor,string $request,string $type,array $payload)
    use($capScheduler,$capRules,$capWorld) {
    return $capScheduler->executeForActor($actor,new Command($capWorld,$request,$type,$payload),$capRules->commands);
};
$capStart=$capExecute('alice','cap-start','building.start',[
    'building'=>'cap-mill','account'=>'cap-alice','type'=>'mill'
]);
check(count($capStart->scheduledIds)===1,'M04 saturated refund fixture starts durable construction');
foreach (['wood'=>7,'grain'=>11] as $resource=>$units) {
    $capExecute('bob','cap-fill-'.$resource,'economy.transfer',[
        'from'=>'cap-bob','to'=>'cap-alice','resource'=>$resource,'units'=>$units
    ]);
}
check($capScheduler->balanceForActor('alice','cap-alice','wood',$capRules->commands)->stock===100
    && $capScheduler->balanceForActor('alice','cap-alice','grain',$capRules->commands)->stock===100,
    'M04 competing transfers fill all space after costs were spent');
$capClock->advanceTo(1101);
$capExecute('alice','cap-cancel','building.cancel',[
    'building'=>'cap-mill','account'=>'cap-alice','type'=>'mill'
]);
check($capBuildings->own('alice','cap-mill')['refundPending']===['grain'=>11,'wood'=>7],
    'M04 capacity-saturated cancellation records every unit in durable account credits');
check($capScheduler->balanceForActor('alice','cap-alice','wood',$capRules->commands)->stock===100,
    'M04 refund preserves physical maximum capacity');
$capClaim=static fn(string $id,string $resource)=>$capExecute('alice',$id,'building.claim',[
    'account'=>'cap-alice','resource'=>$resource
]);
rejects(fn()=> $capClaim('cap-too-full','wood'),DomainException::class,
    'M04 construction credit claim refuses full physical storage without losing debt');
check($capBuildings->own('alice','cap-mill')['refundPending']===['grain'=>11,'wood'=>7],
    'M04 failed credit claim leaves both resource claims unspent');
foreach (['wood'=>7,'grain'=>11] as $resource=>$units) {
    $capExecute('alice','cap-return-'.$resource,'economy.transfer',[
        'from'=>'cap-alice','to'=>'cap-bob','resource'=>$resource,'units'=>$units
    ]);
}
$capWoodClaim=$capClaim('cap-claim-wood','wood');
check($capClaim('cap-claim-wood','wood')==$capWoodClaim,
    'M04 exact construction credit claim is idempotent across replay');
$capClaim('cap-claim-grain','grain');
check($capScheduler->balanceForActor('alice','cap-alice','wood',$capRules->commands)->stock===100
    && $capScheduler->balanceForActor('alice','cap-alice','grain',$capRules->commands)->stock===100
    && $capBuildings->own('alice','cap-mill')['refundPending']===[],
    'M04 delayed two-resource refund can be fully collected without creating or losing units');
rejects(fn()=> $capClaim('cap-duplicate-claim','wood'),DomainException::class,
    'M04 credited units cannot be claimed twice with a different request ID');
$capLedger=db()->prepare("SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id=? AND reason='construction_claim'");
$capLedger->execute([$capWorld]);
check((int)$capLedger->fetchColumn()===2,
    'M04 delayed refunds are represented by two separate positive ledger movements');
$capClock->advanceTo(1105);
check($capScheduler->processDue($capRules->events)===$capStart->scheduledIds,
    'M04 canceled saturation case has one safe due-event no-op');
check($capBuildings->own('alice','cap-mill')['level']===0,
    'M04 canceled stage cannot level-up after refund collection');
check((new PdoScheduler(db(),$capWorld,$capClock,$capRules))
    ->verifyReplay($capRules->commands,$capRules->events)->entries===13,
    'M04 saturation, transfer, cancellation, claim, and no-op replay is deterministic');
