<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoPoliticalStore.php';
use Aeternum\WorldPolitics\{PoliticalRoster,PdoPoliticalStore,PoliticsAuthorizer};

function assertWpDb(bool $value,string $description):void {
    if (!$value) throw new RuntimeException($description);
    echo "PASS WP03: $description\n";
}
function rejectWpDb(callable $call,string $description):void {
    try {$call();} catch (\DomainException|\InvalidArgumentException|\UnexpectedValueException $e) {
        assertWpDb(true,$description);return;
    }
    throw new RuntimeException('Expected rejection: '.$description);
}
if (getenv('WP_TEST_ISOLATED')!=='YES') {
    fwrite(STDERR,"Refuse non-isolated database: WP_TEST_ISOLATED=YES required\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN') ?: '';
if (!str_contains($dsn,'wp_test')) {
    fwrite(STDERR,"Refuse database DSN lacking wp_test\n");exit(2);
}
$user=getenv('WP_TEST_USER') ?: '';
$password=getenv('WP_TEST_PASSWORD') ?: '';
$pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach(['wp_command_receipts','wp_world_events','wp_world_rosters'] as $name) $pdo->exec('DROP TABLE IF EXISTS '.$name);
foreach(explode(';',file_get_contents(__DIR__.'/../games/world-politics/database/001_rosters.sql')) as $statement) {
    if (trim($statement)!=='') $pdo->exec(trim($statement));
}
$auth=new class implements PoliticsAuthorizer {
    public string $actor='person-a';
    public array $permitted=['world-a'=>true];
    public function actorId():string{return $this->actor;}
    public function allows(string $world,string $country,string $ability):bool {
        return $country==='FR' && isset($this->permitted[$world]);
    }
};
$repository=new PdoPoliticalStore($pdo,$auth);
$original=new PoliticalRoster('world-a','FR',['prime_minister','economy']);
$original->addPerson([
    'id'=>'sourced-public-official','kind'=>'real','name'=>'Synthetic official test fixture',
    'country'=>'FR','biography'=>'Synthetic fixture only, not a real biography.',
    'as_of'=>'2026-11-24','sources'=>['https://example.org/biography'],
    'programme'=>[]
]);
$repository->seed($original);
$repository->seed(new PoliticalRoster('world-b','FR',['prime_minister','economy']));
$read=$repository->read('world-a','FR');
assertWpDb($read['revision']===0 && isset($read['state']['people']['sourced-public-official']),'trusted sourced fixture seeded');
rejectWpDb(fn()=> $repository->read('world-b','FR'),'forbidden world hidden by authorizer');

$quick=['id'=>'custom-president','name'=>'Personnage créé rapidement','mode'=>'quick'];
$create=$repository->execute('world-a','FR','create_fictional_person',$quick,'create-person-00001',$read['etag']);
assertWpDb($create['status']===201 && $create['revision']===1,'quick character created and revision persisted');
$reconnected=new PdoPoliticalStore(new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),$auth);
assertWpDb(isset($reconnected->read('world-a','FR')['state']['people']['custom-president']),'state survives fresh database connection');

$repeat=$reconnected->execute('world-a','FR','create_fictional_person',$quick,'create-person-00001','"bad"');
assertWpDb($repeat===$create,'receipt survives process restart and stale retry');
$changed=$quick;$changed['name']='Différent';
rejectWpDb(fn()=> $repository->execute('world-a','FR','create_fictional_person',$changed,'create-person-00001',$create['etag']),'same key with conflicting payload rejected');
rejectWpDb(fn()=> $repository->execute('world-a','FR','create_fictional_person',['id'=>'bad','name'=>'Bad'], 'create-person-00002','"wrong"'),'optimistic stale revision rejected');

$head=$repository->execute('world-a','FR','initialize_scenario',['person_id'=>'custom-president'],'head-of-state-0001',$create['etag']);
$nomination=$repository->execute('world-a','FR','nominate',[
    'office'=>'prime_minister','person_id'=>'sourced-public-official'
],'nomination-00000001',$head['etag']);
assertWpDb($nomination['status']===202 && $repository->read('world-a','FR')['state']['cabinet']===[],'real and fictional characters coexist; nomination pending');
$resolved=$repository->execute('world-a','FR','resolve_nomination',[
    'office'=>'prime_minister','accepted'=>true
],'resolution-0000001',$nomination['etag']);
assertWpDb($repository->read('world-a','FR')['state']['cabinet']['prime_minister']==='sourced-public-official','accepted proposal enters cabinet');
$revision=$resolved['revision'];
rejectWpDb(fn()=> $repository->execute('world-a','FR','nominate',[
    'office'=>'undeclared','person_id'=>'custom-president'
],'invalid-office-0001',$resolved['etag']),'invalid office fails without write');
assertWpDb($repository->read('world-a','FR')['revision']===$revision,'failure did not advance revision');

$events=(int)$pdo->query("SELECT COUNT(*) FROM wp_world_events WHERE world_id='world-a'")->fetchColumn();
$receipts=(int)$pdo->query("SELECT COUNT(*) FROM wp_command_receipts WHERE world_id='world-a'")->fetchColumn();
assertWpDb($events===4 && $receipts===4,'atomic append-only events and receipts have no duplicates');
$auth->permitted=[];
rejectWpDb(fn()=> $repository->execute('world-a','FR','resolve_nomination',[
    'office'=>'prime_minister','accepted'=>true
],'resolution-0000001','"any"'),'revoked authorizer cannot replay a previous accepted receipt');
$auth->permitted=['world-a'=>true];
$pdo->exec("UPDATE wp_world_rosters SET state_sha=REPEAT('0',64) WHERE world_id='world-b'");
$auth->permitted['world-b']=true;
rejectWpDb(fn()=> $repository->read('world-b','FR'),'tampered persisted SHA-256 detected');

echo "World Politics durable MariaDB backend tests passed.\n";
