<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/WorldMutationFence.php';

use Aeternum\WorldPolitics\WorldMutationFence;

function wp27(bool $condition,string $description):void
{
    if (!$condition) throw new RuntimeException('FAILED WP27: '.$description);
    echo "PASS WP27: ".$description."\n";
}
function wp27Reject(callable $operation,string $description):void
{
    try { $operation(); }
    catch (\DomainException|\LogicException|\InvalidArgumentException|\UnexpectedValueException $error) {
        wp27(true,$description);
        return;
    }
    throw new RuntimeException('Expected WP27 rejection: '.$description);
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP27 must run only against isolated wp_test MariaDB\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$user=getenv('WP_TEST_USER');$password=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach ([
    'wp_world_mutation_fences','wp_world_clock_steps','wp_world_clock'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach (['007_world_clock.sql','013_world_mutation_fences.sql'] as $file) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$file)
    );
    foreach (explode(';',$sql) as $statement) {
        if (trim($statement)!=='') $pdo->exec(trim($statement));
    }
}
$world='world-one';$other='world-two';
foreach ([$world,$other] as $name) {
    $pdo->prepare(
        'INSERT INTO wp_world_clock(world_id,committed_day,revision) VALUES(?,?,0)'
    )->execute([$name,'2026-11-24']);
}
$pdo->beginTransaction();
wp27Reject(fn()=>WorldMutationFence::admit(
    $pdo,$world,'authenticated-player','nominate'
),'clock-enabled world without a fence fails closed rather than assuming unlocked');
$pdo->rollBack();

$pdo->beginTransaction();
WorldMutationFence::seed($pdo,$world);
WorldMutationFence::seed($pdo,$other);
$pdo->commit();
$another=$connect();
$another->beginTransaction();
WorldMutationFence::admit(
    $another,$other,'human-opposition','nominate'
);
$another->commit();
wp27(true,'unrelated world is admitted independently');

$player=$connect();
$player->beginTransaction();
WorldMutationFence::admit($player,$world,'human-minister','nominate');
$competing=$connect();
$competing->exec('SET SESSION innodb_lock_wait_timeout = 1');
$competing->beginTransaction();
$blocked=false;
try {
    WorldMutationFence::lockCoordinator($competing,$world);
} catch (\PDOException $e) {
    $blocked=in_array((int)($e->errorInfo[1]??0),[1205,1213],true);
}
if ($competing->inTransaction()) $competing->rollBack();
wp27($blocked,'a pending player transaction actually holds the fence lock against coordinator takeover');
$player->commit();

$key=hash('sha256','trusted-tick-2026-11-25');
$wrongKey=hash('sha256','other-unauthorized-tick');
$service='verified-world-scheduler';
$start=$pdo->query(
    "SELECT generation FROM wp_world_mutation_fences WHERE world_id='world-one'"
)->fetchColumn();

$pdo->beginTransaction();
$locked=WorldMutationFence::lockCoordinator($pdo,$world);
WorldMutationFence::begin(
    $pdo,$world,$locked,$key,'2026-11-25',$service
);
$pdo->rollBack();
wp27($pdo->query(
    "SELECT state FROM wp_world_mutation_fences WHERE world_id='world-one'"
)->fetchColumn()==='idle','rolled-back world transition cannot leave phantom write fence');

$pdo->beginTransaction();
$locked=WorldMutationFence::lockCoordinator($pdo,$world);
WorldMutationFence::begin(
    $pdo,$world,$locked,$key,'2026-11-25',$service
);
$pdo->prepare(
    "UPDATE wp_world_clock SET pending_day=?,pending_key=? WHERE world_id=?"
)->execute(['2026-11-25',$key,$world]);
$pdo->commit();
wp27((int)$pdo->query(
    "SELECT generation FROM wp_world_mutation_fences WHERE world_id='world-one'"
)->fetchColumn()===(int)$start+1,'world transition records a durable generation change');

