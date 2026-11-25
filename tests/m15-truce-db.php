<?php
declare(strict_types=1);
use Aeternum\Core\{BilateralTruceCommands,Command,CommandResult,
    DiplomacyActorPackCommand,EconomyHttpApi,EngagementActorPackCommand,FrozenClock,
    GamePackLoader,PdoIdentity,PdoScheduler,PdoSessions,PlayerProvisioner,
    TrustedWorldRules,WorldWorker};

// Two real authenticated actors agree to a durable, replayable truce.
// Test the exact same InnoDB and HTTP path as other pack actions, not a UI hint.
(static function():void {
    $pack=GamePackLoader::load(file_get_contents(
        __DIR__.'/../games/reference-guild/versions/0.4.0/manifest.json'));
    $challenge=new class implements EngagementActorPackCommand {
        public function supportedTypes(): array {return ['pack.guild.challenge'];}
        public function codeDigest(): string {return str_repeat('9',64);}
        public function ownedAccountFields(string $type): array {return ['account'];}
        public function targetAccountField(string $type): string {return 'target';}
        public function apply(Command $cmd,array $state,int $at): CommandResult {
            $key='guild:challenge:'.$cmd->payload['account'].':'.$cmd->payload['target'];
            $count=$state[$key]??0;
            if (!is_int($count) || $count<0 || $count>100) {
                throw new DomainException('Invalid challenge history');
            }
            $state[$key]=$count+1;
            return new CommandResult($state);
        }
    };
    $world='m15-diplomacy-world';
    $reviewed=\Aeternum\Core\ReviewedModuleCatalog::module('bilateral-truce-v1',$pack);
    $rules=TrustedWorldRules::bind([$world=>$pack],[
        $world=>array_merge([$challenge],$reviewed['commands'])
    ])[$world];
    $pdo=db();$clock=new FrozenClock(19000);
    $identity=new PdoIdentity($pdo);
    $a=$identity->register('m15-truce-a','m15 truce durable password alice');
    $b=$identity->register('m15-truce-b','m15 truce durable password bobby');
    $p=new PlayerProvisioner($pdo,$clock);
    $p->provision($world,$a,$pack,$rules);
    $p->provision($world,$b,$pack,$rules);
    $sessions=new PdoSessions($pdo,$clock);
    $tokenA=$sessions->login('m15-truce-a','m15 truce durable password alice');
    $tokenB=$sessions->login('m15-truce-b','m15 truce durable password bobby');
    check(is_string($tokenA)&&is_string($tokenB),
        'M15 two independent truce participants have real authenticated sessions');
    $api=new EconomyHttpApi($pdo,$clock,[$world=>$rules]);
    $path='/v1/worlds/'.$world.'/economy/commands';
    $send=static function(string $token,string $id,string $type,array $payload)
        use($api,$path):array {
        return $api->handle('POST',$path,'Bearer '.$token,
            json_encode(['id'=>$id,'type'=>$type,'payload'=>$payload],JSON_THROW_ON_ERROR));
    };
    $tower=static function(string $token,string $account,string $id) use($send):void {
        check($send($token,'place-'.$id,'building.place',
            ['building'=>$id,'account'=>$account,'type'=>'watchtower',
                'territory'=>$account,'entity'=>$account])[0]===200,
            'M15 scoring power uses authorized construction placement');
        check($send($token,'start-'.$id,'building.start',
            ['building'=>$id,'account'=>$account,'type'=>'watchtower'])[0]===200,
            'M15 scoring power uses scheduled construction completion');
    };
    $tower($tokenA,$a,'m15-truce-tower-a');
    $tower($tokenB,$b,'m15-truce-tower-b');
    $clock->advanceTo(19001);
    $worker=new WorldWorker($pdo,$clock);
    check(count($worker->run($world,$pack,10,1000,$rules))===2,
        'M15 two watchtowers complete under the production worker before eligibility');
    $challengePayload=['account'=>$a,'target'=>$b];
    check($send($tokenA,'m15-truce-first','pack.guild.challenge',$challengePayload)[0]===200,
        'M15 equal-power players can use approved engagement before truce');
    check($send($tokenA,'m15-truce-too-early','pack.guild.challenge',$challengePayload)[0]===409,
        'M15 30-second cooldown is authoritative under the world lock');
    // A proposal alone does not grant protection. A different opponent
    // must affirm the ceasefire via their own authenticated account.
    $offer=['account'=>$b,'target'=>$a,'duration'=>60];
    $offered=$send($tokenB,'m15-truce-offer','pack.diplomacy.offer',$offer);
    check($offered[0]===200,
        'M15 independent player may propose a bounded temporary truce');
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    check(!isset($scheduler->state()['diplomacy:truce:'.min($a,$b).':'.max($a,$b).':until']),
        'M15 unilateral proposal cannot activate truce');
    $accepted=$send($tokenA,'m15-truce-accept','pack.diplomacy.accept',
        ['account'=>$a,'target'=>$b]);
    check($accepted[0]===200,
        'M15 mutually authenticated acceptance creates the bilateral truce');
    $foreign=$send($tokenB,'m15-truce-forge','pack.diplomacy.accept',
        ['account'=>$a,'target'=>$b]);
    check($foreign[0]===409,
        'M15 a foreign actor cannot impersonate another account to accept a truce');
    $clock->advanceTo(19031);
    check($send($tokenA,'m15-truce-blocked','pack.guild.challenge',$challengePayload)[0]===409,
        'M15 truce still blocks engagement after the individual cooldown expires');
    $clock->advanceTo(19061);
    check($send($tokenA,'m15-truce-expired','pack.guild.challenge',$challengePayload)[0]===200,
        'M15 eligibility resumes at precise mutually agreed truce expiration');
    check($send($tokenA,'m15-truce-post-expiry-cooldown','pack.guild.challenge',$challengePayload)[0]===409,
        'M15 cooldown is renewed by post-truce successful engagement');
    $state=$scheduler->state();
    check(($state['guild:challenge:'.$a.':'.$b]??null)===2,
        'M15 denied retries and truce cannot create extra canonical challenges');
    check(($state['engagement:last:'.$a]??null)===19061,
        'M15 last accepted engagement is persisted for audit and deterministic replay');
    check($scheduler->verifyReplay($rules->commands,$rules->events)->entries>5,
        'M15 truce, cooldown and protected actions verify by canonical journal replay');
})();
