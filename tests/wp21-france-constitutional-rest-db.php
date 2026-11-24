<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/FrenchConstitutionalApi.php';

use Aeternum\WorldPolitics\{
    FrenchConstitutionalCrisis,FrenchConstitutionalPrincipal,
    PdoFrenchConstitutionalStore,FrenchConstitutionalApi
};

function wp21(bool $ok,string $label):void
{
    if (!$ok) throw new RuntimeException('FAILED WP21: '.$label);
    echo "PASS WP21: ".$label."\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP21 requires isolated MariaDB wp_test\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$dbUser=getenv('WP_TEST_USER');
$dbPassword=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$dbUser,$dbPassword,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach([
    'wp_fr_constitutional_journal','wp_fr_constitutional_receipts',
    'wp_fr_constitutional_states'
] as $name) $pdo->exec('DROP TABLE IF EXISTS '.$name);
$sql=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(
    __DIR__.'/../games/world-politics/database/009_fr_constitutional_49_3.sql'
));
foreach(explode(';',$sql) as $statement) {
    if(trim($statement)!=='') $pdo->exec(trim($statement));
}
$actor=new class implements FrenchConstitutionalPrincipal {
    public string $at='2026-11-24T09:00:00+01:00';
    public bool $allowed=true;
    public bool $council=true;
    public int $signed=58;
    public int $voted=289;
    public function actorId():string{return 'verified-parliamentary-service';}
    public function allows(string $world,string $ability):bool {
        return $world==='fr-rest-world' && $this->allowed
            && in_array($ability,[
                'fr.constitution.read','fr.49_3.engage',
                'fr.censure.file','fr.censure.vote',
                'fr.constitution.clock','fr.constitution.session'
            ],true);
    }
    public function councilDeliberated(string $world,string $bill):bool {
        return $world==='fr-rest-world' && $this->council;
    }
    public function verifiedBillCategory(string $world,string $bill):string {
        return $bill==='ordinary-bill'?'ordinary':'finance';
    }
    public function verifiedSignatures(string $world,string $motion):int {
        return $this->signed;
    }
    public function verifiedVotesFor(string $world,string $motion):int {
        return $this->voted;
    }
    public function verifiedInstant(string $world,string $operation):string {
        return $this->at;
    }
};
$store=new PdoFrenchConstitutionalStore($pdo,$actor);
$store->seed(new FrenchConstitutionalCrisis(
    'fr-rest-world','regular-2026',
    FrenchConstitutionalCrisis::instant($actor->at),577
));
$api=new FrenchConstitutionalApi($store);
$root='/api/v1/worlds/fr-rest-world/france/constitution';
$initial=$api->handle('GET',$root);
wp21($initial['status']===200
    && $initial['body']['government']==='in_office'
    && isset($initial['headers']['ETag']),
    'French state exposed as versioned JSON with strong entity tag');
wp21($api->handle('GET','/api/v1/worlds/unlisted/france/constitution')['status']===404,
    'cross-world French records are not enumerable without grants');
wp21($api->handle('POST',$root.'/engagements',[
    'bill_id'=>'bill-budget','category'=>'finance','at'=>$actor->at
])['status']===428,'constitutional commands require preconditions and receipt key');
wp21($api->handle('POST',$root.'/engagements',[
    'bill_id'=>'bill-budget','category'=>'finance','at'=>$actor->at,
    'council_approved'=>true
],[
    'Idempotency-Key'=>'budget-malice-001',
    'If-Match'=>$initial['headers']['ETag']
])['status']===422,'JSON cannot forge Council of Ministers deliberations');
wp21($api->handle('POST',$root.'/engagements',[
    'bill_id'=>'ordinary-bill','category'=>'finance','at'=>$actor->at
],[
    'Idempotency-Key'=>'ordinary-finance-fake',
    'If-Match'=>$initial['headers']['ETag']
])['status']===409,'client cannot relabel ordinary legislation as finance to evade 49.3 quota');
$actor->at='2026-11-24T09:00:00+01:00';
$official=$api->handle('POST',$root.'/engagements',[
    'bill_id'=>'bill-budget','category'=>'finance','at'=>$actor->at
],[
    'Idempotency-Key'=>'budget-valid-49-3-001',
    'If-Match'=>$initial['headers']['ETag']
]);
wp21($official['status']===202
    && $official['body']['law_promulgated']===false
    && $official['body']['filing_closes_at']===
        FrenchConstitutionalCrisis::instant('2026-11-25T09:00:00+01:00'),
    'genuine 49.3 engagement begins constitutional 24-hour window');

