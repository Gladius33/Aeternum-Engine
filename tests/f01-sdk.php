<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PackDevelopment,ReviewedModuleCatalog,WorldModuleRegistry,Command,ScheduledEvent};

(static function():void {
    $bytes=file_get_contents(__DIR__.'/../games/sdk-observatory/versions/1.0.0/manifest.json');
    $pack=GamePackLoader::load($bytes);
    $info=PackDevelopment::inspect($pack,['survey-v1']);
    $module=ReviewedModuleCatalog::module('survey-v1',$pack);
    $rules=$pack->rules($module['commands'],$module['events']);
    check($info['rules_key']===$rules->key && $info['pack_sha256']===hash('sha256',$bytes)
        && $rules->key!==$pack->rules()->key,'F01 independent SDK pack has exact composed fingerprints');
    $command=$pack->sdk()->command('sdk-world','cmd-one','pack.survey.start',['account'=>'alice']);
    $start=$rules->commands->apply($command,[],100);
    check(count($start->events)===1 && $start->events[0]->dueAt===110
        && $start->movements===[] && !isset($start->state['pack:survey:alice:completed']),
        'F01 SDK sample schedules bounded own-account work');
    rejects(fn()=> $rules->commands->apply($command,$start->state,101),DomainException::class,
        'F01 sample cannot accumulate pending work');
    $forged=new ScheduledEvent('sdk-world','forged','pack.survey.complete',110,['account'=>'alice']);
    rejects(fn()=> $rules->events->apply($forged,$start->state),DomainException::class,'F01 unmatched completion denied');
    // Deliberately hostile effect results from trusted fixture code are
    // blocked by the opt-in contract independently of the module itself.
    $guard=new \Aeternum\Core\SurveyModuleV1();
    rejects(fn()=> \Aeternum\Core\PrivatePackEffectGuard::state($guard,['alice'],[],
        ['pack:survey:bob:completed'=>1],[]),DomainException::class,'F01 private effects reject foreign state');
    rejects(fn()=> \Aeternum\Core\PrivatePackEffectGuard::state($guard,['alice'],['econ:alice:supplies:stock'=>10],
        ['econ:alice:supplies:stock'=>11],[]),DomainException::class,'F01 private effects cannot mint generic resources');
    rejects(fn()=> \Aeternum\Core\PrivatePackEffectGuard::events($guard,$command,100,['alice'],
        $start->events,$pack->eventHooks,[]),DomainException::class,'F01 unapproved delayed event cannot enter queue');
    $foreign=new ScheduledEvent('sdk-world','foreign','pack.survey.complete',110,['account'=>'bob']);
    rejects(fn()=> \Aeternum\Core\PrivatePackEffectGuard::events($guard,$command,100,['alice'],
        [$foreign],$pack->eventHooks,['pack.survey.complete'=>new \Aeternum\Core\SurveyEventV1()]),
        DomainException::class,'F01 delayed effect may not target a foreign account');
    $done=$rules->events->apply($start->events[0],$start->state);
    check($done===['pack:survey:alice:completed'=>1],'F01 survey completion clears its pending record');
    rejects(fn()=> $rules->events->apply($start->events[0],$done),DomainException::class,'F01 completed transition cannot duplicate award');
    rejects(fn()=> $pack->rules()->commands->apply($command,[],100),DomainException::class,'F01 manifest alone grants no executable permission');
    rejects(fn()=> PackDevelopment::inspect($pack,['survey-v1','survey-v1']),InvalidArgumentException::class,'F01 duplicate module denied by SDK');
    $scaffold=PackDevelopment::scaffold('example-pack','1.0.0','Example Pack');
    check(GamePackLoader::load($scaffold)->id==='example-pack','F01 scaffold is valid without manual correction');
    $newVersion=GamePackLoader::load(PackDevelopment::scaffold('sdk-observatory','2.0.0','SDK Observatory 2'));
    $plan=PackDevelopment::upgradePlan($pack,$newVersion,['survey-v1'],[]);
    check($plan['strategy']==='provision-new-world' && !$plan['in_place_allowed'] && !$plan['copy_state_allowed'],
        'F01 changed versions cannot silently overwrite historical state');
    check(PackDevelopment::upgradePlan($pack,$pack,['survey-v1'],['survey-v1'])['in_place_allowed'],
        'F01 unchanged exact key may retain world');
    $sameVersion=GamePackLoader::load(PackDevelopment::scaffold('sdk-observatory','1.0.0','Changed'));
    rejects(fn()=> PackDevelopment::upgradePlan($pack,$sameVersion),DomainException::class,'F01 immutable version bytes enforced');
})();
