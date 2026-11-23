<?php
declare(strict_types=1);
use Aeternum\Core\{Command,EconomyHttpApi,FrozenClock,GamePackLoader,PdoIdentity,PdoScheduler,PdoSessions,PlayerProvisioner,ProgressionHttpApi,WorldWorker};

// Real MariaDB transactional score tests; scope all PDO handles so the
// later legacy parallel tests do not exhaust connections.
(static function(): void {
    $pack=GamePackLoader::load(file_get_contents(
        __DIR__.'/../games/reference-guild/versions/0.2.0/manifest.json'));
    $legacy=GamePackLoader::load(file_get_contents(
        __DIR__.'/../games/reference-guild/manifest.json'));
    $world='m15-scores-world';
    $clock=new FrozenClock(15000);
    $pdo=db();
    $rules=$pack->rules();
    $identity=new PdoIdentity($pdo);
    $alice=$identity->register('m15-alice','m15 score laboratory password alice');
    $bob=$identity->register('m15-bob','m15 score laboratory password bobby');
    $provision=new PlayerProvisioner($pdo,$clock);
    $provision->provision($world,$alice,$pack);
    $provision->provision($world,$bob,$pack);
    $sessions=new PdoSessions($pdo,$clock);
    $tokenA=$sessions->login('m15-alice','m15 score laboratory password alice');
    $tokenB=$sessions->login('m15-bob','m15 score laboratory password bobby');
    check(is_string($tokenA)&&is_string($tokenB),'M15 scoring test creates two authenticated players');
    $scores=new ProgressionHttpApi($pdo,$clock,[$world=>$pack,'m15-legacy'=>$legacy]);
    $scorePath=static fn(string $account):string=>'/v1/worlds/'.$world.'/progression/'.$account;
    $read=static fn(string $token,string $account):array=>
        $scores->handle('GET',$scorePath($account),'Bearer '.$token);
    $initial=$read($tokenA,$alice);
    check($initial[0]===200 && $initial[1]['data']['global']===0
        && $initial[1]['data']['military']===0,
        'M15 newly provisioned guild player starts at zero earned score');
    check($scores->handle('GET',$scorePath($bob),'Bearer '.$tokenA)[0]===404,
        'M15 foreign player score is never revealed');
    check($scores->handle('GET',$scorePath($alice),'')[0]===401
        && $scores->handle('POST',$scorePath($alice),'Bearer '.$tokenA)[0]===405,
        'M15 progression endpoint requires authenticated GET');
    check($scores->handle('GET','/v1/worlds/m15-legacy/progression/'.$alice,
        'Bearer '.$tokenA)[0]===404,'M15 old worlds remain inert with no scores');

    $api=new EconomyHttpApi($pdo,$clock,[$world=>$rules]);
    $commandPath='/v1/worlds/'.$world.'/economy/commands';
    $submit=static function(string $token,string $id,string $type,array $payload)
        use($api,$commandPath):array {
        return $api->handle('POST',$commandPath,'Bearer '.$token,
            json_encode(['id'=>$id,'type'=>$type,'payload'=>$payload],JSON_THROW_ON_ERROR));
    };
    $offer=['offer'=>'m15-trade','seller'=>$alice,'giveResource'=>'spice',
        'giveUnits'=>3,'wantResource'=>'silver','wantUnits'=>6];
    check($submit($tokenA,'m15-create','market.create',$offer)[0]===200,
        'M15 seller may open a genuine market offer');
    $purchase=['offer'=>'m15-trade','seller'=>$alice,'buyer'=>$bob];
    $buy=$submit($tokenB,'m15-buy','market.accept',$purchase);
    check($buy[0]===200,'M15 second player completes a persistent barter');
    $retry=$submit($tokenB,'m15-buy','market.accept',$purchase);
    check($retry[0]===200
        && json_encode($retry[1],JSON_THROW_ON_ERROR)===json_encode($buy[1],JSON_THROW_ON_ERROR),
        'M15 identical market receipt cannot award trade points twice');
    check($read($tokenA,$alice)[1]['data']['development']===6
        && $read($tokenB,$bob)[1]['data']['development']===6,
        'M15 filled exchange is scored once for each participant');

    $place=static function(string $id,string $type) use($alice,$tokenA,$submit):void {
        check($submit($tokenA,'m15-place-'.$id,'building.place',
            ['building'=>$id,'entity'=>$alice,'territory'=>$alice,'account'=>$alice,
                'type'=>$type])[0]===200,'M15 approved personal building foundation is placed');
    };
    $start=static function(string $id,string $type) use($alice,$tokenA,$submit):void {
        check($submit($tokenA,'m15-start-'.$id,'building.start',
            ['building'=>$id,'account'=>$alice,'type'=>$type])[0]===200,
            'M15 building completion event is scheduled through real actor intake');
    };
    $place('m15-hall','market_hall');
    $start('m15-hall','market_hall');
    check($read($tokenA,$alice)[1]['data']['global']===6,
        'M15 queued but incomplete building cannot award points');
    $clock->advanceTo(15002);
    check($read($tokenA,$alice)[0]===404,
        'M15 due-event barrier prevents a stale score snapshot');
    $worker=new WorldWorker($pdo,$clock);
    check(count($worker->run($world,$pack,10,1000))===1,
        'M15 production worker applies due construction');
    check($read($tokenA,$alice)[1]['data']['development']===18,
        'M15 completed commerce building grants weighted development points');

    $place('m15-tower','watchtower');
    $start('m15-tower','watchtower');
    $clock->advanceTo(15003);
    check(count($worker->run($world,$pack,10,1000))===1,
        'M15 production worker completes defensive building');
    $afterTower=$read($tokenA,$alice);
    check($afterTower[1]['data']['development']===18
        && $afterTower[1]['data']['military']===30
        && $afterTower[1]['data']['global']===48,
        'M15 military and development scores remain separately classified');

    check($submit($tokenA,'m15-upgrade','economy.upgrade',
        ['account'=>$alice,'resource'=>'silver'])[0]===200,
        'M15 earned resource upgrade uses the standard scheduler');
    $clock->advanceTo(15006);
    check(count($worker->run($world,$pack,10,1000))===1,
        'M15 upgrade gains points only after its scheduled completion');
    $final=$read($tokenA,$alice);
    check($final[1]['data']['development']===26
        && $final[1]['data']['military']===30
        && $final[1]['data']['global']===56,
        'M15 API returns exact deterministic global, development and military totals');
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    $stateBefore=$scheduler->state();
    check($read($tokenA,$alice)===$final
        && $scheduler->state()===$stateBefore,
        'M15 read-only scoring cannot mutate canonical world state');
    check($scheduler->verifyReplay($rules->commands,$rules->events)->entries>5,
        'M15 replay validates the progression source history after trade and buildings');
})();
