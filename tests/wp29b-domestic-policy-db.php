<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoDomesticPolicyStore.php';

use Aeternum\WorldPolitics\{PdoDomesticPolicyStore,PersistentDomesticPolicyAuthority,WorldMutationFence};

if (getenv('WP_TEST_ISOLATED')!=='YES' || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP29B requires isolated wp_test database\n");exit(2);
}
function wp29b(bool $ok,string $label):void {
    if (!$ok) throw new RuntimeException('FAIL WP29B: '.$label);
    echo "PASS WP29B: $label\n";
}
function reject29b(callable $fn,string $label,string $class=DomainException::class):void {
    try {$fn();} catch (Throwable $e) {
        wp29b($e instanceof $class,$label.' ('.get_class($e).')');
        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
$connect=static fn():PDO=>new PDO(getenv('WP_TEST_DSN'),getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
// This test runs FIRST in the isolated MariaDB job; no previous test owns these tables.
$tables=['wp_domestic_receipts','wp_domestic_journal','wp_domestic_states',
    'wp_world_mutation_fences','wp_world_clock_steps','wp_world_clock'];
foreach($tables as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach(['007_world_clock.sql','013_world_mutation_fences.sql','017_wp29b_domestic_policy.sql'] as $file) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(__DIR__.'/../games/world-politics/database/'.$file));
    foreach(explode(';',$sql) as $stmt) if (trim($stmt)!=='') $pdo->exec(trim($stmt));
}
$principal=new class implements PersistentDomesticPolicyAuthority {
    public string $actor='verified-policy-worker';
    public bool $allowed=true;
    public bool $legal=false;
    public string $day='2026-11-25';
    public function actorId():string{return $this->actor;}
    public function allows(string $world,string $ability):bool {
        return $this->allowed && in_array($world,['domestic-a','domestic-b','domestic-coordinated'],true)
            && in_array($ability,['domestic.admin','domestic.read','domestic.propose','domestic.enact','domestic.clock'],true);
    }
    public function enactedInstrument(string $world,string $policy):?string {
        return $this->legal?str_repeat('a',64):null;
    }
    public function clockDay(string $world):string{return $this->day;}
};
$config=['start_day'=>'2026-11-24','currency'=>'EUR','opening_balance'=>1000,
    'baseline_revenue'=>100,'baseline_expense'=>90,'cohorts'=>['workers'=>400],
    'catalog'=>['reform'=>['revenue_delta'=>20,'expense_delta'=>5,'pressure_delta'=>['workers'=>200],'duration_days'=>2]]];
$store=new PdoDomesticPolicyStore($pdo,$principal);
$store->seed('domestic-a',$config);$store->seed('domestic-b',$config);
$a=$store->read('domestic-a');$b=$store->read('domestic-b');
wp29b($a['revision']===0 && $a['state']['balance']===1000,'seed and pinned baseline');
reject29b(fn()=>$store->read('unauthorized-world'),'cross-world access rejected');
reject29b(fn()=>$store->execute('domestic-a','propose',['policy_id'=>'reform','effective_day'=>'2026-11-26','revenue_delta'=>999],
    'invalid-command-0001',$a['etag']),'client coefficients rejected',InvalidArgumentException::class);
$proposal=['policy_id'=>'reform','effective_day'=>'2026-11-26'];
$p=$store->execute('domestic-a','propose',$proposal,'proposal-key-0001',$a['etag']);
wp29b($p['revision']===1 && $p['status']===202,'durable proposal');
$reopened=new PdoDomesticPolicyStore($connect(),$principal);
wp29b($reopened->execute('domestic-a','propose',array_reverse($proposal,true),'proposal-key-0001',$a['etag'])===$p,
    'reconnect and canonical request replay with stale ETag');
reject29b(fn()=>$reopened->execute('domestic-a','propose',['policy_id'=>'reform','effective_day'=>'2026-11-27'],
    'proposal-key-0001',$p['etag']),'conflicting key rejected');
$principal->actor='another-worker';
reject29b(fn()=>$reopened->execute('domestic-a','propose',$proposal,'proposal-key-0001',$a['etag']),'receipt scoped to actor');
$principal->actor='verified-policy-worker';
reject29b(fn()=>$store->execute('domestic-a','enact',['policy_id'=>'reform'],'enact-key-000001',$p['etag']),'no legal evidence');
$principal->legal=true;
reject29b(fn()=>$store->execute('domestic-a','enact',['policy_id'=>'reform'],'enact-key-000001',$a['etag']),'stale ETag rejected');
$e=$store->execute('domestic-a','enact',['policy_id'=>'reform'],'enact-key-000001',$p['etag']);
wp29b($e['revision']===2 && $e['result']['policy']['instrument_sha']===str_repeat('a',64),'proof pinned in persistent state');
wp29b($store->read('domestic-b')===$b,'worlds remain isolated');
$principal->allowed=false;
reject29b(fn()=>$store->execute('domestic-a','enact',['policy_id'=>'reform'],'enact-key-000001',$p['etag']),'revoked actor cannot replay');
$principal->allowed=true;
// Fail after updating state and writing journal, at receipt insertion.
$pdo->exec("CREATE TRIGGER wp29b_inject_failure BEFORE INSERT ON wp_domestic_receipts
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='WP29B injected receipt failure'");
try {
    reject29b(fn()=>$store->execute('domestic-a','clock',['target_day'=>'2026-11-25'],'clock-key-000001',$e['etag']),
        'injected database failure',PDOException::class);
} finally {$pdo->exec('DROP TRIGGER wp29b_inject_failure');}
wp29b($store->read('domestic-a')['revision']===2
    && (int)$pdo->query("SELECT COUNT(*) FROM wp_domestic_journal WHERE world_id='domestic-a'")->fetchColumn()===2
    && (int)$pdo->query("SELECT COUNT(*) FROM wp_domestic_receipts WHERE world_id='domestic-a'")->fetchColumn()===2,
    'state, journal and receipt rolled back together');
$d=$reopened->execute('domestic-a','clock',['target_day'=>'2026-11-25'],'clock-key-000001',$e['etag']);
wp29b($d['result']['closing_balance']===1010,'retry commits once');
$principal->day='2026-11-26';
$d2=$store->execute('domestic-a','clock',['target_day'=>'2026-11-26'],'clock-key-000002',$d['etag']);
wp29b($d2['result']['closing_balance']===1035 && $d2['result']['pressure']['workers']===450,
    'daily effects and replay survive database restart');
wp29b($reopened->execute('domestic-a','clock',['target_day'=>'2026-11-25'],'clock-key-000001',$e['etag'])===$d,
    'previous daily receipt remains stable after subsequent day');
$journal=$pdo->query("SELECT revision,before_sha,after_sha FROM wp_domestic_journal WHERE world_id='domestic-a' ORDER BY revision")->fetchAll(PDO::FETCH_ASSOC);
wp29b(count($journal)===4 && $journal[0]['before_sha']===trim($a['etag'],'"')
    && $journal[3]['after_sha']===trim($d2['etag'],'"')
    && $journal[0]['after_sha']===$journal[1]['before_sha']
    && $journal[1]['after_sha']===$journal[2]['before_sha']
    && $journal[2]['after_sha']===$journal[3]['before_sha'],'continuous before/after journal chain');
// Clock-managed worlds are deliberately excluded until coordinator integration.
$pdo->beginTransaction();
$pdo->exec("INSERT INTO wp_world_clock(world_id,committed_day) VALUES('domestic-coordinated','2026-11-24')");
WorldMutationFence::seed($pdo,'domestic-coordinated');
$pdo->commit();
reject29b(fn()=>$store->seed('domestic-coordinated',$config),'no uncoordinated domestic bootstrap');
$pdo->beginTransaction();
$f=WorldMutationFence::lockCoordinator($pdo,'domestic-coordinated');
$key=str_repeat('b',64);
$pdo->exec("UPDATE wp_world_clock SET pending_day='2026-11-25',pending_key='$key' WHERE world_id='domestic-coordinated'");
WorldMutationFence::begin($pdo,'domestic-coordinated',$f,$key,'2026-11-25',$principal->actorId());
$pdo->commit();
reject29b(fn()=>$store->seed('domestic-coordinated',$config),'active world fence blocks mutations');
$pdo->exec("UPDATE wp_domestic_states SET assumptions_sha=REPEAT('0',64) WHERE world_id='domestic-b'");
reject29b(fn()=>$store->read('domestic-b'),'corrupt assumptions fail closed',UnexpectedValueException::class);
$pdo->exec("UPDATE wp_domestic_states SET state_sha=REPEAT('0',64) WHERE world_id='domestic-a'");
reject29b(fn()=>$reopened->read('domestic-a'),'corrupt snapshot fails closed');
foreach($tables as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
echo "WP29B durable domestic policy acceptance passed.\n";
