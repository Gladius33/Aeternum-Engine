<?php
declare(strict_types=1);
require __DIR__ . '/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,ScheduledEvent,EventHandler};
$pdo = db();
// Refuse any existing schema rather than dropping or modifying existing data.
if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) throw new RuntimeException('Integration tests require an empty disposable database');
$migrationRunner=new \Aeternum\Core\MigrationRunner($pdo);
$migrationPath=__DIR__.'/../database/migrations';
// Exercise a real 001..015 -> 016..018 migration on a NONEMPTY synthetic
// legacy world, not merely a fresh-schema install. This DB is disposable.
$legacyDir=sys_get_temp_dir().'/aeternum-m01-upgrade-'.bin2hex(random_bytes(8));
if(!mkdir($legacyDir,0700)) throw new RuntimeException('Unable to stage legacy migrations');
try {
    $allFiles=glob($migrationPath.'/*.sql');
    $legacyFiles=array_values(array_filter($allFiles,
        static fn(string $f):bool=>(int)substr(basename($f),0,3)<=15));
    check(count($legacyFiles)===15,'legacy baseline comprises migrations 001..015');
    foreach($legacyFiles as $src) {
        if(!copy($src,$legacyDir.'/'.basename($src))) throw new RuntimeException('Cannot stage legacy migration');
    }
    check(count($migrationRunner->run($legacyDir))===15,'legacy schema applied without public onboarding tables');
    $legacyClock=new FrozenClock(50);
    $legacyPack=\Aeternum\Core\GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
    $legacyRules=$legacyPack->rules();
    $legacyActor=(new \Aeternum\Core\PdoIdentity($pdo))->register('migration-old-player','legacy account long-lived password');
    $legacyWorld='m01-legacy-world';
    (new \Aeternum\Core\PlayerProvisioner($pdo,$legacyClock))->provision($legacyWorld,$legacyActor,$legacyPack);
    $legacyScheduler=new PdoScheduler($pdo,$legacyWorld,$legacyClock,$legacyRules);
    $legacyStock=[];
    foreach($legacyPack->resources as $resource) {
        $legacyStock[$resource->id]=$legacyScheduler->balanceForActor($legacyActor,$legacyActor,$resource->id,$legacyRules->commands)->stock;
    }
    $legacyJournalEntries=$legacyScheduler->verifyReplay($legacyRules->commands,$legacyRules->events)->entries;
    check($legacyJournalEntries===count($legacyPack->resources),'nonempty pre-upgrade journal replays');
    // Insert an escrow offer into a real world while the database contains
    // ONLY migrations 001..015. Migration 020 must preserve it verbatim.
    $historicOffer=['offer'=>'legacy-m03-offer','seller'=>$legacyActor,
        'giveResource'=>'wood','giveUnits'=>1,'wantResource'=>'grain','wantUnits'=>1];
    $legacyScheduler->executeForActor($legacyActor,new \Aeternum\Core\Command($legacyWorld,
        'legacy-market-create','market.create',$historicOffer),$legacyRules->commands);
    $legacyStock['wood']=$legacyScheduler->balanceForActor($legacyActor,$legacyActor,
        'wood',$legacyRules->commands)->stock;
    $legacyJournalEntries++;
    check(array_column($legacyScheduler->marketForActor($legacyActor)['offers'],'id')===['legacy-m03-offer'],
        'nonempty legacy scalar market works without relational tables');
    $legacyToken=bin2hex(random_bytes(32));
    $legacySession=$pdo->prepare('INSERT INTO engine_sessions(token_hash,actor_id,expires_at) VALUES (?,?,?)');
    $legacySession->execute([hash('sha256',$legacyToken),$legacyActor,1000]);
    $newMigrations=count($allFiles)-count($legacyFiles);
    check(count($migrationRunner->run($migrationPath))===$newMigrations,'M01 schema migrations applied on populated legacy world');
    check($migrationRunner->run($migrationPath)===[],'migration rerun is no-op');
    $legacyAfter=new PdoScheduler($pdo,$legacyWorld,$legacyClock,$legacyRules);
    foreach($legacyPack->resources as $resource) {
        check($legacyAfter->balanceForActor($legacyActor,$legacyActor,$resource->id,$legacyRules->commands)->stock===$legacyStock[$resource->id],
            'upgrade preserved legacy world stock '.$resource->id);
    }
    check($legacyAfter->verifyReplay($legacyRules->commands,$legacyRules->events)->entries===$legacyJournalEntries,
        'migration retained preexisting deterministic journal and rules');
    $relationalOffer=$pdo->prepare('SELECT status,give_units FROM engine_market_offers WHERE world_id=? AND offer_id=?');
    $relationalOffer->execute([$legacyWorld,'legacy-m03-offer']);
    $record=$relationalOffer->fetch(PDO::FETCH_ASSOC);
    check($record!==false && $record['status']==='open' && (int)$record['give_units']===1,
        'M03 lazy transactional backfill preserves an open escrow on a populated legacy world');
    $relationalStock=$pdo->prepare('SELECT stock_units FROM engine_economy_balances WHERE world_id=? AND account_id=? AND resource_id=?');
    $relationalStock->execute([$legacyWorld,$legacyActor,'wood']);
    check((int)$relationalStock->fetchColumn()===$legacyStock['wood'],
        'M03 legacy stock backfills to indexed balances without losing a unit');
    $version=$pdo->prepare('SELECT economy_projection_version FROM engine_worlds WHERE world_id=?');
    $version->execute([$legacyWorld]);
    check((int)$version->fetchColumn()===1,'M03 migration backfill commits its version marker');
    check(array_column($legacyAfter->marketForActor($legacyActor)['offers'],'id')===['legacy-m03-offer'],
        'M03 new SQL market preserves historical listing and authorization');
    check($legacyAfter->verifyReplay($legacyRules->commands,$legacyRules->events)->entries===$legacyJournalEntries,
        'M03 migrated nonempty world still replays all legacy actions and offer receipts');
    $legacyAuth=new \Aeternum\Core\PdoSessions($pdo,$legacyClock);
    check($legacyAuth->resolve($legacyToken)===$legacyActor,'preexisting legacy session remains valid after upgrade');
    check($legacyAuth->login('migration-old-player','legacy account long-lived password')!==null,
        'existing account can log in after onboarding migrations');
    $s=$pdo->prepare('SELECT COUNT(*) FROM engine_onboarding WHERE actor_id=?');
    $s->execute([$legacyActor]);
    check((int)$s->fetchColumn()===0,'upgrade never silently enrolls existing operator account');
} finally {
    foreach(glob($legacyDir.'/*.sql') ?: [] as $staged) unlink($staged);
    rmdir($legacyDir);
}
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
require __DIR__ . "/m02-registry-db.php";
require __DIR__ . "/m14-actor-pack-db.php";
require __DIR__ . "/m14-events-db.php";
require __DIR__ . "/actors.php";
require __DIR__ . "/entities.php";
require __DIR__ . "/m04-territories.php";
require __DIR__ . "/identity.php";
require __DIR__ . "/sessions.php";
require __DIR__ . "/session-cleanup.php";
require __DIR__ . "/http-api.php";
require __DIR__ . "/m04-map-http.php";
require __DIR__ . "/m04-public-map.php";
require __DIR__ . "/m04-buildings.php";
require __DIR__ . "/m04-building-http.php";
require __DIR__ . "/m04-construction.php";
require __DIR__ . "/m04-player-placement.php";
require __DIR__ . "/m04-placement-race.php";
require __DIR__ . "/m04-map-cli.php";
require __DIR__ . "/m04-building-cli.php";
require __DIR__ . "/m04-construction-race.php";
require __DIR__ . "/rate-limits.php";
require __DIR__ . "/auth-http.php";
require __DIR__ . "/auth-global.php";
require __DIR__ . "/economy-http.php";
require __DIR__ . "/provisioning.php";
require __DIR__ . "/m05-colony-onboarding.php";
require __DIR__ . "/m05-full-gameplay.php";
require __DIR__ . "/m06-full-gameplay.php";
require __DIR__ . "/onboarding.php";
require __DIR__ . "/recovery.php";
require __DIR__ . "/password-change.php";
require __DIR__ . "/invitations.php";
require __DIR__ . "/email-activation.php";
require __DIR__ . "/email-http.php";
require __DIR__ . "/email-activation-race.php";
require __DIR__ . "/invite-cli.php";
require __DIR__ . "/world-worker.php";
require __DIR__.'/player-discovery.php';
require __DIR__ . "/market-http.php";
require __DIR__ . "/m09-social.php";
require __DIR__ . "/m09-messages.php";
require __DIR__ . "/m09-pagination.php";
require __DIR__ . "/m09-social-pages.php";
require __DIR__ . "/m09-moderator-pages.php";
require __DIR__ . "/m09-governance.php";
require __DIR__ . "/m09-volume.php";
require __DIR__ . "/m10-admin-premium.php";
require __DIR__ . "/m10-admin-cli.php";
require __DIR__ . "/m10-parallel.php";
require __DIR__ . "/rate-cleanup.php";
require __DIR__ . "/maintenance-cli.php";
require __DIR__ . "/onboarding-status.php";
require __DIR__ . "/market.php";
require __DIR__ . "/m03-projection-db.php";
require __DIR__ . "/m03-scale-db.php";
require __DIR__ . "/m03-claim-race.php";
require __DIR__ . "/m03-parallel-writes.php";
require __DIR__ . "/m03-expiry-batch.php";
require __DIR__ . "/m03-cli.php";
// Run the high-watermark authenticated hash stress AFTER all lower fixed-clock
// recovery tests so a deliberate simulated future cannot exhaust their quota.
require __DIR__ . "/auth-capacity.php";
require __DIR__ . "/m11-operations.php";
require __DIR__ . "/m12-capacity.php";
require __DIR__ . "/m13-capacity.php";
echo "MariaDB integration checks passed.\n";
