<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoWorldPoliticsApi.php';
use Aeternum\WorldPolitics\{PoliticalRoster,PdoPoliticalStore,PdoWorldPoliticsApi,PoliticsAuthorizer};

function wp4(bool $value,string $name):void {
    if(!$value) throw new RuntimeException($name);
    echo "PASS WP04: $name\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES' || !str_contains(getenv('WP_TEST_DSN') ?: '','wp_test')) {
    fwrite(STDERR,"Isolated WP test database required\n");exit(2);
}
$pdo=new PDO(
    getenv('WP_TEST_DSN'),
    getenv('WP_TEST_USER'),
    getenv('WP_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]
);
foreach(['wp_command_receipts','wp_world_events','wp_world_rosters'] as $name) $pdo->exec('DROP TABLE IF EXISTS '.$name);
$sql=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(__DIR__.'/../games/world-politics/database/001_rosters.sql'));
foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') $pdo->exec(trim($statement));

$auth=new class implements PoliticsAuthorizer {
    public bool $allowed=true;
    public function actorId():string{return 'player-verified-by-server';}
    public function allows(string $world,string $country,string $ability):bool{
        return $this->allowed && $world==='scenario-a' && $country==='FR';
    }
};
$store=new PdoPoliticalStore($pdo,$auth);
$store->seed(new PoliticalRoster('scenario-a','FR',['prime_minister','defence']));
$store->seed(new PoliticalRoster('scenario-b','FR',['prime_minister']));
$api=new PdoWorldPoliticsApi($store);
$base='/api/v1/worlds/scenario-a/countries/FR/politics';
$get=$api->handle('GET',$base);
wp4($get['status']===200 && isset($get['headers']['ETag']),'REST GET uses persistent world snapshot');
wp4($api->handle('GET',$base.'/person-template')['body']['schema_version']==='1.0.0','REST GET exposes two-mode form schema');
wp4($api->handle('GET','/api/v1/worlds/scenario-b/countries/FR/politics')['status']===404,'REST conceals unauthorized worlds');
wp4($api->handle('POST',$base.'/people',['id'=>'fictional','name'=>'Candidate'])['status']===428,'writes require two preconditions');

$headers=['idempotency-key'=>'create-character-0001','if-match'=>$get['headers']['ETag']];
$create=$api->handle('POST',$base.'/people',['id'=>'fictional','name'=>'Candidate','mode'=>'quick'],$headers);
wp4($create['status']===201,'quick fictional candidate persisted through REST');
$minimal=$api->handle('POST',$base.'/people',['name'=>'Nom seulement'],[
    'Idempotency-Key'=>'create-minimal-00001','If-Match'=>$create['headers']['ETag']
]);
wp4($minimal['status']===201 && preg_match('/^f-[a-f0-9]{32}$/D',$minimal['body']['person_id'])===1,'name-only creation generates stable identifier server-side');
$minimalRepeat=$api->handle('POST',$base.'/people',['name'=>'Nom seulement'],[
    'Idempotency-Key'=>'create-minimal-00001','If-Match'=>'"stale"'
]);
wp4($minimalRepeat===$minimal,'name-only submission is retry-stable after database write');
$repeat=$api->handle('POST',$base.'/people',['id'=>'fictional','name'=>'Candidate','mode'=>'quick'],[
    'Idempotency-Key'=>'create-character-0001','If-Match'=>'"stale"'
]);
wp4($repeat===$create,'REST returned durable receipt despite stale retry');
wp4($api->handle('GET',$base.'/people/fictional')['body']['person']['avatar']==='generic-v1','GET individual person uses generic portrait key');
wp4($api->handle('GET',$base.'/people/unknown')['status']===404,'unknown character is not found');

$start=$api->handle('POST',$base.'/head-of-state',['person_id'=>'fictional'],[
    'Idempotency-Key'=>'new-president-0001',
    'If-Match'=>$minimal['headers']['ETag']
]);
wp4($start['status']===200,'alternate presidency initialized');
$nominate=$api->handle('POST',$base.'/nominations',['office'=>'prime_minister','person_id'=>'fictional'],[
    'Idempotency-Key'=>'nomination-pm-0001',
    'If-Match'=>$start['headers']['ETag']
]);
wp4($nominate['status']===202,'nomination remains pending');
$accept=$api->handle('POST',$base.'/nominations/prime_minister/resolution',['accepted'=>true],[
    'Idempotency-Key'=>'accept-pm-0000001',
    'If-Match'=>$nominate['headers']['ETag']
]);
wp4($accept['status']===200,'nomination accepted over durable REST adapter');
wp4($api->handle('GET',$base)['body']['cabinet']['prime_minister']==='fictional','cabinet change read from database');

$stale=$api->handle('POST',$base.'/nominations',['office'=>'defence','person_id'=>'fictional'],[
    'Idempotency-Key'=>'nomination-def-0001','If-Match'=>$start['headers']['ETag']
]);
wp4($stale['status']===412 && $stale['body']['code']==='stale_precondition','optimistic writes require a current ETag');
$auth->allowed=false;
wp4($api->handle('GET',$base)['status']===404,'revoked principal cannot read world');
wp4($api->handle('POST',$base.'/people',['id'=>'fictional','name'=>'Candidate','mode'=>'quick'],$headers)['status']===403,'revoked principal cannot replay receipt');
$count=(int)$pdo->query("SELECT COUNT(*) FROM wp_world_events WHERE world_id='scenario-a'")->fetchColumn();
wp4($count===5,'only successful commands produced durable journal events');

echo "World Politics persistent REST contract passed.\n";
