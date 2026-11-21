<?php
declare(strict_types=1);
use Aeternum\Core\{PdoRateLimiter,FrozenClock};
// Run after the other rate limiter consumers using earlier fixture clocks.
$rateCleanPdo=db();
$rateOld=new PdoRateLimiter(db(),new FrozenClock(86400));
check($rateOld->admit('cleanup-old',1,86400),'old bucket admitted');
check(!$rateOld->admit('cleanup-old',1,86400),'old bucket exhausted');
$rateCleanClock=new FrozenClock(1000000);
$rateClean=new PdoRateLimiter($rateCleanPdo,$rateCleanClock);
check($rateClean->admit('cleanup-active',1,86400),'active bucket admitted');
$cutoff=1000000-604800;
$oldCount=(int)$rateCleanPdo->query('SELECT COUNT(*) FROM engine_rate_limits WHERE window_start<='.$cutoff)->fetchColumn();
check($oldCount>=1,'old rows exist');
check($rateClean->purgeStale(1)===1,'bounded rate cleanup');
check((int)$rateCleanPdo->query('SELECT COUNT(*) FROM engine_rate_limits WHERE window_start<='.$cutoff)->fetchColumn()===$oldCount-1,'one old row removed');
check(!$rateOld->admit('cleanup-old',1,86400),'old window cannot reopen after clock rollback');
check(!$rateOld->admit('unknown-after-rollback',1,86400),'old clock rejected even without existing bucket');
check(!$rateClean->admit('cleanup-active',1,86400),'active bucket survives cleanup');
check($rateClean->purgeStale(1000)===$oldCount-1,'remaining stale buckets removed');
check($rateClean->purgeStale()===0,'cleanup retry is no-op');
$floor=(int)$rateCleanPdo->query('SELECT minimum_now FROM engine_rate_limit_maintenance WHERE singleton=1')->fetchColumn();
check($floor===$cutoff+86400,'durable rollback floor');
check((new PdoRateLimiter(db(),new FrozenClock($floor)))->admit('cleanup-floor',1,86400),'exact floor admitted');
rejects(fn()=> $rateClean->purgeStale(0),InvalidArgumentException::class,'invalid cleanup limit');
rejects(fn()=> $rateClean->purgeStale(1001),InvalidArgumentException::class,'excess cleanup limit');
rejects(fn()=> $rateOld->purgeStale(),DomainException::class,'cleanup refuses clock below floor');
$rateCleanPdo->beginTransaction();
rejects(fn()=> $rateClean->purgeStale(),LogicException::class,'rate cleanup refuses outer transaction');
$rateCleanPdo->rollBack();
check($rateClean->admit('rate-cleanup-stale-race',1,86400),'initialize exhausted stale race bucket');
$rateRaceChildren=[];
foreach(['admit','purge','admit','stale','admit','purge','admit'] as $rateRaceMode) {
    $p=proc_open([PHP_BINARY,__DIR__.'/rate-cleanup-worker.php',$rateRaceMode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p)) throw new RuntimeException('Cannot spawn rate worker');
    fclose($pipes[0]); $rateRaceChildren[]=[$p,$pipes,$rateRaceMode];
}
$rateAdmitted=0;
foreach($rateRaceChildren as [$p,$pipes,$rateRaceMode]) {
    $out=trim(stream_get_contents($pipes[1])); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if(proc_close($p)!==0 || !preg_match('/^[0-9]+$/D',$out)) throw new RuntimeException('Rate subprocess failed: '.$err);
    if($rateRaceMode==='admit') { check((int)$out<=1,'admission result bounded'); $rateAdmitted+=(int)$out; }
    if($rateRaceMode==='stale') check($out==='0','stale admission denied during purge race');
}
check($rateAdmitted===2,'concurrent admissions and cleanup enforce exact limit');
$rateLatest=new PdoRateLimiter(db(),new FrozenClock(2000000));
check(!$rateLatest->admit('rate-cleanup-race',2,60),'new active bucket remains exhausted');
check(!$rateClean->admit('rate-cleanup-stale-race',1,86400),'stale admission denied after purge');
check($rateLatest->purgeStale()===0,'parallel purge removed all stale fixture rows');
