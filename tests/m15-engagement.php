<?php
declare(strict_types=1);
use Aeternum\Core\{Command,CommandResult,EngagementActorPackCommand,EngagementGuard,EngagementRules,GamePackLoader,ScheduledEvent};

$m15FightBytes=file_get_contents(__DIR__.'/../games/reference-guild/versions/0.3.0/manifest.json');
$m15FightPack=GamePackLoader::load($m15FightBytes);
check($m15FightPack->engagement instanceof EngagementRules
    && $m15FightPack->progression!==null
    && $m15FightPack->rules()->key!==$m15Pack->rules()->key,
    'M15 engagement is a separate opt-in pinned rules version');
$gate=new EngagementGuard($m15FightPack->progression,$m15FightPack->engagement);
$state=[
    'build:max:attacker:market_hall'=>1,
    'build:max:attacker:watchtower'=>1,
    'build:max:defender:watchtower'=>1,
];
$gate->assertEligible($state,'attacker','defender');
check(true,'M15 a moderately stronger actor remains eligible within the configured ratio');
rejects(fn()=> $gate->assertEligible($state,'defender','novice'),
    DomainException::class,'M15 novice below shield is protected');
rejects(fn()=> $gate->assertEligible($state,'attacker','attacker'),
    DomainException::class,'M15 account cannot target itself');
$state['econ:attacker:silver:level']=1;
rejects(fn()=> $gate->assertEligible($state,'attacker','defender'),
    DomainException::class,'M15 player above power ratio cannot target weaker player');
$state['build:max:defender:market_hall']=1;
$gate->assertEligible($state,'attacker','defender');
check(true,'M15 target becomes eligible only after server-side earned development score');

$approved=new class implements EngagementActorPackCommand {
    public function supportedTypes(): array { return ['pack.guild.challenge']; }
    public function codeDigest(): string { return str_repeat('a',64); }
    public function ownedAccountFields(string $type): array { return ['account']; }
    public function targetAccountField(string $type): string { return 'target'; }
    public function apply(Command $command,array $state,int $at): CommandResult {
        $key='guild:challenge:'.$command->payload['account'].':'.$command->payload['target'];
        $state[$key]=($state[$key]??0)+1;
        return new CommandResult($state);
    }
};
$m15Guarded=$m15FightPack->rules([$approved]);
$cmd=new Command('m15-simulation','challenge1','pack.guild.challenge',
    ['account'=>'attacker','target'=>'defender']);
check($m15Guarded->commands->engagementTarget($cmd)==='defender'
    && $m15Guarded->commands->actorPackAccounts($cmd)===['attacker'],
    'M15 approved handler identifies independent authenticated source and target');
$m15Guarded->commands->assertEngagement($cmd,$state,'attacker','defender',100);
$m15SimResult=$m15Guarded->commands->apply($cmd,$state,100)->state;
check(($m15SimResult['guild:challenge:attacker:defender']??null)===1
    && ($m15SimResult['build:max:attacker:watchtower']??null)===1,
    'M15 reviewed engagement checks the canonical power snapshot during execution and replay');
rejects(fn()=> $m15FightPack->rules([new class implements EngagementActorPackCommand {
    public function supportedTypes(): array {return ['pack.guild.challenge'];}
    public function codeDigest(): string {return str_repeat('b',64);}
    public function ownedAccountFields(string $type): array {return ['account'];}
    public function targetAccountField(string $type): string {return 'account';}
    public function apply(Command $c,array $s,int $at):CommandResult{return new CommandResult($s);}
}]),InvalidArgumentException::class,
'M15 target cannot be declared as the attacker-owned account');
$scheduled=new class implements EngagementActorPackCommand {
    public function supportedTypes(): array {return ['pack.guild.challenge'];}
    public function codeDigest(): string {return str_repeat('c',64);}
    public function ownedAccountFields(string $type): array {return ['account'];}
    public function targetAccountField(string $type): string {return 'target';}
    public function apply(Command $command,array $state,int $at): CommandResult {
        return new CommandResult($state,[
            new ScheduledEvent($command->worldId,'deferred-challenge','pack.guild.challenge',$at+10,
                ['account'=>$command->payload['account'],'target'=>$command->payload['target']])
        ]);
    }
};
rejects(fn()=> $m15FightPack->rules([$scheduled])->commands->apply($cmd,$state,100),
    DomainException::class,
    'M15 delayed engagement side effects blocked until due-time re-authorization exists');
$m15Bad=json_decode($m15FightBytes,false,32,JSON_THROW_ON_ERROR);
$m15Bad->engagement->max_attacker_ratio_percent=1001;
rejects(fn()=>GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 excessive ratio is rejected');
$m15Bad=json_decode($m15FightBytes,false,32,JSON_THROW_ON_ERROR);
unset($m15Bad->progression);
rejects(fn()=>GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 engagement cannot be enabled without authoritative scoring');
