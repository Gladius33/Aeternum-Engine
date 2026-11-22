<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,PdoIdentity,PdoSessions,FrozenClock,
    Command,EconomyCommandHandler,EconomyEventHandler,ResourceDefinition,RuleSet,EconomyHttpApi};
$m03Clock=new FrozenClock(100);
$m03Economy=new EconomyCommandHandler([
    new ResourceDefinition('ore',100,100,100,10),
    new ResourceDefinition('coin',0,100,0,10)
]);
$m03Rules=new RuleSet('m03-projection','1',hash('sha256','m03-projection'),
    $m03Economy,new EconomyEventHandler($m03Economy));
$m03World='m03-market-world';
$m03Db=new PdoScheduler(db(),$m03World,$m03Clock,$m03Rules);
$m03Db->initialize();
$m03Members=new PdoMemberships(db(),$m03World);
$m03Identity=new PdoIdentity(db());
$m03Actors=[];
foreach (['seller','buyer'] as $role) {
    $actor=$m03Identity->register('m03-'.$role,'m03 reproducible test password');
    $m03Actors[$role]=$actor;
    $m03Members->grant($actor);
    $m03Members->assign($role,$actor);
    foreach (['ore','coin'] as $resource) {
        $m03Db->executeForActor($actor,new Command($m03World,'opening-'.$resource,
            'economy.open',['account'=>$role,'resource'=>$resource]),$m03Economy);
    }
}
$m03Market=fn(string $id,string $type,array $payload):Command=>
    new Command($m03World,$id,$type,$payload);
$terms=['offer'=>'expiring','seller'=>'seller','giveResource'=>'ore','giveUnits'=>10,
    'wantResource'=>'coin','wantUnits'=>1,'expiresAt'=>120];
$created=$m03Db->executeForActor($m03Actors['seller'],$m03Market('create','market.create',$terms),$m03Economy);
check(count($created->scheduledIds)===1,'M03 persisted expiring escrow schedules exactly one timed event');
$offer=db()->query("SELECT status,expires_at,give_units FROM engine_market_offers WHERE world_id='m03-market-world' AND offer_id='expiring'")->fetch(PDO::FETCH_ASSOC);
check($offer['status']==='open' && (int)$offer['expires_at']===120 && (int)$offer['give_units']===10,
    'M03 indexed SQL offer is written in same commit as escrow');
check(array_column($m03Db->marketForActor($m03Actors['buyer'])['offers'],'id')===['expiring'],
    'M03 SQL cursor returns newly committed offer to other active actor');
$balance=db()->query("SELECT stock_units FROM engine_economy_balances WHERE world_id='m03-market-world' AND account_id='seller' AND resource_id='ore'")->fetchColumn();
check((int)$balance===90,'M03 relational stock mirrors escrow-debited source');
$m03Clock->advanceTo(120);
check($m03Db->processDue($m03Rules->events)===$created->scheduledIds,
    'M03 worker expires offer exactly at logical due timestamp');
check($m03Db->processDue($m03Rules->events)===[],'M03 expiration event cannot replay twice');
$refund=db()->query("SELECT claimable_units FROM engine_market_refunds WHERE world_id='m03-market-world' AND account_id='seller' AND resource_id='ore'")->fetchColumn();
check((int)$refund===10,'M03 full capacity preserves all ten units in separate durable refund balance');
check($m03Db->marketForActor($m03Actors['buyer'])['offers']===[],'M03 expired offers disappear from SQL market');
check($m03Db->state()['market:expiring:status']==='expired',
    'M03 event updates replayable state in same database transaction as projection');
$claim=$m03Market('claim','market.claim',['account'=>'seller','resource'=>'ore']);
rejects(fn()=> $m03Db->executeForActor($m03Actors['seller'],$claim,$m03Economy),
    DomainException::class,'M03 full storage refuses claim while retaining escrow');
$m03Db->executeForActor($m03Actors['seller'],$m03Market('make-room','market.create',
    ['offer'=>'fresh','seller'=>'seller','giveResource'=>'ore','giveUnits'=>10,
     'wantResource'=>'coin','wantUnits'=>1]),$m03Economy);
$claimed=$m03Db->executeForActor($m03Actors['seller'],$claim,$m03Economy);
check($m03Db->executeForActor($m03Actors['seller'],$claim,$m03Economy)==$claimed,
    'M03 claim retry returns identical receipt, never credits twice');