$reconnect=$connect();
$reconnect->beginTransaction();
wp27Reject(fn()=>WorldMutationFence::admit(
    $reconnect,$world,'human-minister','nominate'
),'ordinary authenticated player command blocked during partial world transition');
wp27Reject(fn()=>WorldMutationFence::admit(
    $reconnect,$world,'autonomous-ai','respond'
),'autonomous diplomacy worker cannot write during pending global tick');
wp27Reject(fn()=>WorldMutationFence::admit(
    $reconnect,$world,$service,'nominate'
),'even trusted clock service cannot use its identity for unrelated cabinet decisions');
wp27Reject(fn()=>WorldMutationFence::admit(
    $reconnect,$world,$service,'clock','2026-11-26'
),'trusted world clock cannot advance to a different day while recovering prior day');
wp27Reject(fn()=>WorldMutationFence::admit(
    $reconnect,$world,'human-minister','clock','2026-11-25'
),'clock-shaped player command cannot borrow scheduler actor identity');
WorldMutationFence::admit($reconnect,$world,$service,'clock','2026-11-25');
wp27(true,'verified scheduler is admitted for exact pending day only');
$reconnect->commit();

$pdo->beginTransaction();
$locked=WorldMutationFence::lockCoordinator($pdo,$world);
WorldMutationFence::begin(
    $pdo,$world,$locked,$key,'2026-11-25',$service
);
wp27Reject(fn()=>WorldMutationFence::begin(
    $pdo,$world,$locked,$wrongKey,'2026-11-25',$service
),'another idempotency key cannot overtake an existing partial world tick');
wp27Reject(fn()=>WorldMutationFence::begin(
    $pdo,$world,$locked,$key,'2026-11-26',$service
),'another target day cannot reuse world lock key');
wp27Reject(fn()=>WorldMutationFence::finish(
    $pdo,$world,$locked,$key,'2026-11-25','human-minister'
),'player cannot release a fence using another actor ID');
wp27Reject(fn()=>WorldMutationFence::finish(
    $pdo,$world,$locked,$wrongKey,'2026-11-25',$service
),'wrong command key cannot clear a suspended world transition');
$pdo->commit();

wp27($pdo->query(
    "SELECT state FROM wp_world_mutation_fences WHERE world_id='world-one'"
)->fetchColumn()==='advancing','crash/restart semantics retain pending fence without TTL or arbitrary expiration');
$pdo->beginTransaction();
$final=WorldMutationFence::lockCoordinator($pdo,$world);
WorldMutationFence::finish(
    $pdo,$world,$final,$key,'2026-11-25',$service
);
$pdo->prepare(
    "UPDATE wp_world_clock SET committed_day=?,pending_day=NULL,pending_key=NULL
     WHERE world_id=?"
)->execute(['2026-11-25',$world]);
$pdo->commit();
$released=$pdo->query(
    "SELECT state,pending_key,controller_id,generation FROM wp_world_mutation_fences
     WHERE world_id='world-one'"
)->fetch(PDO::FETCH_ASSOC);
wp27($released['state']==='idle'
    && $released['pending_key']===null
    && $released['controller_id']===null
    && (int)$released['generation']===(int)$start+2,
    'publishing the committed date releases the same durable fence exactly once');

$pdo->beginTransaction();
WorldMutationFence::admit($pdo,$world,'human-minister','nominate');
WorldMutationFence::admit($pdo,$world,'autonomous-ai','respond');
$pdo->commit();
wp27(true,'cabinet and autonomous workers are admitted again after verified publication');
$pdo->beginTransaction();
wp27Reject(fn()=>WorldMutationFence::finish(
    $pdo,$world,WorldMutationFence::lockCoordinator($pdo,$world),
    $key,'2026-11-25',$service
),'a completed world tick cannot release the fence a second time');
$pdo->rollBack();

$pdo->beginTransaction();
WorldMutationFence::admit($pdo,$other,'human-opposition','nominate');
$pdo->commit();
wp27(true,'independent second world remains writable while first is recovered');

// Simulate a physically corrupted or partially repaired database: the
// authoritative clock says "pending" while the fence incorrectly says idle.
$pdo->prepare(
    'UPDATE wp_world_clock SET pending_day=?,pending_key=? WHERE world_id=?'
)->execute(['2026-11-26',$wrongKey,$world]);
$pdo->beginTransaction();
wp27Reject(fn()=>WorldMutationFence::admit(
    $pdo,$world,'human-minister','nominate'
),'world clock and write fence corruption fails closed instead of admitting player actions');
$pdo->rollBack();
$pdo->prepare(
    'UPDATE wp_world_clock SET pending_day=NULL,pending_key=NULL WHERE world_id=?'
)->execute([$world]);
$pdo->beginTransaction();
WorldMutationFence::admit($pdo,$world,'human-minister','nominate');
$pdo->commit();
wp27(true,'restored coherent world and idle fence admit ordinary actions again');

echo "WP27 concurrency, durable fencing, crash recovery and service isolation passed.\n";
