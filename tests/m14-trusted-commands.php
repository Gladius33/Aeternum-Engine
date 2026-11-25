<?php
declare(strict_types=1);
use Aeternum\Core\{ApprovedPackCommand,Command,CommandResult,EconomyCommandHandler,GamePackLoader};

/**
 * Third non-shipping test pack: extend the M02 data-only fixture with a
 * declared custom action. No file path or PHP class is supplied by JSON.
 */
$m14Fixture=json_decode(file_get_contents(__DIR__.'/fixtures/m02-packs/sdk-fixture/manifest.json'),true,32,JSON_THROW_ON_ERROR);
$m14Fixture['actions'][]=['type'=>'pack.guild.research','payload'=>['account'=>'string','units'=>'integer']];
$m14Json=json_encode($m14Fixture,JSON_THROW_ON_ERROR);
$m14Pack=GamePackLoader::load($m14Json);
$m14Approved=static fn(string $type,string $digest): ApprovedPackCommand =>
    new class($type,$digest) implements ApprovedPackCommand {
        public function __construct(private string $type,private string $digest) {}
        public function supportedTypes(): array { return [$this->type]; }
        public function codeDigest(): string { return $this->digest; }
        public function apply(Command $command,array $state,int $effectiveAt): CommandResult
        {
            if ($command->type!==$this->type) throw new DomainException('Unknown command');
            $key='pack:guild:'.$command->payload['account'];
            $prior=$state[$key]??0;
            if (!is_int($prior) || $prior<0 || $command->payload['units']<1
                || $command->payload['units']>100 || $prior>10000-$command->payload['units']) {
                throw new DomainException('Invalid research progression');
            }
            $state[$key]=$prior+$command->payload['units'];
            return new CommandResult($state);
        }
    };

$module=$m14Approved('pack.guild.research',str_repeat('a',64));
$historical=$m14Pack->rules();
$active=$m14Pack->rules([$module]);
check($active->commands instanceof EconomyCommandHandler,'M14 plugin preserves shared economy handler');
check($historical->key===$m14Pack->rules()->key,
    'M14 no plugin preserves the historical pinned rules identity');
check($historical->key!==$active->key,
    'M14 approved command changes pinned rules key');
check($active->key===$m14Pack->rules([$module])->key,
    'M14 same manifest and reviewed digest yield stable rules key');
$cmd=$m14Pack->sdk()->command('third-world','cmd-01','pack.guild.research',['account'=>'person1','units'=>3]);
rejects(fn()=> $historical->commands->apply($cmd,[],100),DomainException::class,
    'M14 pack command denied without explicit trusted binding');
$after=$active->commands->apply($cmd,[],100);
check($after->state===['pack:guild:person1'=>3] && $after->events===[] && $after->movements===[],
    'M14 third-pack approved action executes with scalar state');
check($active->commands->apply($cmd,$after->state,101)->state['pack:guild:person1']===6,
    'M14 approved handler remains deterministic for equal state and command');
rejects(fn()=> $active->commands->apply(new Command('third-world','cmd-02','pack.guild.research',
    ['account'=>'person1','units'=>'3']),[],100),InvalidArgumentException::class,
    'M14 typed payload validation precedes handler');
rejects(fn()=> $active->commands->apply(new Command('third-world','cmd-03','pack.guild.research',
    ['account'=>'person1','units'=>101]),[],100),DomainException::class,
    'M14 handler enforces its declared gameplay bounds');
check($active->key!==$m14Pack->rules([$m14Approved('pack.guild.research',str_repeat('b',64))])->key,
    'M14 changed approved implementation digest cannot reuse old rules key');
rejects(fn()=> $m14Pack->rules([$module,$module]),InvalidArgumentException::class,
    'M14 duplicate command owner rejected');
rejects(fn()=> $m14Pack->rules([$m14Approved('economy.transfer',str_repeat('a',64))]),InvalidArgumentException::class,
    'M14 core transactions cannot be shadowed');
rejects(fn()=> $m14Pack->rules([$m14Approved('pack.guild.missing',str_repeat('a',64))]),InvalidArgumentException::class,
    'M14 executable action must be declared in the data-only manifest');
rejects(fn()=> $m14Pack->rules([$m14Approved('pack.guild.research','unversioned')]),InvalidArgumentException::class,
    'M14 unpinned executable handler rejected');
rejects(fn()=> $m14Pack->rules([new stdClass()]),InvalidArgumentException::class,
    'M14 command requires a reviewed handler contract');

// A trusted operator-only module is not automatically accessible to players.
check(!$active->commands->allowsActorPackCommand('pack.guild.research'),
    'M14 trusted internal plugin never auto-enables player admission');
