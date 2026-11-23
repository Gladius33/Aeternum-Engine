<?php
declare(strict_types=1);
use Aeternum\Core\{Command,CommandResult,EconomyHttpApi,EngagementActorPackCommand,
    FrozenClock,GamePackLoader,PdoIdentity,PdoScheduler,PdoSessions,
    PlayerProvisioner,TrustedWorldRules,WorldWorker};

// These tests exercise policy at the real locked actor command boundary,
// never via a client-only button or an untrusted payload-supplied power score.
(static function():void {
    $pack=GamePackLoader::load(file_get_contents(
        __DIR__.'/../games/reference-guild/versions/0.3.0/manifest.json'));
    $approved=new class implements EngagementActorPackCommand {
        public function supportedTypes(): array {return ['pack.guild.challenge'];}
        public function codeDigest(): string {return str_repeat('d',64);}
        public function ownedAccountFields(string $type): array {return ['account'];}
        public function targetAccountField(string $type): string {return 'target';}
        public function apply(Command $command,array $state,int $at):CommandResult {
            $key='guild:challenge:'.$command->payload['account'].':'.$command->payload['target'];
            $old=$state[$key]??0;
            if (!is_int($old) || $old>100) throw new DomainException('Challenge counter exhausted');
            $state[$key]=$old+1;
            return new CommandResult($state);
        }
    };
    $world='m15-engagement-world';
    $rules=TrustedWorldRules::bind([$world=>$pack],[$world=>[$approved]])[$world];
    $pdo=db();
    $clock=new FrozenClock(17000);
    $identity=new PdoIdentity($pdo);
    $alice=$identity->register('m15-engage-alice','m15 laboratory engagement alice password');
    $bob=$identity->register('m15-engage-bob','m15 laboratory engagement bobby password');
    $provisioner=new PlayerProvisioner($pdo,$clock);
    $provisioner->provision($world,$alice,$pack,$rules);
    $provisioner->provision($world,$bob,$pack,$rules);
    $sessions=new PdoSessions($pdo,$clock);
    $tokenA=$sessions->login('m15-engage-alice','m15 laboratory engagement alice password');
    $tokenB=$sessions->login('m15-engage-bob','m15 laboratory engagement bobby password');
    check(is_string($tokenA)&&is_string($tokenB),
        'M15 protected multiplayer actors authenticate against MariaDB');
    $scores=new \Aeternum\Core\ProgressionHttpApi($pdo,$clock,[$world=>$pack],[$world=>$rules]);
    check($scores->handle('GET','/v1/worlds/'.$world.'/progression/'.$alice,
        'Bearer '.$tokenA)[0]===200,
        'M15 private progression reads use the correct operator-approved world rules');
    $http=new EconomyHttpApi($pdo,$clock,[$world=>$rules]);
    $endpoint='/v1/worlds/'.$world.'/economy/commands';
    $send=static function(string $token,string $id,string $type,array $p)
        use($http,$endpoint):int {
        return $http->handle('POST',$endpoint,'Bearer '.$token,
            json_encode(['id'=>$id,'type'=>$type,'payload'=>$p],JSON_THROW_ON_ERROR))[0];
    };
    $challenge=static fn(string $id):int=>$send($tokenA,$id,'pack.guild.challenge',
        ['account'=>$alice,'target'=>$bob]);
    check($challenge('m15-novice-denied')===409,
        'M15 server denies targeting novice before handler mutation');
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    check(!isset($scheduler->state()['guild:challenge:'.$alice.':'.$bob]),
        'M15 denied challenge never enters canonical state');
    $make=static function(string $token,string $owner,string $id,string $type)
        use($send):void {
        check($send($token,'place-'.$id,'building.place',
            ['building'=>$id,'entity'=>$owner,'territory'=>$owner,
                'account'=>$owner,'type'=>$type])===200,
            'M15 authorized actor places defensive/progression building');
        check($send($token,'start-'.$id,'building.start',
            ['building'=>$id,'account'=>$owner,'type'=>$type])===200,
            'M15 construction starts under ordinary actor authorization');
    };
    $worker=new WorldWorker($pdo,$clock);
    $make($tokenB,$bob,'m15-bob-tower','watchtower');
    $clock->advanceTo(17001);
    check(count($worker->run($world,$pack,10,1000,$rules))===1,
        'M15 target earns defense power only after worker completion');
    check($challenge('m15-allowed-weak')===200,
        'M15 weaker player may challenge stronger established player');
    $make($tokenA,$alice,'m15-alice-tower','watchtower');
    $clock->advanceTo(17002);
    check(count($worker->run($world,$pack,10,1000,$rules))===1,
        'M15 attacker earns legitimate defensive power');
    $make($tokenA,$alice,'m15-alice-hall','market_hall');
    $clock->advanceTo(17004);
    check(count($worker->run($world,$pack,10,1000,$rules))===1,
        'M15 attacker advances development with actual completed hall');
    check($challenge('m15-allowed-ratio')===200,
        'M15 actor slightly stronger than target remains within configured ratio');
    check($send($tokenA,'m15-alice-upgrade','economy.upgrade',
        ['account'=>$alice,'resource'=>'silver'])===200,
        'M15 same actor schedules a real resource upgrade');
    $clock->advanceTo(17007);
    check(count($worker->run($world,$pack,10,1000,$rules))===1,
        'M15 resource upgrade becomes effective only when due');
    check($challenge('m15-strong-denied')===409,
        'M15 server blocks stronger actor targeting a weaker player after upgrade');
    check($send($tokenB,'m15-bob-target-self','pack.guild.challenge',
        ['account'=>$bob,'target'=>$bob])===409,
        'M15 policy refuses targeting a player's own account');
    $state=$scheduler->state();
    check(($state['guild:challenge:'.$alice.':'.$bob]??null)===2,
        'M15 refused engagement does not change authorized challenge count');
    check($scheduler->verifyReplay($rules->commands,$rules->events)->entries>5,
        'M15 protected multiplayer history replays deterministically');
})();
