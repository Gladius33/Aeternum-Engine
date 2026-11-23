<?php
declare(strict_types=1);
use Aeternum\Core\OperationsAlerts;
$policy=new OperationsAlerts();
$base=['schemaVersion'=>1,'migrations'=>['verified'=>true],'healthy'=>true,
    'sessions'=>['capped'=>false],'worlds'=>[
      ['world'=>'world-A','initialized'=>true,
       'dueEvents'=>0,'dueEventsCapped'=>false,'oldestDueSeconds'=>null,
       'outboxPending'=>0,'outboxPendingCapped'=>false]
    ]];
check($policy->evaluate($base)===[
    'schemaVersion'=>1,'requiresAttention'=>false,'alerts'=>[]
],'M11 alert policy is quiet for verified healthy systems');
$lag=$base;
$lag['worlds'][0]['oldestDueSeconds']=900;
$lag['worlds'][0]['dueEvents']=1000;
$lag['worlds'][0]['outboxPending']=1000;
$result=$policy->evaluate($lag);
check($result['requiresAttention']===true
    && array_column($result['alerts'],'code')===['event_backlog','event_lag','outbox_backlog'],
    'M11 exact backlog/lag/outbox thresholds are deterministic and world-scoped');
$dirty=$base;
$dirty['migrations']['verified']=false;
check($policy->evaluate($dirty)['alerts'][0]['code']==='schema_not_verified',
    'M11 schema checksum drift is an alert, never a success');
$uninitialized=$base;
$uninitialized['healthy']=false;
$uninitialized['worlds'][0]['initialized']=false;
check(array_column($policy->evaluate($uninitialized)['alerts'],'code')===
    ['operations_unhealthy','world_uninitialized'],
    'M11 missing initialized world cannot silently pass alerting');
rejects(fn()=> $policy->evaluate(['schemaVersion'=>100,'worlds'=>[]]),
    InvalidArgumentException::class,'M11 unknown alert schema is refused');
rejects(fn()=> $policy->evaluate($base,0),
    InvalidArgumentException::class,'M11 invalid alert limits are rejected');

$malformed=$base;
unset($malformed['worlds'][0]['oldestDueSeconds']);
rejects(fn()=> $policy->evaluate($malformed),
    InvalidArgumentException::class,'M11 missing lag metric cannot produce false-green status');
$malformed=$base;
$malformed['worlds'][0]['dueEvents']='not-a-number';
rejects(fn()=> $policy->evaluate($malformed),
    InvalidArgumentException::class,'M11 malformed backlog metric cannot silently cast to zero');
$malformed=$base;
$malformed['worlds'][0]['outboxPending']=-1;
rejects(fn()=> $policy->evaluate($malformed),
    InvalidArgumentException::class,'M11 negative outbox count is invalid');
$malformed=$base;
$malformed['worlds'][0]['outboxPendingCapped']='false';
rejects(fn()=> $policy->evaluate($malformed),
    InvalidArgumentException::class,'M11 malformed cap flag is rejected');
$malformed=$base;
$malformed['worlds'][0]['oldestDueSeconds']=-5;
rejects(fn()=> $policy->evaluate($malformed),
    InvalidArgumentException::class,'M11 negative event age is rejected');
