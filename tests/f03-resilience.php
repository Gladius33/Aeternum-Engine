<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{MigrationRunner,GamePackLoader,PlayerProvisioner,PdoIdentity,PdoSessions,PdoMemberships,PdoScheduler,PdoEconomyProjection,PdoSocialMessages,ProgressionHttpApi,PremiumHttpApi,EconomyHttpApi,SocialHttpApi,WorldWorker,FrozenClock,SystemClock,ScoreProjector,Command};
if (getenv('AETERNUM_F03_DISPOSABLE')!=='1') throw new RuntimeException('Explicit disposable permission required');
$pdo=db();
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='aeternum_f03'
    || $pdo->query('SHOW TABLES')->fetchColumn()!==false) throw new RuntimeException('F03 requires its own empty database');
(new MigrationRunner($pdo))->run(__DIR__.'/../database/migrations');
$world='f03-world';$at=time();$clock=new FrozenClock($at);
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/versions/0.2.0/manifest.json'));
$rules=$pack->rules();$scheduler=new PdoScheduler($pdo,$world,$clock,$rules);
$provision=new PlayerProvisioner($pdo,$clock);$identities=new PdoIdentity($pdo);$sessions=new PdoSessions($pdo,$clock);
$actors=[];$tokens=[];
for ($i=0;$i<4;$i++) {
    $login='f03-player-'.$i;$password=bin2hex(random_bytes(24));
    $actors[$i]=$identities->register($login,$password);
    $provision->provision($world,$actors[$i],$pack,$rules);
    $tokens[$i]=$sessions->login($login,$password);
    check(is_string($tokens[$i]),'F03 real fixture actor authenticated');
}
[$a,$b]=$actors;
$api=new EconomyHttpApi($pdo,$clock,[$world=>$rules]);$social=new SocialHttpApi($pdo,$clock,[$world=>$pack]);
$command=static fn(string $id,string $type,array $payload):array=>['id'=>$id,'type'=>$type,'payload'=>$payload];
$submit=static fn(int $i,array $body):array=>$api->handle('POST','/v1/worlds/'.$world.'/economy/commands',
    'Bearer '.$tokens[$i],json_encode($body,JSON_THROW_ON_ERROR));
$q=static function(string $sql,array $args=[])use($pdo):PDOStatement {
    $s=$pdo->prepare($sql);$s->execute($args);return $s;
};
$tables=['engine_worlds','engine_commands','engine_events','engine_journal','engine_resource_ledger','engine_outbox',
    'engine_economy_balances','engine_market_offers','engine_market_refunds','engine_account_scores',
    'engine_score_watermarks','engine_buildings','engine_social_messages','engine_social_notifications'];
