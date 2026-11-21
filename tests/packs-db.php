<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,FrozenClock,GamePackLoader,Command};

$packClock = new FrozenClock(100);
foreach ([
    ['reference-medieval','wood',100,30,40,120,50],
    ['reference-industrial','steel',200,75,120,180,110],
] as [$packId,$resource,$initial,$baseRate,$cost,$duration,$upgradedRate]) {
    $bytes=file_get_contents(__DIR__.'/../games/'.$packId.'/manifest.json');
    $rules=GamePackLoader::load($bytes)->rules();
    $clock=new FrozenClock(100);
    $world='pack-'.$packId;
    $game=new PdoScheduler(db(),$world,$clock,$rules);
    $game->initialize();
    $game->execute(new Command($world,'open','economy.open',['account'=>'a','resource'=>$resource]),$rules->commands);
    $receipt=$game->execute(new Command($world,'upgrade','economy.upgrade',['account'=>'a','resource'=>$resource]),$rules->commands);
    $clock->advanceTo(100+$duration+60);
    // Reconstruct handlers exclusively from the same data artifact.
    $rules=GamePackLoader::load($bytes)->rules();
    $game=new PdoScheduler(db(),$world,$clock,$rules);
    check($game->processDue($rules->events)===$receipt->scheduledIds,$packId.' completes upgrade after reload');
    $balance=$rules->commands->balanceAt($game->state(),'a',$resource,$clock->now());
    $expected=$initial-$cost+intdiv($baseRate*$duration,60)+$upgradedRate;
    check($balance->stock===$expected && $balance->amount===$upgradedRate,$packId.' applies distinct data-driven rules');
    check($game->verifyReplay($rules->commands,$rules->events)->entries===3,$packId.' replays from original content artifact');
    $altered=GamePackLoader::load($bytes."\n")->rules();
    $wrong=new PdoScheduler(db(),$world,$clock,$altered);
    rejects(fn()=> $wrong->state(),DomainException::class,$packId.' rejects changed artifact');
}
