<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PackDevelopment,WorldModuleRegistry,PlayerDiscoveryApi,EconomyHttpApi,PdoIdentity,PdoSessions,PlayerProvisioner,PdoScheduler,PdoMemberships,FrozenClock,WorldWorker,PdoEconomyProjection};
(static function():void {
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/versions/0.5.0/manifest.json'));
    $world='f04-guild-db';$pdo=db();$clock=new FrozenClock(60000);
    $entry=PackDevelopment::inspect($pack,['campaign-v1','guild-challenge-v1','bilateral-truce-v1']);
    unset($entry['pack'],$entry['version']);
    $runtime=WorldModuleRegistry::load(json_encode(['module_api'=>'1','worlds'=>[['world'=>$world]+$entry]],JSON_THROW_ON_ERROR),[$world=>$pack]);
    $rules=$runtime->rule($world);
    $provisioner=new PlayerProvisioner($pdo,$clock);$ids=new PdoIdentity($pdo);$sessions=new PdoSessions($pdo,$clock);
    $accounts=[];$tokens=[];
    for ($i=0;$i<3;$i++) {
        $login='f04-guild-'.$i;$password='isolated f04 fixture password '.$i;
        $accounts[]=$ids->register($login,$password);
        $provisioner->provision($world,$accounts[$i],$pack,$rules);
        $tokens[]=$sessions->login($login,$password);
    }
    [$a,$b,$c]=$accounts;
    $api=new EconomyHttpApi($pdo,$clock,$runtime->rules);
    $root='/v1/worlds/'.$world.'/economy/';
    $send=static fn(int $i,string $id,string $type,array $payload):array=>$api->handle('POST',$root.'commands','Bearer '.$tokens[$i],
        json_encode(['id'=>$id,'type'=>$type,'payload'=>$payload],JSON_THROW_ON_ERROR));
    $view=static fn(int $i,string $account):array=>$api->handle('GET',$root.'pack-view/campaign/'.$account,'Bearer '.$tokens[$i]);
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    $worker=new WorldWorker($pdo,$clock);
    $advance=static function(int $seconds,int $count=1)use($clock,$worker,$world,$pack,$rules):void {
        $clock->advanceTo($clock->now()+$seconds);
        check(count($worker->run($world,$pack,20,2000,$rules))===$count,'F04 scheduled completion commits once');
    };
    $discovery=(new PlayerDiscoveryApi($pdo,$clock,$runtime->packs,$runtime->rules))->handle('GET','Bearer '.$tokens[0]);
    check(in_array('pack.guild.challenge',$discovery[1]['data']['worlds'][0]['packActions'],true)
        &&$discovery[1]['data']['worlds'][0]['packViews']===['campaign'],'F04 exposes reviewed capability IDs, not arbitrary manifest declarations');
    check($view(1,$a)[0]===404 && $view(0,$a)[1]['data']['victory']['achieved']===false,'F04 private victory snapshot isolated');
    check($send(0,'research-early','pack.campaign.research',['account'=>$a])[0]===409
        &&$send(1,'forge','pack.campaign.research',['account'=>$a])[0]===409,'F04 invalid or foreign jobs rejected');
    $build=static function(int $i,string $account,string $type,int $level,int $seconds)use($send,$advance):void {
        $id='f04-'.$i.'-'.$type;
        if ($level===1) check($send($i,'place-'.$id,'building.place',['building'=>$id,'account'=>$account,'territory'=>$account,'entity'=>$account,'type'=>$type])[0]===200,'F04 place actual foundation');
        check($send($i,'start-'.$id.'-'.$level,'building.start',['building'=>$id,'account'=>$account,'type'=>$type])[0]===200,'F04 start paid construction');
        $advance($seconds);
    };
    $build(0,$a,'watchtower',1,1);$build(1,$b,'watchtower',1,1);
    check($send(0,'novice','pack.guild.challenge',['account'=>$a,'target'=>$c])[0]===409,'F04 novice shield authoritative');
    check($send(0,'challenge','pack.guild.challenge',['account'=>$a,'target'=>$b])[0]===200
        &&$send(0,'cooldown','pack.guild.challenge',['account'=>$a,'target'=>$b])[0]===409,'F04 reviewed challenge uses world-locked cooldown');
    check($send(1,'truce','pack.diplomacy.offer',['account'=>$b,'target'=>$a,'duration'=>60])[0]===200
        &&$send(0,'accept','pack.diplomacy.accept',['account'=>$a,'target'=>$b])[0]===200,'F04 mutually approved truce committed');
    $advance(31,0);
    check($send(0,'blocked','pack.guild.challenge',['account'=>$a,'target'=>$b])[0]===409,'F04 truce blocks otherwise eligible challenge');
    $advance(29,0);
    check($send(0,'after-truce','pack.guild.challenge',['account'=>$a,'target'=>$b])[0]===200,'F04 expiry permits new immediate challenge');
    $build(0,$a,'market_hall',1,2);$build(0,$a,'market_hall',2,4);
    $build(0,$a,'caravan_hub',1,3);
    // Fund the last stage through normal production, not a privileged grant.
    $advance(120,0);$build(0,$a,'caravan_hub',2,5);
    foreach (['research'=>4,'expedition'=>6] as $job=>$seconds) {
        for ($i=1;$i<=2;$i++) {
            $id=$job.'-'.$i;
            $receipt=$send(0,$id,'pack.campaign.'.$job,['account'=>$a]);
            check($receipt[0]===200 && json_encode($receipt,JSON_THROW_ON_ERROR)===json_encode($send(0,$id,'pack.campaign.'.$job,['account'=>$a]),JSON_THROW_ON_ERROR),'F04 exact job receipt idempotent');
            check($view(0,$a)[1]['data']['pending']===$job,'F04 pending is not a completed objective');
            check($send(0,$id.'-extra','pack.campaign.'.$job,['account'=>$a])[0]===409,'F04 duplicate pending job denied');
            $advance($seconds);
            // Keep each logical minute within the real pack quota.
            $advance(60,0);
        }
    }
    for ($i=0;$i<3;$i++) {
        $offer='f04-trade-'.$i;
        check($send(1,'sell-'.$i,'market.create',['offer'=>$offer,'seller'=>$b,'giveResource'=>'spice','giveUnits'=>1,'wantResource'=>'silver','wantUnits'=>1])[0]===200,'F04 independent merchant supplies actual escrow');
        $buy=$send(0,'buy-'.$i,'market.accept',['offer'=>$offer,'seller'=>$b,'buyer'=>$a]);
        check($buy[0]===200&&json_encode($buy,JSON_THROW_ON_ERROR)===json_encode($send(0,'buy-'.$i,'market.accept',['offer'=>$offer,'seller'=>$b,'buyer'=>$a]),JSON_THROW_ON_ERROR),'F04 trade retry has one authoritative effect');
        check($view(0,$a)[1]['data']['victory']['achieved']===($i===2),'F04 victory requires all three completed trades');
    }
    check(!$view(1,$b)[1]['data']['victory']['achieved'],'F04 counterparty does not inherit victory');
    $sessions->revoke($tokens[0]);
    check($view(0,$a)[0]===401,'F04 revoked session cannot read campaign');
    $new=$sessions->login('f04-guild-0','isolated f04 fixture password 0');
    check($api->handle('GET',$root.'pack-view/campaign/'.$a,'Bearer '.$new)[1]['data']['victory']['achieved'],'F04 victory survives reconnect');
    check($scheduler->verifyReplay($rules->commands,$rules->events)->entries>30&&$scheduler->pendingCount()===0,'F04 exact canonical replay and no stranded work');
    check($scheduler->auditScores()>=2,'F04 score index agrees after new campaign events');
    (new PdoEconomyProjection($pdo,$world))->assertConsistent($scheduler->state());
    $wrong=new PdoScheduler($pdo,$world,$clock,$pack->rules());
    rejects(fn()=> $wrong->state(),DomainException::class,'F04 module-less rules cannot reopen pinned campaign');
})();
