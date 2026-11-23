<?php
declare(strict_types=1);
use Aeternum\Core\{ApprovedPackEvent,EventResult,GamePackLoader,ScheduledEvent,TrustedWorldRules};

// Separate non-shipping guild pack; approved code is NEVER named by JSON.
$m14EventFixture=json_decode(file_get_contents(__DIR__.'/fixtures/m02-packs/sdk-fixture/manifest.json'),true,32,JSON_THROW_ON_ERROR);
$m14EventFixture['event_hooks'][]=[
    'type'=>'pack.guild.complete',
    'payload'=>['account'=>'string','units'=>'integer']
];
$m14EventJson=json_encode($m14EventFixture,JSON_THROW_ON_ERROR);
$m14EventPack=GamePackLoader::load($m14EventJson);
$m14EventHandler=static fn(string $type,string $sha):ApprovedPackEvent=>
    new class($type,$sha) implements ApprovedPackEvent {
        public function __construct(private string $eventType,private string $digest) {}
        public function supportedTypes(): array { return [$this->eventType]; }
        public function codeDigest(): string { return $this->digest; }
        public function resolve(ScheduledEvent $event,array $state): EventResult {
            if ($event->type!==$this->eventType) throw new DomainException('Unknown event');
            $key='pack:guild:'.$event->payload['account'];
            $units=$event->payload['units'];
            $before=$state[$key]??0;
            if (!is_int($before) || !is_int($units) || $units<1
                || $units>10 || $before>100-$units) {
                throw new DomainException('Invalid delayed reward');
            }
            $state[$key]=$before+$units;
            return new EventResult($state);
        }
        public function apply(ScheduledEvent $event,array $state): array {
            return $this->resolve($event,$state)->state;
        }
    };
$m14ApprovedEvent=$m14EventHandler('pack.guild.complete',str_repeat('e',64));
$m14PlainEvents=$m14EventPack->rules();
$m14CustomEvents=$m14EventPack->rules([],[$m14ApprovedEvent]);
check($m14CustomEvents->key!==$m14PlainEvents->key,
    'M14 trusted event opt-in pins a distinct replay rules identity');
check($m14CustomEvents->key===$m14EventPack->rules([],[$m14ApprovedEvent])->key,
    'M14 stable reviewed event digest preserves pinned identity');
$m14SampleEvent=$m14EventPack->sdk()->event(
    'guild-world','guild-event-01','pack.guild.complete',500,['account'=>'owner','units'=>4]);
rejects(fn()=> $m14PlainEvents->events->apply($m14SampleEvent,[]),
    DomainException::class,'M14 unapproved event cannot execute');
check($m14CustomEvents->events->apply($m14SampleEvent,[])===['pack:guild:owner'=>4],
    'M14 pinned event adapter executes reviewed non-core game mechanic');
check($m14CustomEvents->events->apply($m14SampleEvent,['pack:guild:owner'=>4])
    ===['pack:guild:owner'=>8],'M14 event handler is deterministic for replay');
rejects(fn()=> $m14CustomEvents->events->apply(
    new ScheduledEvent('guild-world','bad','pack.guild.complete',500,
        ['account'=>'owner','units'=>'4']),[]),
    InvalidArgumentException::class,
    'M14 event manifest schema blocks malformed scheduled payload');
rejects(fn()=> $m14CustomEvents->events->apply(
    new ScheduledEvent('guild-world','bad-unknown','pack.guild.unknown',500,
        ['account'=>'owner','units'=>4]),[]),
    DomainException::class,'M14 undeclared event type is denied');
check($m14CustomEvents->key!==$m14EventPack->rules(
    [],[$m14EventHandler('pack.guild.complete',str_repeat('f',64))])->key,
    'M14 different reviewed event code must not silently replace a pinned world');
rejects(fn()=> $m14EventPack->rules([],[$m14ApprovedEvent,$m14ApprovedEvent]),
    InvalidArgumentException::class,'M14 duplicate event owner rejected');
rejects(fn()=> $m14EventPack->rules([],[$m14EventHandler(
    'economy.upgrade.complete',str_repeat('e',64))]),
    InvalidArgumentException::class,'M14 built-in engine events cannot be shadowed');
rejects(fn()=> $m14EventPack->rules([],[$m14EventHandler(
    'pack.guild.undeclared',str_repeat('e',64))]),
    InvalidArgumentException::class,'M14 event implementation requires a declared hook');
rejects(fn()=> $m14EventPack->rules([],[$m14EventHandler(
    'pack.guild.complete','unverified')]),
    InvalidArgumentException::class,'M14 unpinned event handler code rejected');
$worldEvents=TrustedWorldRules::bind(
    ['custom-events'=>$m14EventPack,'plain-events'=>$m14EventPack],
    [],['custom-events'=>[$m14ApprovedEvent]]
);
check($worldEvents['custom-events']->key===$m14CustomEvents->key
    && $worldEvents['plain-events']->key===$m14PlainEvents->key,
    'M14 event handlers are explicitly pinned per world only');
rejects(fn()=> TrustedWorldRules::bind(
    ['plain-events'=>$m14EventPack],[],['unconfigured'=>[$m14ApprovedEvent]]),
    InvalidArgumentException::class,'M14 event handler cannot target an unknown world');
