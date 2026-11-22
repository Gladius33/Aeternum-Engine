<?php
declare(strict_types=1);
require __DIR__ . '/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,ScheduledEvent,EventHandler};
$pdo = db();
// Refuse any existing schema rather than dropping or modifying existing data.
if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) throw new RuntimeException('Integration tests require an empty disposable database');
$migrationRunner=new \Aeternum\Core\MigrationRunner($pdo);
$migrationPath=__DIR__.'/../database/migrations';
check(count($migrationRunner->run($migrationPath))===count(glob($migrationPath.'/*.sql')),'all schema migrations applied');
check($migrationRunner->run($migrationPath)===[],'migration rerun is no-op');
$pdo->exec("UPDATE engine_schema_migrations SET completed=0 ORDER BY filename DESC LIMIT 1");
rejects(fn()=> $migrationRunner->run($migrationPath),RuntimeException::class,'dirty migration rejected');
$pdo->exec("UPDATE engine_schema_migrations SET completed=1");
$clock = new FrozenClock(100);
$id = 'world-A';
$q = new PdoScheduler($pdo,$id,$clock);
$q->initialize();
$handler = new class implements EventHandler {
    public function apply(ScheduledEvent $e,array $s): array {
        if ($e->type === 'fail') throw new RuntimeException('injected failure');
        if ($e->type === 'invalid') return ['bad'=>[]];
        $s['trace'] = ($s['trace'] ?? '') . $e->id;
        return $s;
    }
};
$event = new ScheduledEvent($id,'a','append',90,['x'=>1,'y'=>true]);
check($q->schedule($event),'persistent insert');
check(!$q->schedule(new ScheduledEvent($id,'a','append',90,['y'=>true,'x'=>1])),'canonical duplicate');
rejects(fn()=> $q->schedule(new ScheduledEvent($id,'a','append',91)),DomainException::class,'conflicting duplicate');
rejects(fn()=> $q->schedule(new ScheduledEvent('world-B','x','append',90)),InvalidArgumentException::class,'world scope');
$q->schedule(new ScheduledEvent($id,'b','append',80));
$q->schedule(new ScheduledEvent($id,'c','append',90));
$q->schedule(new ScheduledEvent($id,'d','append',110));
check($q->processDue($handler,2) === ['b','a'],'chronological stable bounded processing');
$q = new PdoScheduler(db(),$id,$clock);
$q->initialize();
check($q->state() === ['trace'=>'ba'],'state survives reconnect and initialization');
check($q->processDue($handler) === ['c'],'pending survives reconnect');
check(!$q->schedule($event),'completed event remains idempotent');
rejects(fn()=> $q->schedule(new ScheduledEvent($id,'old','append',89)),DomainException::class,'watermark');
$clock->advanceTo(110);
check($q->processDue($handler) === ['d'],'future event executed at cutoff');
$q->schedule(new ScheduledEvent($id,'e','fail',110));
$q->schedule(new ScheduledEvent($id,'f','append',110));
rejects(fn()=> $q->processDue($handler),RuntimeException::class,'failure propagates');
check($q->state() === ['trace'=>'bacd'] && $q->pendingCount() === 2,'failed transaction rolled back');
$recovery = new class implements EventHandler {
    public function apply(ScheduledEvent $e,array $s): array {
        $s['trace'] .= $e->id;
        return $s;
    }
};
check($q->processDue($recovery) === ['e','f'] && $q->state()['trace'] === 'bacdef','retry recovers in order');
$q->schedule(new ScheduledEvent($id,'g','invalid',110));
rejects(fn()=> $q->processDue($handler),UnexpectedValueException::class,'invalid state rejected');
check($q->pendingCount() === 1 && $q->state()['trace'] === 'bacdef','invalid state not committed');
$other = new PdoScheduler(db(),'world-B',$clock);
$other->initialize();
$other->schedule(new ScheduledEvent('world-B','a','append',100));
check($other->processDue($handler) === ['a'] && $other->state()['trace'] === 'a','same IDs in separate worlds');
check($q->state()['trace'] === 'bacdef','world state isolated');

function parallel(string $world,string $action): void {
    $children = [];
    for ($i=0; $i<4; $i++) {
        $p = proc_open([PHP_BINARY,__DIR__.'/db-worker.php',$world,$action,(string)$i],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($p)) throw new RuntimeException('Unable to spawn worker');
        fclose($pipes[0]);
        $children[] = [$p,$pipes];
    }
    foreach ($children as [$p,$pipes]) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($p) !== 0) throw new RuntimeException('Worker failed: '.$out.$err);
    }
}
$race = new PdoScheduler(db(),'race',new FrozenClock(500));
$race->initialize();
parallel('race','schedule');
check($race->pendingCount() === 1,'four concurrent duplicate submissions produced one event');
for ($i=0;$i<30;$i++) $race->schedule(new ScheduledEvent('race','ev-'.$i,'inc',200));
parallel('race','process');
check($race->pendingCount() === 0 && $race->state()['n'] === 31,'four concurrent processors committed each event once');
require __DIR__ . "/commands.php";
require __DIR__ . "/journal.php";
require __DIR__ . "/outbox.php";
require __DIR__ . "/rules.php";
require __DIR__ . "/economy.php";
require __DIR__ . "/upgrades.php";
require __DIR__ . "/packs-db.php";
require __DIR__ . "/actors.php";
require __DIR__ . "/entities.php";
require __DIR__ . "/identity.php";
require __DIR__ . "/sessions.php";
require __DIR__ . "/session-cleanup.php";
require __DIR__ . "/http-api.php";
require __DIR__ . "/rate-limits.php";
require __DIR__ . "/auth-http.php";
require __DIR__ . "/auth-global.php";
require __DIR__ . "/economy-http.php";
require __DIR__ . "/provisioning.php";
require __DIR__ . "/onboarding.php";
require __DIR__ . "/recovery.php";
require __DIR__ . "/invitations.php";
require __DIR__ . "/invite-cli.php";
require __DIR__ . "/world-worker.php";
require __DIR__.'/player-discovery.php';
require __DIR__ . "/market-http.php";
require __DIR__ . "/rate-cleanup.php";
require __DIR__ . "/maintenance-cli.php";
require __DIR__ . "/market.php";
echo "MariaDB integration checks passed.\n";