$afterRestart=new FrenchConstitutionalApi(new PdoFrenchConstitutionalStore(
    $connect(),$actor
));
wp21($afterRestart->handle('POST',$root.'/engagements',[
    'bill_id'=>'bill-budget','category'=>'finance','at'=>$actor->at
],[
    'idempotency-key'=>'budget-valid-49-3-001',
    'if-match'=>'"stale"'
])===$official,'same authenticated principal can safely replay after DB reconnect');
wp21($afterRestart->handle('POST',$root.'/engagements',[
    'bill_id'=>'bill-budget','category'=>'ordinary','at'=>$actor->at
],[
    'Idempotency-Key'=>'budget-valid-49-3-001',
    'If-Match'=>$official['headers']['ETag']
])['status']===409,'receipt key cannot be reused to alter bill category');

$actor->at='2026-11-24T10:00:00+01:00';
$actor->signed=57;
wp21($api->handle('POST',$root.'/motions',[
    'motion_id'=>'motion-one','at'=>$actor->at
],[
    'Idempotency-Key'=>'motion-signatures-57',
    'If-Match'=>$official['headers']['ETag']
])['status']===409,'insufficient verified signatures cannot file censure');
$actor->signed=58;
wp21($api->handle('POST',$root.'/motions',[
    'motion_id'=>'motion-one',
    'at'=>$actor->at,
    'signatures'=>500
],[
    'Idempotency-Key'=>'motion-signed-forged',
    'If-Match'=>$official['headers']['ETag']
])['status']===422,'client-provided signature count cannot override verified parliamentary record');
$motion=$api->handle('POST',$root.'/motions',[
    'motion_id'=>'motion-one','at'=>$actor->at
],[
    'Idempotency-Key'=>'motion-valid-58-signatures',
    'If-Match'=>$official['headers']['ETag']
]);
wp21($motion['status']===202 && $motion['body']['needed_votes']===289,
    'one tenth signed motion requires majority absolute 289 affirmative votes');
$actor->at='2026-11-26T09:59:59+01:00';
wp21($api->handle('POST',$root.'/motions/motion-one/votes',[
    'at'=>$actor->at
],[
    'Idempotency-Key'=>'motion-too-early-0001',
    'If-Match'=>$motion['headers']['ETag']
])['status']===409,'48-hour minimum debate interval is enforced');

$actor->at='2026-11-26T10:00:00+01:00';
wp21($api->handle('POST',$root.'/motions/motion-one/votes',[
    'at'=>$actor->at,'votes_for'=>289
],[
    'Idempotency-Key'=>'motion-votes-forged1',
    'If-Match'=>$motion['headers']['ETag']
])['status']===422,'REST body cannot forge an absolute-majority parliamentary ballot');
$actor->voted=289;
$accepted=$api->handle('POST',$root.'/motions/motion-one/votes',[
    'at'=>$actor->at
],[
    'Idempotency-Key'=>'motion-certified-289',
    'If-Match'=>$motion['headers']['ETag']
]);
wp21($accepted['status']===200
    && $accepted['body']['government']==='resignation_required'
    && $accepted['body']['regime_changed']===false,
    'Article 50 obliges cabinet to resign without automatically ending the Republic');
wp21($api->handle('GET',$root)['body']['history'][0]['assembly_adoption']===false,
    'censure blocks Assembly adoption of the government-backed draft');

$rows=(int)$pdo->query('SELECT COUNT(*) FROM wp_fr_constitutional_journal')->fetchColumn();
$receipts=(int)$pdo->query('SELECT COUNT(*) FROM wp_fr_constitutional_receipts')->fetchColumn();
wp21($rows===3 && $receipts===3,
    'only three successful constitutional commands are persisted atomically');
$actor->allowed=false;
wp21($api->handle('GET',$root)['status']===404,
    'revoked actor cannot read parliamentary world');
wp21($api->handle('POST',$root.'/motions/motion-one/votes',[
    'at'=>$actor->at
],[
    'Idempotency-Key'=>'motion-certified-289','If-Match'=>'"stale"'
])['status']===403,'revoked actor cannot retrieve previous vote receipts');
$actor->allowed=true;
$pdo->exec("UPDATE wp_fr_constitutional_states SET state_sha=REPEAT('0',64) WHERE world_id='fr-rest-world'");
wp21($api->handle('GET',$root)['status']===503,
    'database checksum corruption fails closed without SQL disclosure');

echo "WP21 French constitutional MariaDB REST and delegation tests passed.\n";
