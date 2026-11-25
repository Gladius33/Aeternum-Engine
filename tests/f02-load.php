<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{MigrationRunner,GamePackLoader,PdoIdentity,PlayerProvisioner,PdoScheduler,Command,SystemClock,WorldWorker,ScoreProjector,PdoEconomyProjection};
$pdo=db();
if (getenv('AETERNUM_F02_DISPOSABLE')!=='1' || $pdo->query('SELECT DATABASE()')->fetchColumn()!=='aeternum_f02')
    throw new RuntimeException('F02 requires explicit isolated load database');
$mode=$argv[1]??'';$dir=$argv[2]??'';
if ($argc!==3 || !is_dir($dir) || !str_starts_with($dir,'/')) throw new InvalidArgumentException('Expected private harness directory');
$world='f02-load-world';
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/versions/0.2.0/manifest.json'));
$rules=$pack->rules();$clock=new SystemClock();
$scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
$q=static function(string $sql,array $args=[]) use($pdo): PDOStatement {
    $s=$pdo->prepare($sql);$s->execute($args);return $s;
};
$emit=static function(array $r):void {echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;};
if ($mode==='setup') {
    if ($pdo->query('SHOW TABLES')->fetchColumn()!==false) throw new RuntimeException('F02 setup refuses populated database');
    $old=$dir.'/pre032';if (!mkdir($old,0700)) throw new RuntimeException('Cannot stage old migrations');
    $migrations=__DIR__.'/../database/migrations';
    foreach (glob($migrations.'/*.sql') as $f) if ((int)basename($f)<32 && !copy($f,$old.'/'.basename($f)))
        throw new RuntimeException('Cannot stage migration');
    $runner=new MigrationRunner($pdo);
    if (count($runner->run($old))!==31) throw new RuntimeException('Wrong pre032 schema');
    $identities=new PdoIdentity($pdo);$provision=new PlayerProvisioner($pdo,$clock);$players=[];
    for ($i=0;$i<33;$i++) {
        $login='f02-load-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        $password=bin2hex(random_bytes(24));$actor=$identities->register($login,$password);
        $provision->provision($world,$actor,$pack,$rules);
        $players[]=['login'=>$login,'password'=>$password,'account'=>$actor];
    }
    $a=$players[0]['account'];$b=$players[32]['account'];
    $create=static function(string $id,string $seller) use($scheduler,$world,$rules):void {
        $scheduler->executeForActor($seller,new Command($world,'create-'.$id,'market.create',
            ['offer'=>$id,'seller'=>$seller,'giveResource'=>'spice','giveUnits'=>1,'wantResource'=>'silver','wantUnits'=>1]),$rules->commands);
    };
    $create('pre032-trade',$b);
    $scheduler->executeForActor($a,new Command($world,'pre032-purchase','market.accept',
        ['offer'=>'pre032-trade','seller'=>$b,'buyer'=>$a]),$rules->commands);
    $create('solo-partner',$b);
    $before=$scheduler->verifyReplay($rules->commands,$rules->events);
    if ($runner->run($migrations)!==['032_score_projection.sql'] || $runner->run($migrations)!==[])
        throw new RuntimeException('Populated score migration did not converge');
    $scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
    if ($scheduler->scoreForActor($a,$a,$pack->progression)['global']!==6
        || $scheduler->auditScores()!==2
        || $scheduler->verifyReplay($rules->commands,$rules->events)!=$before)
        throw new RuntimeException('Historical score migration changed canonical history');
    file_put_contents($dir.'/players.json',json_encode($players,JSON_THROW_ON_ERROR));chmod($dir.'/players.json',0600);
    file_put_contents($dir.'/worlds.json',json_encode(['registry_api'=>'1','worlds'=>[
        ['world'=>$world,'pack'=>$pack->id,'version'=>$pack->version,'sha256'=>$pack->digest]
    ]],JSON_THROW_ON_ERROR));chmod($dir.'/worlds.json',0600);
    $emit(['accounts'=>count($players),'migration'=>'populated-031-to-032','historical_replay'=>true]);
} elseif ($mode==='worker') {
    $processed=0;$maxLag=0;$iterations=0;$started=hrtime(true);
    $worker=new WorldWorker($pdo,$clock);
    while (!is_file($dir.'/stop-worker') && (hrtime(true)-$started)/1e9<240) {
        $oldest=$q('SELECT MIN(due_at) FROM engine_events WHERE world_id=? AND completed=0 AND due_at<=?',[$world,time()])->fetchColumn();
        if ($oldest!==null && $oldest!==false) {
            $maxLag=max($maxLag,time()-(int)$oldest);
            $processed+=count($worker->run($world,$pack,64,2000,$rules));
        }
        $iterations++;usleep(100000);
    }
    $emit(['processed'=>$processed,'max_observed_due_lag_seconds'=>$maxLag,'polls'=>$iterations]);
} elseif ($mode==='metrics') {
    $names=['Innodb_row_lock_waits','Innodb_row_lock_time','Innodb_data_read','Innodb_data_written','Threads_connected','Max_used_connections','Innodb_buffer_pool_reads','Innodb_buffer_pool_read_requests'];
    $rows=$pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('".implode("','",$names)."')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $emit(array_map('intval',$rows));
} elseif ($mode==='verify') {
    $state=$scheduler->state();$replay=$scheduler->verifyReplay($rules->commands,$rules->events);
    $rows=$scheduler->auditScores();
    $players=json_decode(file_get_contents($dir.'/players.json'),true,8,JSON_THROW_ON_ERROR);
    $oracle=new ScoreProjector($pack->progression);
    foreach ($players as $p) {
        $account=$p['account'];
        if ($scheduler->scoreForActor($account,$account,$pack->progression)!==$oracle->forAccount($state,$account)
            || $scheduler->rankForActor($account,$account,$pack->progression)['participants']!==33)
            throw new RuntimeException('F02 score or participant divergence');
    }
    $filled=(int)$q("SELECT COUNT(*) FROM engine_market_offers WHERE world_id=? AND status='filled'",[$world])->fetchColumn();
    $builds=(int)$q("SELECT COUNT(*) FROM engine_buildings WHERE world_id=?",[$world])->fetchColumn();
    $completed=(int)$q("SELECT COUNT(*) FROM engine_events WHERE world_id=? AND event_type='building.complete' AND completed=1",[$world])->fetchColumn();
    if ($filled!==54 || $builds!==53 || $completed!==53 || $scheduler->pendingCount()!==0)
        throw new RuntimeException('F02 workload did not complete all expected effects');
    $economy=(new PdoEconomyProjection($pdo,$world))->assertConsistent($state);
    $ledger=$q('SELECT resource_id,SUM(delta_units) AS units FROM engine_resource_ledger WHERE world_id=? GROUP BY resource_id',[$world])->fetchAll(PDO::FETCH_KEY_PAIR);
    $stock=$q('SELECT resource_id,SUM(stock_units) AS units FROM engine_economy_balances WHERE world_id=? GROUP BY resource_id',[$world])->fetchAll(PDO::FETCH_KEY_PAIR);
    ksort($ledger);ksort($stock);
    if (array_map('intval',$ledger)!==array_map('intval',$stock)) throw new RuntimeException('F02 ledger and physical stocks differ');
    $plan=$q('EXPLAIN SELECT COUNT(*) FROM engine_account_scores s JOIN engine_account_owners o ON o.world_id=s.world_id AND o.account_id=s.account_id JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id AND m.active=1 WHERE s.world_id=? AND s.global_score>?',[$world,6])->fetchAll(PDO::FETCH_ASSOC);
    $emit(['replay_entries'=>$replay->entries,'score_rows'=>$rows,'filled_trades'=>$filled,'completed_buildings'=>$completed,
        'pending_events'=>0,'ledger_conserved'=>true,'economy_projection'=>$economy,
        'rank_explain'=>array_map(static fn($r)=>['table'=>$r['table'],'access'=>$r['type'],'index'=>$r['key'],'estimated_rows'=>(int)$r['rows']],$plan)]);
} else throw new InvalidArgumentException('Unknown F02 operation');
