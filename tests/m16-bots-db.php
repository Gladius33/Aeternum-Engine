<?php
declare(strict_types=1);
use Aeternum\Core\{ActorReceipt,ApprovedBotStrategy,BotPlan,EconomyHttpApi,FrozenClock,
    GamePackLoader,PdoIdentity,PdoScheduler,PdoSessions,PlayerProvisioner,
    ResourceUpgradeBot,TrustedBotRuntime,WorldWorker};

// An operator-controlled bot has NO privileged SQL mutation path. It uses
// a normal authenticated account, the same HTTP limits and journal receipts.
(static function():void {
    $world='m16-bot-lab';
    $pack=GamePackLoader::load(file_get_contents(
        __DIR__.'/../games/reference-guild/manifest.json'));
    $clock=new FrozenClock(21000);
    $pdo=db();
    $ids=new PdoIdentity($pdo);
    $actor=$ids->register('m16-bot-login','m16 disposable bot password exactly');
    $human=$ids->register('m16-human-login','m16 disposable human password exactly');
    $provision=new PlayerProvisioner($pdo,$clock);
    $provision->provision($world,$actor,$pack);
    $provision->provision($world,$human,$pack);
    $sessions=new PdoSessions($pdo,$clock);
    $botToken=$sessions->login('m16-bot-login','m16 disposable bot password exactly');
    $humanToken=$sessions->login('m16-human-login','m16 disposable human password exactly');
    check(is_string($botToken) && is_string($humanToken),
        'M16 bot and human have separate genuine authenticated actor sessions');
    $rules=$pack->rules();
    $api=new EconomyHttpApi($pdo,$clock,[$world=>$rules]);
    $strategy=new ResourceUpgradeBot();
    $policyFileDigest=hash_file('sha256',__FILE__);
    $approvedSources=[$strategy->codeDigest(),$policyFileDigest];
    $runner=new TrustedBotRuntime($api,$clock,$approvedSources);
    check((new TrustedBotRuntime($api,$clock,[]))->tick(
        $world,$actor,'silver','Bearer '.$botToken,$strategy,false
    )===['status'=>'paused'],
        'M16 an unconfigured runtime remains paused without an approved strategy');
    rejects(fn()=>(new TrustedBotRuntime($api,$clock,[]))->tick(
        $world,$actor,'silver','Bearer '.$botToken,$strategy,true
    ),DomainException::class,'M16 activation requires explicitly approved strategy digest');
    rejects(fn()=>new TrustedBotRuntime($api,$clock,['not-a-sha256']),
        InvalidArgumentException::class,'M16 reject malformed trusted strategy registry');
    $spoofed=new class implements ApprovedBotStrategy {
        public function codeDigest():string {return str_repeat('a',64);}
        public function choose(array $overview,string $account,string $resource,int $logicalTick): ?BotPlan {
            return new BotPlan('economy.settle',['account'=>$account,'resource'=>$resource]);
        }
    };
    rejects(fn()=>(new TrustedBotRuntime($api,$clock,[str_repeat('a',64)]))
        ->tick($world,$actor,'silver','Bearer '.$botToken,$spoofed,true),
        DomainException::class,'M16 a strategy cannot spoof the reviewed digest of its actual PHP source');
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    $before=$scheduler->state();
    check($runner->tick($world,$actor,'silver','Bearer '.$botToken,$strategy,false)
        ===['status'=>'paused'] && $scheduler->state()===$before,
        'M16 operator pause is fail-closed and creates no journal mutation');
    $first=$runner->tick($world,$actor,'silver','Bearer '.$botToken,$strategy,true);
    check($first['status']==='submitted' && $first['code']===200
        && $scheduler->pendingCount()===1,
        'M16 bot schedules a real upgrade using the human actor HTTP contract');
    $repeat=$runner->tick($world,$actor,'silver','Bearer '.$botToken,$strategy,true);
    check($repeat===$first && $scheduler->pendingCount()===1,
        'M16 repeated tick has a stable command id and does not enqueue duplicate events');
    $restarted=new TrustedBotRuntime($api,$clock,$approvedSources);
    $changedPolicy=new class implements ApprovedBotStrategy {
        public function codeDigest(): string {return hash_file('sha256',__FILE__);}
        public function choose(array $overview,string $account,string $resource,int $logicalTick): ?BotPlan {
            throw new \LogicException('A committed tick must never recalculate its strategy');
        }
    };
    check($restarted->tick($world,$actor,'silver','Bearer '.$botToken,
        $changedPolicy,true)===$first && $scheduler->pendingCount()===1,
        'M16 restart and reviewed policy change use the committed first-tick receipt');
    $receiptPath='/v1/worlds/'.$world.'/economy/bot-receipts/'.$actor.'/silver/'.$first['commandId'];
    [$receiptCode,$receiptData]=$api->handle('GET',$receiptPath,'Bearer '.$botToken);
    check($receiptCode===200 && $receiptData['data'] instanceof ActorReceipt
        && $receiptData['data']->requestId===$first['commandId']
        && $receiptData['data']->effectiveAt===21000
        && $receiptData['data']->scheduledIds!==[],
        'M16 bot receipt is durable and contains only its own actor receipt');
    [$denied,]=$api->handle('GET',$receiptPath,'Bearer '.$humanToken);
    check($denied===409,'M16 foreign actors cannot inspect bot receipts');
    $foreign=$runner->tick($world,$actor,'silver','Bearer '.$humanToken,$strategy,true);
    check($foreign['status']==='blocked' && $foreign['code']===409,
        'M16 a different human token cannot operate the bot-owned resource account');
    rejects(fn()=> $runner->tick($world,$actor,'silver','Bearer invalid',
        $strategy,true),DomainException::class,
        'M16 invalid session credentials fail closed before player action');
    $clock->advanceTo(21003);
    $worker=new WorldWorker($pdo,$clock);
    check(count($worker->run($world,$pack,10,1000,$rules))===1,
        'M16 bot upgrade is completed only by normal due-time world worker');
    $clock->advanceTo(21060);
    $next=$runner->tick($world,$actor,'silver','Bearer '.$botToken,$strategy,true);
    check($next['status']==='submitted' && $next['commandId']!==$first['commandId'],
        'M16 next logical tick chooses a new deterministic, bounded human-allowed action');
    check(($scheduler->state()['econ:'.$actor.':silver:level']??null)===1,
        'M16 completed bot upgrade changes only its own canonical resource state');
    check($scheduler->verifyReplay($rules->commands,$rules->events)->entries>=3,
        'M16 autonomous strategy actions preserve deterministic canonical replay');
})();
