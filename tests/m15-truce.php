<?php
declare(strict_types=1);
use Aeternum\Core\{BilateralTruceCommands,Command,EngagementGuard,GamePackLoader};

$policyBytes=file_get_contents(__DIR__.'/../games/reference-guild/versions/0.4.0/manifest.json');
$policyPack=GamePackLoader::load($policyBytes);
$olderPack=GamePackLoader::load(file_get_contents(
    __DIR__.'/../games/reference-guild/versions/0.3.0/manifest.json'));
check($policyPack->engagement!==null
    && $policyPack->engagement->cooldownSeconds===30
    && $policyPack->engagement->diplomacyEnabled
    && $olderPack->engagement->cooldownSeconds===0
    && !$olderPack->engagement->diplomacyEnabled
    && $olderPack->rules()->key!==$policyPack->rules()->key,
    'M15 new optional cooldown and diplomacy policy preserves earlier pinned pack behavior');
$guard=new EngagementGuard($policyPack->progression,$policyPack->engagement);
$state=['build:max:player_a:watchtower'=>1,'build:max:player_b:watchtower'=>1];
$guard->assertEligible($state,'player_a','player_b',2000);
$stamped=$guard->record($state,'player_a',2000);
check(($stamped['engagement:last:player_a']??null)===2000 && $state!==$stamped,
    'M15 successful engagement records cooldown in replay-canonical state');
rejects(fn()=> $guard->assertEligible($stamped,'player_a','player_b',2029),
    DomainException::class,'M15 new engagement rejected throughout configured cooldown');
$guard->assertEligible($stamped,'player_a','player_b',2030);
check(true,'M15 cooldown expires at exact logical tick, independent of wall-clock scheduling');
$truceHandler=new BilateralTruceCommands();
$rules=$policyPack->rules([$truceHandler]);
$offer=new Command('guild','mutual-offer','pack.diplomacy.offer',[
    'account'=>'player_b','target'=>'player_a','duration'=>120]);
$offered=$rules->commands->apply($offer,$stamped,2030)->state;
check(($offered['diplomacy:proposal:player_b:player_a:expires']??null)===5630,
    'M15 truce proposal is not yet an active unilateral ceasefire');
$guard->assertEligible($offered,'player_a','player_b',2030);
$accept=new Command('guild','mutual-accept','pack.diplomacy.accept',[
    'account'=>'player_a','target'=>'player_b']);
$mutual=$rules->commands->apply($accept,$offered,2031)->state;
check(($mutual['diplomacy:truce:player_a:player_b:until']??null)===2151,
    'M15 target consent makes the ceasefire effective for both sides');
rejects(fn()=> $guard->assertEligible($mutual,'player_a','player_b',2150),
    DomainException::class,'M15 accepted bilateral truce forbids engagement before expiry');
rejects(fn()=> $guard->assertEligible($mutual,'player_b','player_a',2150),
    DomainException::class,'M15 truce is symmetrical across both account orders');
$guard->assertEligible($mutual,'player_a','player_b',2151);
check(true,'M15 mutual truce expires without asynchronous jobs or client clocks');
rejects(fn()=> $rules->commands->apply($accept,$mutual,2151),
    DomainException::class,'M15 another acceptance cannot silently reuse a consumed proposal');
rejects(fn()=> $rules->commands->apply(new Command('guild','bad-offer',
    'pack.diplomacy.offer',['account'=>'player_b','target'=>'player_a','duration'=>59]),$state,2200),
    DomainException::class,'M15 rejects truce offers shorter than approved range');
rejects(fn()=> $rules->commands->apply(new Command('guild','self',
    'pack.diplomacy.offer',['account'=>'player_a','target'=>'player_a','duration'=>100]),$state,2200),
    DomainException::class,'M15 bilateral truce rejects same-account targeting');
$bad=$mutual;
$bad['diplomacy:truce:player_a:player_b:until']='9999';
rejects(fn()=> $guard->assertEligible($bad,'player_a','player_b',2300),
    DomainException::class,'M15 malformed persisted truce never weakens an existing policy');
$bad=$stamped;
$bad['engagement:last:player_a']=99999;
rejects(fn()=> $guard->assertEligible($bad,'player_a','player_b',2300),
    DomainException::class,'M15 invalid future cooldown timestamp fails closed');
$badManifest=json_decode($policyBytes,false,32,JSON_THROW_ON_ERROR);
$badManifest->engagement->cooldown_seconds='30';
rejects(fn()=> GamePackLoader::load(json_encode($badManifest,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 rejects numeric string cooldown');
$badManifest=json_decode($policyBytes,false,32,JSON_THROW_ON_ERROR);
$badManifest->engagement->diplomacy_enabled='true';
rejects(fn()=> GamePackLoader::load(json_encode($badManifest,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 requires exact boolean diplomacy opt-in');
