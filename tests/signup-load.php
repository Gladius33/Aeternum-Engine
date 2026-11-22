<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,GamePackLoader,SystemClock,PdoIdentity};
$workers=[];
for($i=0;$i<8;$i++) {
    $p=proc_open([PHP_BINARY,__DIR__.'/signup-load-worker.php',(string)$i],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('Unable to start real load worker');
    fclose($pipes[0]);
    $workers[]=[$p,$pipes];
}
$statuses=[];$latencies=[];$rss=[];
foreach($workers as [$p,$pipes]) {
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0) throw new RuntimeException('Load worker failed: '.$err);
    $result=json_decode(trim($out),true,8,JSON_THROW_ON_ERROR);
    if(!in_array($result['status']??null,[201,429,503],true)
       || !is_numeric($result['latencyMs']??null)
       || $result['latencyMs']<0) throw new RuntimeException('Malformed load worker evidence');
    $statuses[]=$result['status'];$latencies[]=$result['latencyMs'];
    if(is_int($result['rssKiB']??null)) $rss[]=$result['rssKiB'];
}
sort($latencies,SORT_NUMERIC);
$percentile=static fn(float $q):float=>$latencies[min(count($latencies)-1,(int)ceil($q*count($latencies))-1)];
$counts=array_count_values($statuses);
$successes=$counts[201]??0;
$pdo=db();
$s=$pdo->prepare("SELECT COUNT(*) FROM engine_onboarding WHERE world_id=? AND status='ready'");
$s->execute(['m01-ci-load-world']);
if((int)$s->fetchColumn()!==$successes) throw new RuntimeException('Load caused partial or missing enrollments');
if($successes>8) throw new RuntimeException('Configured world quota ignored');
$s=$pdo->prepare("SELECT COUNT(*) FROM engine_onboarding WHERE world_id=? AND status='pending'");
$s->execute(['m01-ci-load-world']);
if((int)$s->fetchColumn()!==0) throw new RuntimeException('Load left orphaned pending signup');
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$rules=$pack->rules();
$identity=new PdoIdentity($pdo);
if($successes===0) throw new RuntimeException('Load yielded no admitted player');
for($i=0;$i<8;$i++) {
    $actor=$identity->authenticate('m01-ci-load-'.$i,'real worker load test password');
    if($actor===null) continue;
    $game=new PdoScheduler($pdo,'m01-ci-load-world',new SystemClock(),$rules);
    foreach($pack->resources as $resource) {
        if($game->balanceForActor($actor,$actor,$resource->id,$rules->commands)->stock<$resource->initialStock)
            throw new RuntimeException('Load actor lost initial resources');
    }
}
$summary=[
    'scenario'=>'m01-lab-8-independent-php-workers',
    'workers'=>count($statuses),
    'http201'=>$successes,'http429'=>$counts[429]??0,'http503'=>$counts[503]??0,
    'p50Ms'=>$percentile(0.5),'p95Ms'=>$percentile(0.95),
    'maxWorkerRssKiB'=>$rss===[]?null:max($rss),
    'readyRecords'=>$successes,'pendingRecords'=>0,
    'scope'=>'disposable MariaDB CI; not external hosting SLO'
];
echo json_encode($summary,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
