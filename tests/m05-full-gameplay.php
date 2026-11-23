<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PdoIdentity,PlayerProvisioner,PdoScheduler,
    PdoBuildings,PdoSessions,PdoSettlementStatus,WorldBuildingHttpApi,FrozenClock,Command};
$playPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/versions/0.3.0/manifest.json'));
$playRules=$playPack->rules();
$playWorld='m05-playthrough';
$playClock=new FrozenClock(1000);
$playIdentities=new PdoIdentity(db());
$playA=$playIdentities->register('medieval-play-a','medieval gameplay test password');
$playB=$playIdentities->register('medieval-play-b','medieval gameplay test password');
$provision=new PlayerProvisioner(db(),$playClock);
$provision->provision($playWorld,$playA,$playPack);
$provision->provision($playWorld,$playB,$playPack);
$playStore=new PdoScheduler(db(),$playWorld,$playClock,$playRules);
$playBuildings=new PdoBuildings(db(),$playWorld);
$playStatus=new PdoSettlementStatus(db(),$playWorld,$playRules,$playPack->settlement);
check($playStatus->forActor($playA,$playA)['population']===1
    && $playStatus->forActor($playA,$playA)['victory']['achieved']===false,
    'M05 initial colony has one settler and no premature victory');
$playCommand=function(string $actor,string $id,string $type,array $payload)
    use ($playStore,$playRules,$playWorld) {
    return $playStore->executeForActor($actor,new Command($playWorld,$id,$type,$payload),$playRules->commands);
};
$place=function(string $actor,string $id,string $type)use($playCommand):void {
    $playCommand($actor,'place-'.$id,'building.place',[
        'account'=>$actor,'building'=>$id,'entity'=>$actor,'territory'=>$actor,'type'=>$type
    ]);
};
$start=function(string $actor,string $id,string $type)use($playCommand) {
    return $playCommand($actor,'start-'.$id.'-'.bin2hex(random_bytes(4)),'building.start',[
        'account'=>$actor,'building'=>$id,'type'=>$type
    ]);
};
$advance=function(int $time)use($playClock,$playStore,$playRules):void {
    $playClock->advanceTo($time);
    $playStore->processDue($playRules->events);
};
$place($playA,'play-a-lumber','lumberyard');
$place($playA,'play-a-farm','farm');
$place($playA,'play-a-granary','granary');
$place($playA,'play-a-keep','keep');
$place($playB,'play-b-farm','farm');
$place($playB,'play-b-lumber','lumberyard');
rejects(fn()=> $playCommand($playB,'steal-a','building.place',[
    'account'=>$playB,'building'=>'stolen','entity'=>$playB,'territory'=>$playA,'type'=>'lumberyard'
]),DomainException::class,'M05 foreign colony cannot place foundation');
rejects(fn()=> $playCommand($playA,'feed-before-farm','settlement.feed',['account'=>$playA]),
    DomainException::class,'M05 villagers require a completed farm');
rejects(fn()=> $playCommand($playA,'work-before-lumber','settlement.work',['account'=>$playA]),
    DomainException::class,'M05 work requires a productive lumberyard and workforce');
rejects(fn()=> $start($playA,'play-a-keep','keep'),DomainException::class,
    'M05 stronghold cannot bypass prerequisite chain');
$start($playA,'play-a-lumber','lumberyard');
$start($playB,'play-b-farm','farm');
$advance(1020);
check($playBuildings->own($playA,'play-a-lumber')['level']===1
    && $playBuildings->own($playB,'play-b-farm')['level']===1,
    'M05 independent players progress separately on same world');
$start($playA,'play-a-farm','farm');
$advance(1040);
$feed1=$playCommand($playA,'first-villager','settlement.feed',['account'=>$playA]);
check($playCommand($playA,'first-villager','settlement.feed',['account'=>$playA])==$feed1
    && $playStatus->forActor($playA,$playA)['population']===2,
    'M05 feeding is atomic and exactly once on identical request replay');
