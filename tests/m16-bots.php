<?php
declare(strict_types=1);
use Aeternum\Core\{BotPlan,ResourceUpgradeBot};

$bot=new ResourceUpgradeBot();
check((bool)preg_match('/^[a-f0-9]{64}$/D',$bot->codeDigest()),
    'M16 bot policy exposes a stable hash of the reviewed PHP implementation');
$upgrade=$bot->choose(['stock'=>180,'next'=>['cost'=>25],'pending'=>null],
    'bot-account','silver',100);
check($upgrade instanceof BotPlan && $upgrade->type==='economy.upgrade'
    && $upgrade->payload===['account'=>'bot-account','resource'=>'silver'],
    'M16 strategy selects a permitted own-account upgrade from human-visible data');
$settle=$bot->choose(['stock'=>0,'next'=>['cost'=>25],'pending'=>null],
    'bot-account','silver',101);
check($settle instanceof BotPlan && $settle->type==='economy.settle',
    'M16 strategy does not buy an unaffordable upgrade');
$pending=$bot->choose(['stock'=>180,'next'=>['cost'=>25],
    'pending'=>['level'=>1]],'bot-account','silver',101);
check($pending instanceof BotPlan && $pending->type==='economy.settle',
    'M16 strategy does not enqueue a duplicate pending upgrade');
rejects(fn()=>new BotPlan('economy.transfer',[
    'account'=>'bot-account','resource'=>'silver']),InvalidArgumentException::class,
    'M16 bot cannot request transfers or player-targeting privileges');
rejects(fn()=>new BotPlan('economy.upgrade',[
    'account'=>'other','resource'=>'silver','units'=>100]),InvalidArgumentException::class,
    'M16 bot plan refuses extra unaudited fields');
rejects(fn()=> $bot->choose(['stock'=>'180','next'=>null,'pending'=>null],
    'bot-account','silver',101),DomainException::class,
    'M16 bot fails closed on malformed authoritative overview');
