<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PackDevelopment,WorldModuleRegistry,PdoOnboarding,PdoSessions,PdoScheduler,EconomyHttpApi,FrozenClock,WorldWorker};

(static function():void {
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/sdk-observatory/versions/1.0.0/manifest.json'));
    $world='f01-observatory';
    $entry=PackDevelopment::inspect($pack,['survey-v1']);
    unset($entry['pack'],$entry['version']);$entry['world']=$world;
    $runtime=WorldModuleRegistry::load(json_encode(['module_api'=>'1','worlds'=>[$entry]],JSON_THROW_ON_ERROR),[$world=>$pack]);
    $rules=$runtime->rule($world);$pdo=db();$clock=new FrozenClock(19200);
    $onboard=new PdoOnboarding($pdo,$clock);
    $a=$onboard->enroll($world,$pack,'f01-survey-alice','f01 survey password alice',null,$rules)['actor'];
    $b=$onboard->enroll($world,$pack,'f01-survey-bobby','f01 survey password bobby',null,$rules)['actor'];
    $sessions=new PdoSessions($pdo,$clock);
    $ta=$sessions->login('f01-survey-alice','f01 survey password alice');
    $tb=$sessions->login('f01-survey-bobby','f01 survey password bobby');
    check(is_string($ta)&&is_string($tb),'F01 independent pack provisions two actual actors');
    $api=new EconomyHttpApi($pdo,$clock,$runtime->rules);
    $send=static fn(string $token,string $id,string $account):array=>$api->handle('POST',
        '/v1/worlds/'.$world.'/economy/commands','Bearer '.$token,
        json_encode(['id'=>$id,'type'=>'pack.survey.start','payload'=>['account'=>$account]],JSON_THROW_ON_ERROR));
    check($send($tb,'forge',$a)[0]===409,'F01 independent module denies foreign accounts');
    $first=$send($ta,'survey-1',$a);$retry=$send($ta,'survey-1',$a);
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    check($first[0]===200 && $retry[0]===200
        && json_encode($first[1],JSON_THROW_ON_ERROR)===json_encode($retry[1],JSON_THROW_ON_ERROR)
        && $scheduler->pendingCount()===1,'F01 independent module has durable receipts and exactly one scheduled event');
    check($send($ta,'survey-2',$a)[0]===409 && $scheduler->pendingCount()===1,'F01 pending survey excludes a second command');
    $worker=new WorldWorker($pdo,$clock);
    check($worker->run($world,$pack,10,1000,$rules)===[],'F01 worker respects scheduled deadline');
    $clock->advanceTo(19210);
    check(count($worker->run($world,$pack,10,1000,$rules))===1 && $worker->run($world,$pack,10,1000,$rules)===[],
        'F01 worker completes independent module exactly once');
    $state=$scheduler->state();
    check(($state['pack:survey:'.$a.':completed']??null)===1 && !isset($state['pack:survey:'.$b.':completed']),
        'F01 committed result is isolated to the originating account');
    check($scheduler->verifyReplay($rules->commands,$rules->events)->entries===4,'F01 independent module replays two provisions, one command and one event');
    $wrong=new PdoScheduler($pdo,$world,$clock,$pack->rules());
    rejects(fn()=> $wrong->state(),DomainException::class,'F01 changed rules cannot reopen the historical world');
})();