$actorModule=new class implements \Aeternum\Core\ActorPackCommand {
    public function supportedTypes(): array { return ['pack.guild.research']; }
    public function codeDigest(): string { return str_repeat('c',64); }
    public function ownedAccountFields(string $type): array { return ['account']; }
    public function apply(Command $command,array $state,int $at): CommandResult {
        $state['research:'.$command->payload['account']]=$command->payload['units'];
        return new CommandResult($state);
    }
};
$actorRules=$m14Pack->rules([$actorModule]);
check($actorRules->commands->allowsActorPackCommand('pack.guild.research'),
    'M14 separately approved actor-policy opt-in enables the exact action');
check($actorRules->commands->actorPackAccounts($cmd)===['person1'],
    'M14 actor ownership declarations resolve only typed account IDs');
rejects(fn()=> $actorRules->commands->actorPackAccounts(new Command('third-world','bad',
    'pack.guild.research',['account'=>'bad-id$','units'=>2])),InvalidArgumentException::class,
    'M14 invalid account identifier is rejected before database lookup');
$badActor=static fn(array $fields):\Aeternum\Core\ActorPackCommand =>
    new class($fields) implements \Aeternum\Core\ActorPackCommand {
        public function __construct(private array $fields) {}
        public function supportedTypes(): array { return ['pack.guild.research']; }
        public function codeDigest(): string { return str_repeat('d',64); }
        public function ownedAccountFields(string $type): array { return $this->fields; }
        public function apply(Command $command,array $state,int $at): CommandResult {
            return new CommandResult($state);
        }
    };
rejects(fn()=> $m14Pack->rules([$badActor([])]),InvalidArgumentException::class,
    'M14 empty actor ownership policy rejected');
rejects(fn()=> $m14Pack->rules([$badActor(['account','account'])]),InvalidArgumentException::class,
    'M14 duplicate account ownership field rejected');
rejects(fn()=> $m14Pack->rules([$badActor(['units'])]),InvalidArgumentException::class,
    'M14 policy cannot masquerade an integer as an account identifier');
rejects(fn()=> $m14Pack->rules([$badActor(['invented'])]),InvalidArgumentException::class,
    'M14 undeclared ownership field rejected');

// Operator-side per-world composition is explicit and fail closed. A pack
// listed in the registry never gains a module just because another world did.
$worldBindings=\Aeternum\Core\TrustedWorldRules::bind(
    ['guild-world'=>$m14Pack,'plain-world'=>$m14Pack],
    ['guild-world'=>[$actorModule]]
);
check($worldBindings['guild-world']->key===$actorRules->key
    && $worldBindings['plain-world']->key===$historical->key,
    'M14 approved module binds only to the explicitly selected world');
rejects(fn()=> \Aeternum\Core\TrustedWorldRules::bind(
    ['plain-world'=>$m14Pack],['rogue-world'=>[$actorModule]]
),InvalidArgumentException::class,'M14 operator cannot bind a module to an unknown world');
rejects(fn()=> \Aeternum\Core\TrustedWorldRules::bind(
    ['plain-world'=>$m14Pack],['plain-world'=>['untrusted PHP class path']]
),InvalidArgumentException::class,'M14 world binding cannot instantiate a handler by name');

check(\Aeternum\Core\TrustedWorldRules::bind([])===[],
    'M14 unconfigured installation retains empty-world discovery semantics');

// F01: shared composition pins the same manifest and reviewed code, with
// no new digest input in RuleSet::key for legacy or extended worlds.
$runtime=new \Aeternum\Core\WorldRuntime(['guild-world'=>$m14Pack,'plain-world'=>$m14Pack],['guild-world'=>[$actorModule]]);
check($runtime->rule('guild-world')->key===$actorRules->key && $runtime->rule('plain-world')->key===$historical->key,
    'F01 shared composition preserves both exact historical identities');
check($runtime->rule('guild-world')->packDigest===$m14Pack->digest,
    'F01 extended rule retains source manifest provenance');
rejects(fn()=> $runtime->rule('missing-world'),InvalidArgumentException::class,'F01 unknown runtime world fails closed');
$otherFixture=$m14Fixture;$otherFixture['name']='Different manifest with the same ID and version';
$otherPack=GamePackLoader::load(json_encode($otherFixture,JSON_THROW_ON_ERROR));
rejects(fn()=> \Aeternum\Core\WorldRuntime::assertBindings(['guild-world'=>$otherPack],['guild-world'=>$actorRules]),
    InvalidArgumentException::class,'F01 rejects same ID/version with different manifest bytes');
rejects(fn()=> \Aeternum\Core\WorldRuntime::assertBindings(['plain-world'=>$m14Pack],['missing-world'=>$historical]),
    InvalidArgumentException::class,'F01 rejects unrelated world rule binding');
check((new \Aeternum\Core\WorldRuntime([]))->rules===[],'F01 empty runtime remains valid');
