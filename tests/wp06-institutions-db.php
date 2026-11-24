<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoInstitutionalStore.php';
require_once __DIR__.'/../games/world-politics/src/PoliticalRoster.php';
use Aeternum\WorldPolitics\{
  PoliticalRoster, InstitutionalSimulation, PdoInstitutionalStore, InstitutionalActorAuthorizer
};

function wp6(bool $ok,string $name):void {
    if (!$ok) throw new RuntimeException($name);
    echo "PASS WP06: $name\n";
}
function wp6reject(callable $fn,string $name):void {
    try { $fn(); }
    catch(\DomainException|\InvalidArgumentException|\UnexpectedValueException $e) {
        wp6(true,$name);return;
    }
    throw new RuntimeException('Expected rejected: '.$name);
}
if (getenv('WP_TEST_ISOLATED')!=='YES' || !str_contains(getenv('WP_TEST_DSN') ?: '','wp_test')) {
    fwrite(STDERR,"Only dedicated wp_test database is allowed\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');
$pdo=new PDO($dsn,getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
foreach([
    'wp_institutional_journal','wp_institutional_receipts','wp_institutional_states',
    'wp_command_receipts','wp_world_events','wp_world_rosters'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach(['001_rosters.sql','002_institutions.sql'] as $migration) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$migration));
    foreach(explode(';',$sql) as $statement) {
        if (trim($statement)!=='') $pdo->exec(trim($statement));
    }
}
$auth=new class implements InstitutionalActorAuthorizer {
    public string $actor='human-france';
    public string $role='prime_minister';
    public array $countries=['FR'=>true,'US'=>true,'DE'=>true];
    public bool $revoked=false;
    public function actorId():string{return $this->actor;}
    public function allows(string $world,string $country,string $action):bool {
        return !$this->revoked && $world==='world-legal' && isset($this->countries[$country]);
    }
    public function roleFor(string $world,string $country,string $action):string {
        return $this->role;
    }
};
$store=new PdoInstitutionalStore($pdo,$auth);
foreach(['FR','DE','US'] as $code) {
    $roster=new PoliticalRoster('world-legal',$code,['head_of_government']);
    $pdo->prepare('INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES (?,?,?,?,0)')
       ->execute(['world-legal',$code,json_encode($roster->snapshot(),JSON_THROW_ON_ERROR),$roster->snapshotHash()]);
    $store->seed(new InstitutionalSimulation('world-legal',$code,'2026-11-24'));
}
$start=$store->read('world-legal','FR');
wp6($start['revision']===0 && $start['state']['ruleset']==='fr-1958-wp05-0.1','country-specific policy pinned to persisted world');
$bill=$store->decide('world-legal','FR','submit_bill',['title'=>'Bill with parliament stage'],
    'first-bill-000001',$start['etag']);
wp6($bill['status']===202 && $bill['revision']===1,'French prime minister submits a bill transactionally');
$reconnect=new PDO($dsn,getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
$store2=new PdoInstitutionalStore($reconnect,$auth);
wp6(isset($store2->read('world-legal','FR')['state']['bills'][$bill['result']['bill_id']]),
    'bill survives fresh database connection');
$retry=$store2->decide('world-legal','FR','submit_bill',['title'=>'Bill with parliament stage'],
    'first-bill-000001','"bad"');
wp6($retry===$bill,'durable receipts allow exactly-once retry after reconnect');
wp6reject(fn()=> $store->decide('world-legal','FR','submit_bill',['title'=>'Conflicting bill'],
    'first-bill-000001',$bill['etag']),'same key with changed payload rejected');

$auth->role='president';
wp6reject(fn()=> $store->decide('world-legal','FR','dissolve_lower_house',
    ['consultations_recorded'=>false],'attempt-dissolve-01',$bill['etag']),
    'France: mandatory consultation gate cannot be bypassed');
$diss=$store->decide('world-legal','FR','dissolve_lower_house',
    ['consultations_recorded'=>true],'valid-dissolve-01',$bill['etag']);
wp6($diss['result']['election_earliest']==='2026-12-14'
    && $diss['result']['election_deadline']==='2027-01-03','constitutional election window persisted with dissolution');

$clock=$store->advance('world-legal','FR','2026-12-16',
    'clock-to-dec-16-01',$diss['etag']);
wp6($clock['status']===200 && $store->read('world-legal','FR')['state']['election']['status']==='voting_window_open',
    'trusted clock executes due election agenda under lock');

$auth->role='election_authority';
$poll=$store->decide('world-legal','FR','hold_election',[],
    'record-election-001',$clock['etag']);
wp6($poll['result']['status']==='voting_recorded_results_pending',
    'election authority records vote; no fabricated parliamentary outcome');
$clock2=$store->advance('world-legal','FR','2027-01-04',
    'clock-after-deadline-001',$poll['etag']);
wp6($store->read('world-legal','FR')['state']['constitutional_breaches']===0,
    'on-time election prevents deadline crisis');
$auth->role='president';
wp6reject(fn()=> $store->decide('world-legal','US','dissolve_lower_house',
    ['consultations_recorded'=>true],'us-dissolve-0001',
    $store->read('world-legal','US')['etag']),
    'American president cannot invoke French dissolution');
$auth->role='federal_president';
wp6reject(fn()=> $store->decide('world-legal','DE','dissolve_lower_house',
    ['consultations_recorded'=>true],'de-dissolve-0001',
    $store->read('world-legal','DE')['etag']),
    'German dissolution without a modeled conditional trigger fails closed');
wp6($store->read('world-legal','DE')['revision']===0
    && $store->read('world-legal','US')['revision']===0,'failed commands leave other constitutional worlds unchanged');

$logCount=(int)$pdo->query("SELECT COUNT(*) FROM wp_institutional_journal WHERE world_id='world-legal' AND country_code='FR'")->fetchColumn();
$receiptCount=(int)$pdo->query("SELECT COUNT(*) FROM wp_institutional_receipts WHERE world_id='world-legal' AND country_code='FR'")->fetchColumn();
wp6($logCount===5 && $receiptCount===5,'event journal and durable receipts have exactly five committed operations');
$auth->revoked=true;
wp6reject(fn()=> $store->read('world-legal','FR'),'revoked principal cannot read institutions');
wp6reject(fn()=> $store->decide('world-legal','FR','submit_bill',['title'=>'Bill with parliament stage'],
    'first-bill-000001','"bad"'),'revoked principal cannot replay stored receipts');
$auth->revoked=false;
$pdo->exec("UPDATE wp_institutional_states SET state_sha=REPEAT('0',64) WHERE world_id='world-legal' AND country_code='US'");
wp6reject(fn()=> $store->read('world-legal','US'),'tampered institutional snapshot detected');
echo "WP06 persistent constitutional worlds passed.\n";
