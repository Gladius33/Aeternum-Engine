<?php
declare(strict_types=1);
require __DIR__.'/../tools/f07-dossier.php';
function f07Check(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException($name);
    echo 'PASS F07 '.$name.PHP_EOL;
}
function f07Reject(callable $fn,string $name):void{
    try{$fn();}catch(RuntimeException $e){f07Check(true,$name);return;}
    throw new RuntimeException('Unexpected acceptance: '.$name);
}
// All data below is explicitly synthetic. It never enters a release dossier.
$sha=str_repeat('a',40);$other=str_repeat('b',40);
$load=['runner'=>['source_sha'=>$sha,'checkout_sha'=>$sha],'status'=>'passed',
    'dataset'=>['historical_replay'=>true],'profiles'=>[],
    'soak'=>['reads'=>768,'errors'=>0,'p95_ms'=>100,'p99_ms'=>150],
    'overload'=>['observed_429'=>1,'other_actor_available'=>true,'same_actor_recovered_next_window'=>true],
    'verification'=>['ledger_conserved'=>true,'pending_events'=>0]];
foreach([1,4,16,32] as $n)$load['profiles'][]=['clients'=>$n,'operations'=>11*$n,'errors'=>0,
    'p95_ms'=>100,'p99_ms'=>200,'wall_seconds'=>12,'pending_events'=>0,'worker'=>['max_observed_due_lag_seconds'=>0]];
$recovery=['head'=>$sha,'checkout'=>$sha,'status'=>'passed','faults'=>['abandoned_http_response',
    'clock_regression','membership_revoked_while_waiting','sigkill_due_worker','sql_connection_during_notification',
    'sql_connection_during_trade'],'restart_workers'=>4,'ledger_conserved'=>true,'projection_verified'=>true,
    'pending_events'=>0,'notification_count'=>1,'verification'=>['journal_entries'=>18,'outbox_entries'=>18]];
$distribution=['sourceHead'=>$sha,'checkout'=>$sha,'status'=>'passed','tables'=>42,'fingerprintedRows'=>132,
    'verification'=>['verified'=>true,'worlds'=>[['pendingEvents'=>0]]]];
foreach(['freshInstall','strictPreflight','runtimeDdlDenied','restoredPlayer','restoredModule','testPaymentReplay','testRevocation','privateMetrics'] as $k)$distribution[$k]=true;
$browser=['sourceHead'=>$sha,'checkout'=>$sha,'status'=>'passed','games'=>[]];
foreach(['medieval','industrial','guild'] as $pack){
    $campaign=['pack'=>$pack,'victory'=>true,'reconnected'=>true,'research'=>2,'expeditions'=>2];
    $experience=['pack'=>$pack,'measurements'=>[]];
    foreach(['independentActors','tradeCommitted','sameIdAfterLostReply','allianceAccepted','messageEscaped','blockAndUnblock','operatorModeration','reconnected','keyboard'] as $k)$experience[$k]=true;
    foreach(['desktop','mobile'] as $device)$experience['measurements'][]=['device'=>$device,'overflow'=>false,
        'badBadges'=>0,'nodes'=>1000,'frameP95Ms'=>16.7,'navMs'=>150,'minNavHeight'=>44,'minNavContrast'=>5.2];
    $browser['games'][]=['pack'=>$pack,'campaign'=>$campaign,'experience'=>$experience];
}
$audit=['metadata'=>['vulnerabilities'=>['high'=>0,'critical'=>0]]];
$all=[$sha,$load,$recovery,$distribution,$browser,$audit];
$report=F07Dossier::reports(...$all);
f07Check($report['mixed_operations']===583&&$report['soak_reads']===768&&count($report['games'])===3,'valid synthetic report shape');
$bad=$all;$bad[1]['runner']['checkout_sha']=$other;
f07Reject(fn()=>F07Dossier::reports(...$bad),'wrong checkout cannot qualify');
$bad=$all;$bad[1]['profiles'][3]['p99_ms']=20001;
f07Reject(fn()=>F07Dossier::reports(...$bad),'load threshold enforced');
$bad=$all;$bad[1]['profiles'][3]['operations']=351;
f07Reject(fn()=>F07Dossier::reports(...$bad),'missing operation cannot qualify');
$bad=$all;$bad[1]['soak']['errors']=1;
f07Reject(fn()=>F07Dossier::reports(...$bad),'soak failure blocks dossier');
$bad=$all;array_pop($bad[2]['faults']);
f07Reject(fn()=>F07Dossier::reports(...$bad),'missing fault blocks dossier');
$bad=$all;$bad[3]['restoredPlayer']=false;
f07Reject(fn()=>F07Dossier::reports(...$bad),'failed restored login blocks dossier');
$bad=$all;$bad[4]['games'][2]['campaign']['victory']=false;
f07Reject(fn()=>F07Dossier::reports(...$bad),'missing third victory blocks dossier');
$bad=$all;$bad[4]['games'][0]['experience']['sameIdAfterLostReply']=false;
f07Reject(fn()=>F07Dossier::reports(...$bad),'lost-reply identity required');
$bad=$all;$bad[4]['games'][1]['experience']['measurements'][1]['minNavContrast']=4.4;
f07Reject(fn()=>F07Dossier::reports(...$bad),'accessibility regression blocked');
$bad=$all;$bad[5]['metadata']['vulnerabilities']['high']=1;
f07Reject(fn()=>F07Dossier::reports(...$bad),'known high npm advisory blocks dossier');
$runs=[];foreach(M13ActionsAudit::REQUIRED as $i=>$name)$runs[]=['name'=>$name,'id'=>$i+100,'head_sha'=>$sha,'event'=>'push','status'=>'completed','conclusion'=>'success'];
$ci=M13ActionsAudit::evaluate($runs,$sha);
$draft=F07Dossier::draft($sha,$ci);
f07Check(count($draft['gates'])===9&&count($draft['gates']['G09']['evidence'])===10
    &&array_unique(array_column($draft['gates'],'result'))===['pending'],'all gates have evidence but remain pending');
f07Check(!M13Gate::verify($draft,$sha)['metadata_complete']
    &&!in_array(true,$draft['checks'],true)&&$draft['signoff']['operator']===null,'green CI cannot sign or authorize release');
$foreign=F07Dossier::draft($other,$ci);
f07Check($foreign['gates']['G09']['evidence']===[],'cannot reuse evidence under different candidate SHA');
echo "F07 synthetic dossier contract passed; no operator approval.\n";