check((int)db()->query("SELECT COUNT(*) FROM engine_market_refunds WHERE world_id='m03-market-world'")->fetchColumn()===0,
    'M03 claimed refund row removed atomically');
check($m03Db->balanceForActor($m03Actors['seller'],'seller','ore',$m03Economy)->stock===100,
    'M03 seller recovers full stock without violating capacity');
check((int)db()->query("SELECT COALESCE(SUM(delta_units),0) FROM engine_resource_ledger WHERE world_id='m03-market-world' AND resource_id='ore'")->fetchColumn()===200,
    'M03 ledger conserves stock, active escrow and claimed returns without double counting');
check($m03Db->verifyReplay($m03Economy,$m03Rules->events)->entries===8,
    'M03 expiry, deferred escrow and claim replay from pinned handlers');

$audit=new \Aeternum\Core\PdoEconomyProjection(db(),$m03World);
check($audit->assertConsistent($m03Db->state())['offers']===2,
    'M03 operator audit matches relational status to pinned scalar state');
db()->exec("UPDATE engine_market_offers SET give_units=99 WHERE world_id='m03-market-world' AND offer_id='fresh'");
rejects(fn()=> $audit->assertConsistent($m03Db->state()),UnexpectedValueException::class,
    'M03 operator audit catches relational tampering not visible in replay journal');
db()->exec("UPDATE engine_market_offers SET give_units=10 WHERE world_id='m03-market-world' AND offer_id='fresh'");
check($audit->assertConsistent($m03Db->state())['offers']===2,
    'M03 restored projection passes independent audit without altering canonical journal');

$m03Foreign=new PdoScheduler(db(),'m03-foreign-world',$m03Clock,$m03Rules);
$m03Foreign->initialize();
(new PdoMemberships(db(),'m03-foreign-world'))->grant($m03Actors['buyer']);
check($m03Foreign->marketForActor($m03Actors['buyer'])===['offers'=>[],'nextAfter'=>null],
    'M03 relational offers isolated between configured worlds');
check((int)db()->query("SELECT COUNT(*) FROM engine_market_offers WHERE world_id='m03-foreign-world'")->fetchColumn()===0,
    'M03 projection never leaks foreign world rows');
$api=new EconomyHttpApi(db(),$m03Clock,[$m03World=>$m03Rules]);
$session=(new PdoSessions(db(),$m03Clock))->login('m03-seller','m03 reproducible test password');
check(is_string($session) && strlen($session)===64,
    'M03 HTTP authorization test owns a valid persisted login session');
check((new PdoSessions(db(),$m03Clock))->resolve($session)===$m03Actors['seller'],
    'M03 HTTP test bearer resolves to the intended seller, not a different actor');
rejects(fn()=> $m03Db->executeForActor($m03Actors['seller'],
    new Command($m03World,'direct-claim-denied','market.claim',
        ['account'=>'buyer','resource'=>'ore']),$m03Economy),
    DomainException::class,'M03 domain adapter refuses cross-owner claims before HTTP dispatch');
$httpBody=json_encode(['id'=>'api-claim-denied','type'=>'market.claim',
    'payload'=>['account'=>'buyer','resource'=>'ore']],JSON_THROW_ON_ERROR);
[$deniedCode,$deniedBody]=$api->handle('POST','/v1/worlds/'.$m03World.'/economy/commands',
    'Bearer '.$session,$httpBody);
check($deniedCode===409,
    'M03 HTTP forbids foreign actor from claiming escrow (actual HTTP '.$deniedCode.
    ', body='.json_encode($deniedBody,JSON_THROW_ON_ERROR).')');


// Never let an unauthorised GET trigger expensive first-use migration.
db()->exec("UPDATE engine_worlds SET economy_projection_version=0 WHERE world_id='m03-market-world'");
rejects(fn()=> $m03Db->balanceForActor('not-a-member','seller','ore',$m03Economy),
    DomainException::class,'M03 unauthorized balance read cannot force first-use backfill');
$beforeVersion=(int)db()->query("SELECT economy_projection_version FROM engine_worlds WHERE world_id='m03-market-world'")->fetchColumn();
check($beforeVersion===0,'M03 denied request leaves migration marker unchanged');
$m03Db->balanceForActor($m03Actors['seller'],'seller','ore',$m03Economy);
$afterVersion=(int)db()->query("SELECT economy_projection_version FROM engine_worlds WHERE world_id='m03-market-world'")->fetchColumn();
check($afterVersion===1,'M03 authenticated read may safely complete versioned backfill');
