<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,FrozenClock,EconomyCommandHandler,EconomyEventHandler,
    ResourceDefinition,RuleSet};

// Runs after the M03 1001-offer / 1002-balance real MariaDB fixture.
// This is a bounded CI smoke performance floor, NOT a hosted-world SLA.
(static function():void {
    $pdo=db();
    $economy=new EconomyCommandHandler([
        new ResourceDefinition('ore',2000,2000,0,1),
        new ResourceDefinition('coin',0,2000,0,1)
    ]);
    $rules=new RuleSet('m03-lifetime','1',hash('sha256','m03-lifetime'),
        $economy,new EconomyEventHandler($economy));
    $scheduler=new PdoScheduler($pdo,'m03-scale-world',new FrozenClock(100),$rules);
    $marketTimes=[];$balanceTimes=[];
    $start=hrtime(true);
    for ($n=0;$n<100;$n++) {
        $t=hrtime(true);
        $page=$scheduler->marketForActor('scale-reader',null,50);
        $marketTimes[]=(hrtime(true)-$t)/1000000;
        if (count($page['offers'])!==50 || $page['offers'][0]['id']!=='o0000') {
            throw new RuntimeException('M12 indexed market data changed during read-only load');
        }
        $t=hrtime(true);
        $stock=$scheduler->balanceForActor('scale-owner-1000','account1000','ore',$economy)->stock;
        $balanceTimes[]=(hrtime(true)-$t)/1000000;
        if ($stock!==2000) throw new RuntimeException('M12 read-only balance data changed');
    }
    sort($marketTimes,SORT_NUMERIC);sort($balanceTimes,SORT_NUMERIC);
    $p95=static fn(array $samples):float=>$samples[(int)ceil(count($samples)*.95)-1];
    $wall=(hrtime(true)-$start)/1000000;
    $marketP95=$p95($marketTimes);$balanceP95=$p95($balanceTimes);
    echo sprintf("M12 capacity lab: existing 1001 offers + 1002 balances, 200 authorized reads, market p95 %.2f ms, balance p95 %.2f ms, wall %.2f ms\n",
        $marketP95,$balanceP95,$wall);
    check($marketP95<1000 && $balanceP95<1000 && $wall<30000,
        'M12 indexed reads fit generous isolated CI safety envelope');
    // This is the first measured lower bound; no production concurrency,
    // public throughput, SLA or maximum player capacity is inferred here.
})();