$fingerprint=static function()use($q,$tables,$world):string {
    $digest=[];
    foreach ($tables as $table) {
        $rows=[];
        $s=$q('SELECT * FROM '.$table.' WHERE world_id=?',[$world]);
        while ($row=$s->fetch(PDO::FETCH_ASSOC)) {
            ksort($row,SORT_STRING);$rows[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
        }
        sort($rows,SORT_STRING);$digest[$table]=$rows;
    }
    return hash('sha256',json_encode($digest,JSON_THROW_ON_ERROR));
};
$audit=static function()use($pdo,$scheduler,$rules,$pack,$actors,$world,$q):array {
    $replay=$scheduler->verifyReplay($rules->commands,$rules->events);
    $state=$scheduler->state();$projection=(new PdoEconomyProjection($pdo,$world))->assertConsistent($state);
    $scoreRows=$scheduler->auditScores();
    foreach ($actors as $actor) {
        check($scheduler->scoreForActor($actor,$actor,$pack->progression)===(new ScoreProjector($pack->progression))->forAccount($state,$actor),
            'F03 indexed score agrees with replay-derived score');
    }
    $ledger=$q('SELECT resource_id,SUM(delta_units) FROM engine_resource_ledger WHERE world_id=? GROUP BY resource_id',[$world])->fetchAll(PDO::FETCH_KEY_PAIR);
    $stock=$q('SELECT resource_id,SUM(stock_units) FROM engine_economy_balances WHERE world_id=? GROUP BY resource_id',[$world])->fetchAll(PDO::FETCH_KEY_PAIR);
    ksort($ledger);ksort($stock);
    check(array_map('intval',$ledger)===array_map('intval',$stock),'F03 conservation holds after recovery');
    $outbox=(int)$q('SELECT COUNT(*) FROM engine_outbox WHERE world_id=?',[$world])->fetchColumn();
    check($outbox===$replay->entries,'F03 one durable outbox envelope per committed journal entry');
    return ['journal_entries'=>$replay->entries,'outbox_entries'=>$outbox,'score_rows'=>$scoreRows,'projection'=>$projection];
};
$start=static function(string $mode,array $input):array {
    $proc=proc_open([PHP_BINARY,__DIR__.'/f03-fault-worker.php',$mode],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($proc)) throw new RuntimeException('Cannot launch F03 child');
    fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);
    stream_set_timeout($pipes[1],8);
    $line=fgets($pipes[1]);
    if ($line===false) {
        proc_terminate($proc,9);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);
        throw new RuntimeException('F03 child connection handshake failed');
    }
    $id=json_decode($line,true,4,JSON_THROW_ON_ERROR)['connection']??0;
    if (!is_int($id) || $id<1) throw new RuntimeException('Invalid child connection');
    return [$proc,$pipes,$id];
};
$finish=static function(array $child,bool $expectFailure=false):array {
    [$proc,$pipes]=$child;
    $out=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
    if ($expectFailure) {
        check($exit!==0,'F03 interrupted worker exits without a success receipt');return [];
    }
    if ($exit!==0 || $out==='') throw new RuntimeException('F03 child failed (sanitized)');
    return json_decode($out,true,8,JSON_THROW_ON_ERROR);
};
// Test-only database barrier. No failpoint or switch in packaged PHP.
// By the outbox insert, canonical state, receipts, projections and journal
// have been written but not committed; by notification insert, message exists.
$interruption=static function(string $table,string $mode,array $input,bool $killProcess)use($pdo,$q,$start,$finish,$fingerprint):void {
    if (!in_array($table,['engine_outbox','engine_social_notifications'],true)) throw new LogicException('Unsafe test barrier table');
    $before=$fingerprint();$child=null;
    $pdo->exec("CREATE TRIGGER f03_barrier BEFORE INSERT ON ".$table." FOR EACH ROW
        BEGIN
          IF NEW.world_id='f03-world' THEN
            SET @f03_marker=GET_LOCK('aeternum_f03_marker',0);
            SET @f03_gate=GET_LOCK('aeternum_f03_gate',20);
            IF @f03_gate<>1 OR @f03_marker<>1 THEN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='F03 barrier unavailable';
            END IF;
          END IF;
        END");
    try {
        check((int)$q("SELECT GET_LOCK('aeternum_f03_gate',0)")->fetchColumn()===1,'F03 test barrier acquired');
        $child=$start($mode,$input);$id=$child[2];$deadline=microtime(true)+8;
        do {
            $owner=$q("SELECT IS_USED_LOCK('aeternum_f03_marker')")->fetchColumn();
            if ((int)$owner===$id) break;
            usleep(20000);
        } while (microtime(true)<$deadline);
        check((int)$owner===$id,'F03 child reached real uncommitted SQL write barrier');
        check($fingerprint()===$before,'F03 other connection cannot see uncommitted effects');
        if ($killProcess) proc_terminate($child[0],9);
        // KILL CONNECTION cancels the blocked statement and releases the
        // InnoDB transaction even if the dead PHP socket is not noticed yet.
        try {$pdo->exec('KILL CONNECTION '.$id);}
        catch (PDOException $e) {if ((int)($e->errorInfo[1]??0)!==1094) throw $e;}
        $finish($child,true);$child=null;
        $deadline=microtime(true)+8;
        do {
            $marker=$q("SELECT IS_USED_LOCK('aeternum_f03_marker')")->fetchColumn();
            if ($marker===null) break;
            usleep(20000);
        } while (microtime(true)<$deadline);
        check($marker===null,'F03 disconnected transaction released advisory marker');
        check($fingerprint()===$before,'F03 rollback preserved all canonical and derived table digests');
    } finally {
        if ($child!==null && is_resource($child[0])) {
            proc_terminate($child[0],9);
            try {$pdo->exec('KILL CONNECTION '.(int)$child[2]);}catch (Throwable) {}
            foreach ([1,2] as $fd) if (is_resource($child[1][$fd])) fclose($child[1][$fd]);
            proc_close($child[0]);
        }
        $q("SELECT RELEASE_LOCK('aeternum_f03_gate')")->fetchColumn();
        $pdo->exec('DROP TRIGGER IF EXISTS f03_barrier');
    }
};
$offer=$command('offer','market.create',['offer'=>'f03-offer','seller'=>$a,'giveResource'=>'spice','giveUnits'=>2,'wantResource'=>'silver','wantUnits'=>3]);
check($submit(0,$offer)[0]===200,'F03 real escrow established');
$buy=$command('buy','market.accept',['offer'=>'f03-offer','seller'=>$a,'buyer'=>$b]);
$interruption('engine_outbox','economy',['at'=>$at,'token'=>$tokens[1],'body'=>$buy],false);
$first=$submit(1,$buy);
check($first[0]===200 && json_encode($first,JSON_THROW_ON_ERROR)===json_encode($submit(1,$buy),JSON_THROW_ON_ERROR),
    'F03 exact request ID recovers aborted trade, then returns durable receipt');
check($scheduler->scoreForActor($a,$a,$pack->progression)['global']===6
    && $scheduler->scoreForActor($b,$b,$pack->progression)['global']===6,'F03 trade scores each participant exactly once');
$audit();

$place=$command('place','building.place',['building'=>'f03-watchtower','account'=>$a,'type'=>'watchtower','entity'=>$a,'territory'=>$a]);
check($submit(0,$place)[0]===200,'F03 building founded');
$build=$command('build','building.start',['building'=>'f03-watchtower','account'=>$a,'type'=>'watchtower']);
check($submit(0,$build)[0]===200 && $scheduler->pendingCount()===1,'F03 due work scheduled exactly once');
$clock->advanceTo($at+1);
$interruption('engine_outbox','due',['at'=>$at+1],true);
check($scheduler->pendingCount()===1,'F03 terminated worker leaves due event recoverable');
$workers=[];
for ($i=0;$i<4;$i++) $workers[]=$start('due',['at'=>$at+1]);
$done=0;
foreach ($workers as $child) $done+=$finish($child)['processed'];
check($done===1 && $scheduler->pendingCount()===0,'F03 four restarted workers complete exactly one event');
check($scheduler->scoreForActor($a,$a,$pack->progression)['military']===30,'F03 completed building score never duplicates');
check((new WorldWorker($pdo,$clock))->run($world,$pack,10,1000,$rules)===[],'F03 repeated worker is a no-op');
$before=$fingerprint();
rejects(fn()=>(new WorldWorker($pdo,new FrozenClock($at)))->run($world,$pack,10,1000,$rules),
    DomainException::class,'F03 worker rejects regressed clock');
check($fingerprint()===$before,'F03 regressed clock leaves canonical and derived state intact');
$audit();

$message=['id'=>'f03-message','recipient'=>$b,'text'=>'Synthetic recovery message'];
$interruption('engine_social_notifications','social',['at'=>$at+1,'token'=>$tokens[0],'body'=>$message],false);
$send=static fn():array=>$social->handle('POST','/v1/worlds/'.$world.'/social/messages','Bearer '.$tokens[0],
    json_encode($message,JSON_THROW_ON_ERROR));
check($send()[0]===200 && $send()[0]===200,'F03 social retry succeeds after lost SQL connection');
check((int)$q('SELECT COUNT(*) FROM engine_social_messages WHERE world_id=?',[$world])->fetchColumn()===1
    && (int)$q('SELECT COUNT(*) FROM engine_social_notifications WHERE world_id=?',[$world])->fetchColumn()===1,
    'F03 message and notification commit exactly once together');

// Lock the world, change membership, and observe a stale admitted request
// waiting for that lock. Authorization is rechecked after the lock is acquired.
$pdo->beginTransaction();$child=null;
try {
    $q('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE',[$world])->fetchColumn();
    $q('UPDATE engine_memberships SET active=0 WHERE world_id=? AND actor_id=?',[$world,$b]);
    $foreign=$command('stale-place','building.place',['building'=>'f03-stale','account'=>$b,'type'=>'watchtower','entity'=>$b,'territory'=>$b]);
    $child=$start('economy',['at'=>$at+1,'token'=>$tokens[1],'body'=>$foreign]);
    $deadline=microtime(true)+2;
    $waiting=false;
    do {
        $info=$q('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID=?',[$child[2]])->fetchColumn();
        if (is_string($info) && str_contains($info,'engine_worlds') && str_contains($info,'FOR UPDATE')) {$waiting=true;break;}
        usleep(20000);
    } while (microtime(true)<$deadline);
    check($waiting,'F03 stale admitted request reached world-lock boundary');
    $pdo->commit();
    $result=$finish($child);$child=null;
    check($result['status']===409,'F03 revoked membership denies request after lock acquisition');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($child!==null && is_resource($child[0])) {
        proc_terminate($child[0],9);
        foreach ([1,2] as $fd) if (is_resource($child[1][$fd])) fclose($child[1][$fd]);
        proc_close($child[0]);
    }
}
check((int)$q("SELECT COUNT(*) FROM engine_buildings WHERE world_id=? AND building_id='f03-stale'",[$world])->fetchColumn()===0,'F03 stale request cannot create a foundation');
$rank=new ProgressionHttpApi($pdo,$clock,[$world=>$pack]);
$premium=new PremiumHttpApi($pdo,$clock,[$world=>$pack]);
check($rank->handle('GET','/v1/worlds/'.$world.'/progression/'.$b.'/rank','Bearer '.$tokens[1])[0]===404
    && $premium->handle('GET','/v1/worlds/'.$world.'/premium/me','Bearer '.$tokens[1])[0]===404,'F03 stale member cannot read rank or entitlements');
(new PdoMemberships($pdo,$world))->grant($b);
$sessions->revoke($tokens[1]);
check($submit(1,$buy)[0]===401,'F03 revoked bearer cannot replay old receipt');

// Real HTTP response abandonment on an isolated loopback connection.
// The client closes after sending a complete request; a second request with
// exactly the same identity must recover the committed receipt.
$tmp=sys_get_temp_dir().'/aeternum-f03-'.bin2hex(random_bytes(8));
if (!mkdir($tmp,0700)) throw new RuntimeException('Cannot create F03 private runtime directory');
$registry=$tmp.'/worlds.json';
file_put_contents($registry,json_encode(['registry_api'=>'1','worlds'=>[
    ['world'=>$world,'pack'=>$pack->id,'version'=>$pack->version,'sha256'=>$pack->digest]
]],JSON_THROW_ON_ERROR));
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
if ($socket===false) throw new RuntimeException('Cannot reserve test port');
$address=stream_socket_get_name($socket,false);fclose($socket);
$env=array_merge(getenv(),['AETERNUM_DSN'=>getenv('AETERNUM_TEST_DSN'),'AETERNUM_DB_USER'=>getenv('AETERNUM_TEST_USER'),
    'AETERNUM_DB_PASSWORD'=>getenv('AETERNUM_TEST_PASSWORD'),'AETERNUM_WORLD_REGISTRY'=>$registry,'AETERNUM_BROWSER_TEST'=>'1']);
unset($env['AETERNUM_MEDIEVAL_WORLD'],$env['AETERNUM_INDUSTRIAL_WORLD'],$env['AETERNUM_MODULE_REGISTRY'],$env['PHP_CLI_SERVER_WORKERS']);
$root=dirname(__DIR__);$server=null;
try {
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$root.'/public',$root.'/tests/browser/router.php'],
        [0=>['file','/dev/null','r'],1=>['file',$tmp.'/http.log','a'],2=>['file',$tmp.'/http.log','a']],$pipes,$root,$env);
    if (!is_resource($server)) throw new RuntimeException('Cannot start F03 HTTP fixture');
    $deadline=microtime(true)+8;$conn=false;
    do {
        $conn=@stream_socket_client('tcp://'.$address,$errno,$errstr,.2);
        if ($conn!==false) break;
        usleep(50000);
    } while (microtime(true)<$deadline);
    if ($conn===false) throw new RuntimeException('F03 HTTP fixture unavailable');
    fclose($conn);
    // Keep real logical time at or beyond the worker watermark.
    while (time()<$at+1) usleep(20000);
    $body=json_encode($command('lost-reply','economy.transfer',['from'=>$a,'to'=>$actors[2],'resource'=>'silver','units'=>1]),JSON_THROW_ON_ERROR);
    $path='/v1/worlds/'.$world.'/economy/commands';
    $request="POST ".$path." HTTP/1.1\r\nHost: ".$address."\r\nAuthorization: Bearer ".$tokens[0].
        "\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body;
    $connection=stream_socket_client('tcp://'.$address,$errno,$errstr,2);
    if ($connection===false || fwrite($connection,$request)!==strlen($request)) throw new RuntimeException('F03 abandoned request send failed');
    fclose($connection);
    $deadline=microtime(true)+8;
    do {
        $count=(int)$q("SELECT COUNT(*) FROM engine_commands WHERE world_id=? AND actor_id=? AND client_request_id='lost-reply'",[$world,$a])->fetchColumn();
        if ($count===1) break;
        usleep(50000);
    } while (microtime(true)<$deadline);
    check($count===1,'F03 abandoned HTTP connection committed exactly one durable command');
    $snapshot=$fingerprint();
    $context=stream_context_create(['http'=>['method'=>'POST','header'=>"Authorization: Bearer ".$tokens[0]."\r\nContent-Type: application/json\r\n",
        'content'=>$body,'timeout'=>8,'ignore_errors'=>true]]);
    $response=file_get_contents('http://'.$address.$path,false,$context);
    check(is_string($response)&&str_contains($http_response_header[0]??'',' 200 '),'F03 reconnect recovers committed HTTP receipt');
    $receipt=json_decode($response,true,8,JSON_THROW_ON_ERROR);
    check(($receipt['data']['id']??null)==='lost-reply' || ($receipt['data']['requestId']??null)==='lost-reply','F03 response names original request');
    check($fingerprint()===$snapshot,'F03 recovered HTTP receipt does not repeat effects');
    // Same live HTTPS-equivalent ingress rejects browser cross-site and forms
    // before any database connection or mutation.
    foreach ([
        ["Sec-Fetch-Site: cross-site\r\nContent-Type: application/json\r\n",403],
        ["Content-Type: text/plain\r\n",415]
    ] as [$headers,$expected]) {
        $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>$headers,'content'=>$body,'ignore_errors'=>true,'timeout'=>5]]);
        file_get_contents('http://'.$address.$path,false,$ctx);
        check(str_contains($http_response_header[0]??'',' '.$expected.' '),'F03 actual HTTP early denial '.$expected);
    }
    check($fingerprint()===$snapshot,'F03 rejected cross-origin/form requests have no gameplay effects');
} finally {
    if (is_resource($server)) {proc_terminate($server);proc_close($server);}
    foreach (glob($tmp.'/*')?:[] as $file) unlink($file);
    rmdir($tmp);
}
$clock->advanceTo(max($clock->now(),time()));
$final=$audit();
check($scheduler->pendingCount()===0,'F03 recovered world has no stranded due work');
$report=['status'=>'passed','scope'=>'isolated fault-injection database','head'=>getenv('F03_SOURCE_SHA')?:'local',
    'checkout'=>getenv('GITHUB_SHA')?:'local','faults'=>['sql_connection_during_trade','sigkill_due_worker','sql_connection_during_notification',
        'membership_revoked_while_waiting','abandoned_http_response','clock_regression'],
    'restart_workers'=>4,'ledger_conserved'=>true,'projection_verified'=>true,'notification_count'=>1,
    'pending_events'=>0,'verification'=>$final];
file_put_contents(getenv('F03_REPORT_PATH')?:'f03-resilience.json',json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL);
echo 'F03_RESILIENCE_JSON '.json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;