$woodBefore=$playStore->balanceForActor($playA,$playA,'wood',$playRules->commands)->stock;
$grainBefore=$playStore->balanceForActor($playA,$playA,'grain',$playRules->commands)->stock;
$work=$playCommand($playA,'first-wood-work','settlement.work',['account'=>$playA]);
check($playCommand($playA,'first-wood-work','settlement.work',['account'=>$playA])==$work
    && $playStore->balanceForActor($playA,$playA,'wood',$playRules->commands)->stock===$woodBefore+15
    && $playStore->balanceForActor($playA,$playA,'grain',$playRules->commands)->stock===$grainBefore-2,
    'M05 workforce converts food to wood without double grant');
rejects(fn()=> $playCommand($playA,'instant-work-repeat','settlement.work',['account'=>$playA]),
    DomainException::class,'M05 cooldown refuses instant resource farming');
check($playStatus->forActor($playB,$playB)['population']===1
    && !$playStatus->forActor($playB,$playB)['victory']['achieved'],
    'M05 another colony receives no population or victory from foreign work');
$advance(1100);
$start($playA,'play-a-lumber','lumberyard');
$advance(1145);
$start($playA,'play-a-farm','farm');
$advance(1205);
$start($playA,'play-a-granary','granary');
$advance(1235);
$start($playA,'play-a-keep','keep');
$advance(1295);
check($playStatus->forActor($playA,$playA)['victory']['achieved']===false,
    'M05 first stronghold does not satisfy server-side win condition');
$advance(1600);
$start($playA,'play-a-keep','keep');
$advance(1720);
$beforeFeed=$playStatus->forActor($playA,$playA);
check($beforeFeed['victory']['buildings']['keep']['current']===2
    && $beforeFeed['victory']['achieved']===false
    && $beforeFeed['capacity']>=6,
    'M05 completed castle without population does not falsely win');
for($i=2;$i<=5;$i++) {
    $playCommand($playA,'grow-village-'.$i,'settlement.feed',['account'=>$playA]);
}
$won=$playStatus->forActor($playA,$playA);
check($won['population']===6 && $won['victory']['achieved']
    && $won['victory']['buildings']['granary']['current']===1
    && $won['victory']['buildings']['farm']['current']===2,
    'M05 complete medieval campaign reaches authoritative persistent victory');
rejects(fn()=> $playStatus->forActor($playA,$playB),DomainException::class,
    'M05 victory projection does not reveal another account');
rejects(fn()=> $playCommand($playB,'foreign-feed','settlement.feed',['account'=>$playA]),
    DomainException::class,'M05 workforce commands cannot debit another player');
$playSnapshot=$playStore->state();
check((new PdoSettlementStatus(db(),$playWorld,$playRules,$playPack->settlement))
    ->forActor($playA,$playA)['victory']['achieved'],
    'M05 reconnect preserves authoritative win and population');
$replay=$playStore->verifyReplay($playRules->commands,$playRules->events);
check($replay->entries>=20 && $playStore->state()===$playSnapshot,
    'M05 full campaign journal replays without altering canonical economy');
$token=(new PdoSessions(db(),$playClock))->login('medieval-play-a','medieval gameplay test password');
$api=new WorldBuildingHttpApi(db(),$playClock,[$playWorld=>$playPack]);
$uri='/v1/worlds/'.$playWorld.'/settlement/status/'.$playA;
check($api->handle('GET',$uri,'Bearer '.$token)[1]['data']['victory']['achieved']===true,
    'M05 authenticated gameplay API reports server-side win after reconnect');
check($api->handle('GET','/v1/worlds/'.$playWorld.'/settlement/status/'.$playB,'Bearer '.$token)[0]===404,
    'M05 authenticated gameplay API rejects other settlement');
check($api->handle('POST',$uri,'Bearer '.$token)[0]===405
    && $api->handle('GET',$uri,'')[0]===401,
    'M05 status is read-only and authenticated');
$legacyPlay=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/versions/0.2.0/manifest.json'));
rejects(fn()=> $legacyPlay->rules()->commands->apply(
    new Command('old-medieval','no-colony-action','settlement.feed',['account'=>'old']),[],0
),DomainException::class,'M05 older pinned world cannot invoke unpinned population mechanics');

// Keep the long-lived integration runner below MariaDB's connection ceiling.
// Anonymous PDO clients already expire; these fixtures own explicit clients.
unset($advance,$start,$place,$playCommand,$playIdentities,$provision,
    $playStore,$playBuildings,$playStatus,$api);
