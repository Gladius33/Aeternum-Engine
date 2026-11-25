<?php
declare(strict_types=1);
use Aeternum\Core\{ActorPackCommand,ApprovedPackEvent,Command,CommandResult,EconomyHttpApi,EventResult,FrozenClock,GamePackLoader,PdoIdentity,PdoScheduler,PdoSessions,ScheduledEvent,TrustedWorldRules,PlayerProvisioner,WorldWorker};

// Isolated real-MariaDB test of opt-in delayed gameplay events. Actor
// admission, durable event scheduling, worker processing and canonical replay
// use the SAME pinned world rule identity. No connection escapes this scope.
(static function():void {
    $fixture=json_decode(file_get_contents(__DIR__.'/fixtures/m02-packs/sdk-fixture/manifest.json'),true,32,JSON_THROW_ON_ERROR);
    $fixture['actions'][]=[
        'type'=>'pack.guild.train',
        'payload'=>['account'=>'string','units'=>'integer']
    ];
    $fixture['event_hooks'][]=[
        'type'=>'pack.guild.complete',
        'payload'=>['account'=>'string','units'=>'integer']
    ];
    $pack=GamePackLoader::load(json_encode($fixture,JSON_THROW_ON_ERROR));
    $commands=new class implements ActorPackCommand {
        public function supportedTypes(): array { return ['pack.guild.train']; }
        public function codeDigest(): string { return str_repeat('4',64); }
        public function ownedAccountFields(string $type): array { return ['account']; }
        public function apply(Command $command,array $state,int $at): CommandResult {
            $account=$command->payload['account'];
            $units=$command->payload['units'];
            if (!is_int($units) || $units<1 || $units>5 || $at>PHP_INT_MAX-10) {
                throw new DomainException('Invalid delayed training');
            }
            $event=new ScheduledEvent($command->worldId,
                'm14-due:'.hash('sha256',$command->id),
                'pack.guild.complete',$at+10,['account'=>$account,'units'=>$units]);
            return new CommandResult($state,[$event]);
        }
    };
    $events=new class implements ApprovedPackEvent {
        public function supportedTypes(): array { return ['pack.guild.complete']; }
        public function codeDigest(): string { return str_repeat('5',64); }
        public function resolve(ScheduledEvent $event,array $state): EventResult {
            $key='pack:guild:'.$event->payload['account'];
            $before=$state[$key]??0;
            $units=$event->payload['units'];
            if (!is_int($before) || !is_int($units)
                || $units<1 || $units>5 || $before>100-$units) {
                throw new DomainException('Invalid event progression');
            }
            $state[$key]=$before+$units;
            return new EventResult($state);
        }
        public function apply(ScheduledEvent $event,array $state): array {
            return $this->resolve($event,$state)->state;
        }
    };
    $world='m14-event-world';
    $runtime=new \Aeternum\Core\WorldRuntime([$world=>$pack],[$world=>[$commands]],[$world=>[$events]]);
    $rules=$runtime->rule($world);
    $clock=new FrozenClock(9200);
    $pdo=db();
    $claim=(new \Aeternum\Core\PdoOnboarding($pdo,$clock))->enroll($world,$runtime->pack($world),'m14-event-player','m14 event player long test password',null,$rules);
    $actor=$claim['actor'];
    check($claim['created']===true,'F01 onboarding uses the exact extended world composition');
    $token=(new PdoSessions($pdo,$clock))->login('m14-event-player','m14 event player long test password');
    check(is_string($token) && strlen($token)===64,
        'M14 delayed third-pack actor can authenticate after approved provisioning');
    $api=new EconomyHttpApi($pdo,$clock,$runtime->rules);
    $path='/v1/worlds/'.$world.'/economy/commands';
    $body=json_encode(['id'=>'m14-delayed-train','type'=>'pack.guild.train',
        'payload'=>['account'=>$actor,'units'=>3]],JSON_THROW_ON_ERROR);
    [$code,$receipt]=$api->handle('POST',$path,'Bearer '.$token,$body);
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    check($code===200 && $scheduler->pendingCount()===1
        && !isset($scheduler->state()['pack:guild:'.$actor]),
        'M14 actor command persists exactly one delayed event without advancing state early');
    [$retryCode,$retryReceipt]=$api->handle('POST',$path,'Bearer '.$token,$body);
    check($retryCode===200
        && json_encode($receipt,JSON_THROW_ON_ERROR)===json_encode($retryReceipt,JSON_THROW_ON_ERROR)
        && $scheduler->pendingCount()===1,
        'M14 durable retry cannot schedule a second delayed action');
    check((new WorldWorker($pdo,$clock))->run($world,$pack,10,1000,$rules)===[],
        'M14 operator worker does not execute an event before its logical deadline');
    $clock->advanceTo(9210);
    $processed=(new WorldWorker($pdo,$clock))->run($world,$pack,10,1000,$rules);
    check(count($processed)===1 && ($scheduler->state()['pack:guild:'.$actor]??null)===3,
        'M14 event executes in approved worker at exact logical time');
    check((new WorldWorker($pdo,$clock))->run($world,$pack,10,1000,$rules)===[],
        'M14 completed event cannot replay as a second live award');
    check($scheduler->verifyReplay($rules->commands,$rules->events)->entries===3,
        'M14 approved delayed command and event match deterministic persisted replay');
    $wrong=new PdoScheduler($pdo,$world,$clock,$pack->rules());
    rejects(fn()=> $wrong->state(),DomainException::class,
        'M14 immutable world pin rejects older handler-less rules during replay');
})();
