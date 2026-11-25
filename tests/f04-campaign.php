<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PackDevelopment,WorldModuleRegistry,Command,ScheduledEvent};
(static function():void {
    foreach (['medieval'=>'0.4.0','industrial'=>'0.4.0','guild'=>'0.5.0'] as $theme=>$version) {
        $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-'.$theme.'/versions/'.$version.'/manifest.json'));
        $world='f04-'.$theme;
        $ids=$theme==='guild'?['campaign-v1','guild-challenge-v1','bilateral-truce-v1']:['campaign-v1'];
        $entry=PackDevelopment::inspect($pack,$ids);unset($entry['pack'],$entry['version']);
        $runtime=WorldModuleRegistry::load(json_encode(['module_api'=>'1','worlds'=>[['world'=>$world]+$entry]],JSON_THROW_ON_ERROR),[$world=>$pack]);
        $rules=$runtime->rule($world);
        check($rules->key!==$pack->rules()->key && $rules->commands->packViewIds()===['campaign'],'F04 reviewed view is opt-in for '.$theme);
        $state=[];$view=$rules->commands->ownPackView('campaign',$state,'alice');
        check(!$view['canResearch']&&!$view['canExpedition'],'F04 facilities required before study or expedition');
        $research=new Command($world,'start-research','pack.campaign.research',['account'=>'alice']);
        rejects(fn()=> $rules->commands->apply($research,$state,100),DomainException::class,'F04 research cannot bypass prerequisites');
        $state['build:max:alice:'.$view['researchBuilding']]=2;
        $state['build:max:alice:'.$view['expeditionBuilding']]=1;
        $start=$rules->commands->apply($research,$state,100);
        check($start->movements===[]&&count($start->events)===1&&$start->events[0]->dueAt===104,'F04 private job schedules exactly one bounded event');
        rejects(fn()=> $rules->commands->apply($research,$start->state,101),DomainException::class,'F04 pending slot blocks duplicate jobs');
        $event=$start->events[0];
        rejects(fn()=> $rules->events->apply(new ScheduledEvent($world,'forged',$event->type,$event->dueAt,$event->payload),$start->state),DomainException::class,'F04 unmatched event cannot award research');
        $done=$rules->events->apply($event,$start->state);
        check($rules->commands->ownPackView('campaign',$done,'alice')['research']===1
            && $rules->commands->ownPackView('campaign',$done,'alice')['canExpedition']
            && $rules->commands->ownPackView('campaign',$done,'bob')['research']===0,'F04 confirmed research unlocks only owner expedition');
        rejects(fn()=> $rules->events->apply($event,$done),DomainException::class,'F04 repeated completion cannot increment again');
        check(!$pack->rules()->commands->allowsActorPackCommand('pack.campaign.research'),'F04 manifest alone cannot authorize research');
    }
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/versions/0.5.0/manifest.json'));
    $m=new \Aeternum\Core\CampaignModuleV1($pack->id,$pack->version);
    $state=['build:max:alice:market_hall'=>2,'build:max:alice:caravan_hub'=>2,'build:max:alice:watchtower'=>1,
        'pack:campaign:alice:research'=>2,'pack:campaign:alice:expedition'=>2];
    foreach (['one'=>'filled','two'=>'filled','three'=>'open','four'=>'cancelled'] as $id=>$status) {
        $state['market:'.$id.':status']=$status;$state['market:'.$id.':buyer']='alice';$state['market:'.$id.':seller']='bob';
    }
    check(!$m->ownView($state,'alice')['victory']['achieved'],'F04 open or cancelled offers do not count toward victory');
    $state['market:three:status']='filled';
    check($m->ownView($state,'alice')['victory']['achieved']&&!$m->ownView($state,'bob')['victory']['achieved'],'F04 victory is authoritative and account-local');
})();
