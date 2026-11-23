<?php
declare(strict_types=1);
use Aeternum\Core\{ActorPackCommand,Command,CommandResult,EconomyHttpApi,GamePackLoader,PdoScheduler,PdoMemberships,PdoIdentity,PdoSessions,PlayerProvisioner,FrozenClock};

// A third, deliberately non-medieval/non-industrial pack with a reviewed
// gameplay action is admitted over the REAL authenticated HTTP and MariaDB
// paths. It may never borrow another player's account or another world's
// role, even when the handler code is trusted.
$m14Json=json_decode(file_get_contents(__DIR__.'/fixtures/m02-packs/sdk-fixture/manifest.json'),true,32,JSON_THROW_ON_ERROR);
$m14Json['actions'][]=[
    'type'=>'pack.guild.research',
    'payload'=>['account'=>'string','units'=>'integer']
];
$m14Pack=GamePackLoader::load(json_encode($m14Json,JSON_THROW_ON_ERROR));
$m14Module=new class implements ActorPackCommand {
    public function supportedTypes(): array { return ['pack.guild.research']; }
    public function codeDigest(): string { return str_repeat('6',64); }
    public function ownedAccountFields(string $type): array {
        if ($type!=='pack.guild.research') throw new DomainException('Unexpected approved type');
        return ['account'];
    }
    public function apply(Command $command,array $state,int $effectiveAt): CommandResult {
        $key='pack:guild:'.$command->payload['account'];
        $previous=$state[$key]??0;
        $units=$command->payload['units'];
        if (!is_int($previous) || !is_int($units) || $units<1 || $units>10
            || $previous>100-$units) {
            throw new DomainException('Research limit exceeded');
        }
        $state[$key]=$previous+$units;
        return new CommandResult($state);
    }
};
$m14Rules=$m14Pack->rules([$m14Module]);
$m14Clock=new FrozenClock(7000);
$m14World='m14-player-world';
$m14Foreign='m14-foreign-world';
$m14A=(new PdoIdentity(db()))->register('m14-alice','m14 laboratory long password alice');
$m14B=(new PdoIdentity(db()))->register('m14-bob','m14 laboratory long password bobby');
foreach ([$m14World,$m14Foreign] as $name) {
    (new PdoScheduler(db(),$name,$m14Clock,$m14Rules))->initialize();
}
$m14Members=new PdoMemberships(db(),$m14World);
$m14Members->grant($m14A);$m14Members->assign($m14A,$m14A);
$m14Members->grant($m14B);$m14Members->assign($m14B,$m14B);
$m14OtherMembers=new PdoMemberships(db(),$m14Foreign);
$m14OtherMembers->grant($m14B);$m14OtherMembers->assign($m14B,$m14B);
$m14Scheduler=new PdoScheduler(db(),$m14World,$m14Clock,$m14Rules);
foreach ([$m14A,$m14B] as $actor) {
    $m14Scheduler->executeForActor($actor,
        new Command($m14World,'m14-open-'.$actor,'economy.open',['account'=>$actor,'resource'=>'amber']),
        $m14Rules->commands);
}
$m14OtherScheduler=new PdoScheduler(db(),$m14Foreign,$m14Clock,$m14Rules);
$m14OtherScheduler->executeForActor($m14B,
    new Command($m14Foreign,'m14-other-open','economy.open',['account'=>$m14B,'resource'=>'amber']),
    $m14Rules->commands);
$m14Auth=new PdoSessions(db(),$m14Clock);
$m14TokenA=$m14Auth->login('m14-alice','m14 laboratory long password alice');
$m14TokenB=$m14Auth->login('m14-bob','m14 laboratory long password bobby');
check(is_string($m14TokenA) && is_string($m14TokenB),'M14 two independent actors log in');
$m14Api=new EconomyHttpApi(db(),$m14Clock,[$m14World=>$m14Rules,$m14Foreign=>$m14Rules]);
$m14Path='/v1/worlds/'.$m14World.'/economy/commands';
$m14ForeignPath='/v1/worlds/'.$m14Foreign.'/economy/commands';
$m14BearerA='Bearer '.$m14TokenA;
$m14BearerB='Bearer '.$m14TokenB;
$m14Body=static fn(string $id,string $account,int|string $units):string=>json_encode([
    'id'=>$id,'type'=>'pack.guild.research','payload'=>['account'=>$account,'units'=>$units]
],JSON_THROW_ON_ERROR);
[$m14Ok,$m14Receipt]=$m14Api->handle('POST',$m14Path,$m14BearerA,
    $m14Body('m14-research-1',$m14A,3));
check($m14Ok===200 && ($m14Scheduler->state()['pack:guild:'.$m14A]??null)===3,
    'M14 authorized actor commits third-pack command under world lock');
[$m14Again,$m14Duplicate]=$m14Api->handle('POST',$m14Path,$m14BearerA,
    $m14Body('m14-research-1',$m14A,3));
check($m14Again===200 && json_encode($m14Receipt,JSON_THROW_ON_ERROR)===json_encode($m14Duplicate,JSON_THROW_ON_ERROR)
    && $m14Scheduler->state()['pack:guild:'.$m14A]===3,
    'M14 same actor request id is replayed without duplicate mutation');
check($m14Api->handle('POST',$m14Path,$m14BearerA,
    $m14Body('m14-research-1',$m14A,4))[0]===409,
    'M14 conflicting retry cannot spend a previously committed identity');
check($m14Api->handle('POST',$m14Path,$m14BearerA,
    $m14Body('m14-steal',$m14B,2))[0]===409,
    'M14 owned account of another player is forbidden');
check($m14Api->handle('POST',$m14ForeignPath,$m14BearerA,
    $m14Body('m14-cross-world',$m14B,2))[0]===409,
    'M14 same handler cannot confer membership in a foreign world');
check($m14Api->handle('POST',$m14Path,$m14BearerA,
    $m14Body('m14-bad-schema',$m14A,'3'))[0]===400,
    'M14 malformed typed payload is rejected before gameplay code');
check($m14Scheduler->state()['pack:guild:'.$m14A]===3
    && !isset($m14Scheduler->state()['pack:guild:'.$m14B]),
    'M14 rejected cross-actor commands leave both states unchanged');
check($m14Scheduler->verifyReplay($m14Rules->commands,$m14Rules->events)->entries===3,
    'M14 third-pack authenticated transactions replay deterministically');
$m14Legacy=$m14Pack->rules();
check(!$m14Legacy->commands->allowsActorPackCommand('pack.guild.research'),
    'M14 historical unextended rules do not expose new player commands');
$m14Members->revoke($m14A);
check($m14Api->handle('POST',$m14Path,$m14BearerA,
    $m14Body('m14-after-revoke',$m14A,1))[0]===409,
    'M14 membership revocation blocks an otherwise valid session and action');
check($m14Api->handle('POST',$m14Path,$m14BearerB,
    $m14Body('m14-other-research',$m14B,2))[0]===200,
    'M14 independent authorized player remains functional after revocation');
check($m14Scheduler->verifyReplay($m14Rules->commands,$m14Rules->events)->entries===4,
    'M14 multi-actor history replays after rejected and authorized attempts');
