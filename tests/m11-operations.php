<?php
declare(strict_types=1);
use Aeternum\Core\{OperationsSnapshot,OperationsCron,PdoScheduler,PdoMemberships,GamePackLoader,
    Command,FrozenClock,PdoRateLimiter,WorldWorker,MigrationRunner};
// This disposable real MariaDB fixture runs at the END of integration tests,
// after prior fixed-clock/recovery suites. It leaves the schema consistent
// for the existing CI dump/restore comparison.
(static function():void {
    $pdo=db();
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
    $rules=$pack->rules();
    $world='m11-operations-world';
    $initial=new FrozenClock(1000);
    $scheduler=new PdoScheduler($pdo,$world,$initial,$rules);
    $scheduler->initialize();
    $members=new PdoMemberships($pdo,$world);
    $members->grant('m11-player');
    $members->assign('m11-account','m11-player');
    $scheduler->executeForActor('m11-player',new Command($world,'m11-open','economy.open',
        ['account'=>'m11-account','resource'=>'wood']),$rules->commands);
    $receipt=$scheduler->executeForActor('m11-player',new Command($world,'m11-upgrade',
        'economy.upgrade',['account'=>'m11-account','resource'=>'wood']),$rules->commands);
    check(count($receipt->scheduledIds)===1,'M11 fixture produces one durable future job');
    $now=time()+10000;
    $clock=new FrozenClock($now);
    $monitor=new OperationsSnapshot($pdo,$clock,__DIR__.'/../database/migrations');
    $view=$monitor->snapshot([$world=>$pack]);
    check($view['migrations']['verified']===true
        && $view['migrations']['dirty']===0
        && $view['migrations']['applied']===count(glob(__DIR__.'/../database/migrations/*.sql')),
        'M11 read-only operations status verifies all checksum-pinned migrations');
    check($view['healthy']===true
        && $view['worlds'][0]['initialized']===true
        && $view['worlds'][0]['dueEvents']===1
        && $view['worlds'][0]['oldestDueSeconds']>0,
        'M11 due job backlog and lag are observed without processing');
    check($monitor->snapshot(['m11-not-initialized'=>$pack])['healthy']===false,
        'M11 missing approved world is unhealthy and never initialized by monitoring');
    rejects(fn()=> $monitor->snapshot(['bad world id'=>$pack]),
        InvalidArgumentException::class,'M11 read-only status rejects invalid world input');
    rejects(fn()=> $monitor->snapshot([$world=>new stdClass]),
        InvalidArgumentException::class,'M11 status rejects untrusted pack');
    // Dirty migration should fail closed without attempting a schema repair.
    $file='031_admin_test_entitlements.sql';
    $s=$pdo->prepare('UPDATE engine_schema_migrations SET completed=0 WHERE filename=?');
    $s->execute([$file]);
    try {
        $bad=$monitor->snapshot([$world=>$pack]);
        check(!$bad['healthy']&&$bad['migrations']['dirty']===1,
            'M11 diagnostics detects dirty schema without mutating it');
    } finally {
        $pdo->prepare('UPDATE engine_schema_migrations SET completed=1 WHERE filename=?')->execute([$file]);
    }
    check($monitor->snapshot([$world=>$pack])['healthy'],
        'M11 temporary dirty marker restored for legacy migrations');
    $service=new OperationsCron($pdo,$clock);
    rejects(fn()=> $service->run([$world=>$pack],101),
        InvalidArgumentException::class,'M11 quota rejects 101 due events per cycle');
    rejects(fn()=> $service->run([$world=>$pack],1,501),
        InvalidArgumentException::class,'M11 quota rejects cleanup batch over 500');
    rejects(fn()=> $service->run(['bad world'=>$pack]),
        InvalidArgumentException::class,'M11 CRON forbids unapproved world ID');
    // A real independent session holds the database-scoped lock. The service
    // must skip and not consume the durable job, rather than overlapping.
    $name=hash('sha256','aeternum:m11:cron:'.(string)$pdo->query('SELECT DATABASE()')->fetchColumn());
    $holder=db();
    $acquire=$holder->prepare('SELECT GET_LOCK(?,0)');
    $acquire->execute([$name]);
    check((int)$acquire->fetchColumn()===1,'M11 independent PDO acquires CRON advisory lock');
    try {
        $skip=$service->run([$world=>$pack]);
        check($skip['skipped']==='already_running'&&$skip['worlds']===[],
            'M11 overlapping CRON skips without changing persistent events');
        check((new PdoScheduler(db(),$world,$clock,$rules))->pendingCount()===1,
            'M11 overlapping CRON preserves pending job exactly once');
    } finally {
        $holder->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
        $holder=null;
    }
    $first=$service->run([$world=>$pack],1,100,9000);
    check($first['skipped']===null && $first['worlds'][0]['processed']===1
        && $first['maintenanceDeferred']===false
        && is_array($first['cleanup']),
        'M11 first bounded CRON cycle commits one due event and cleanup counts');
    $second=$service->run([$world=>$pack],1,100,9000);
    check($second['skipped']===null && $second['worlds'][0]['processed']===0,
        'M11 repeated CRON does not duplicate completed event');
    check((new PdoScheduler(db(),$world,$clock,$rules))->pendingCount()===0,
        'M11 zero pending events after real repeated processing');
    check($monitor->snapshot([$world=>$pack])['worlds'][0]['dueEvents']===0,
        'M11 read-only backlog reflects committed CRON progress after reconnect');
    // A backwards operational clock must not silently re-open a cleaned
    // limiter bucket. Existing limiter guard is re-used, not bypassed.
    $past=new FrozenClock(1);
    rejects(fn()=> (new OperationsCron(db(),$past))->run([$world=>$pack]),
        DomainException::class,'M11 backwards-clock maintenance fails safely');
    $again=$service->run([$world=>$pack]);
    check($again['skipped']===null,
        'M11 maintenance exception releases advisory lock for subsequent run');
    $report=json_encode($again,JSON_THROW_ON_ERROR);
    check(!str_contains($report,'m11-player')&&!str_contains($report,'m11-account')
        && !str_contains($report,'mysql:')&&!str_contains($report,'token_hash'),
        'M11 CRON JSON excludes player IDs and database credentials');
})();
