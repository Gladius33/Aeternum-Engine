<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoInstitutionalApi.php';
require __DIR__.'/../games/world-politics/src/PoliticalRoster.php';

use Aeternum\WorldPolitics\{
    PoliticalRoster,InstitutionalSimulation,PdoInstitutionalStore,
    PdoInstitutionalApi,InstitutionalActorAuthorizer
};

function wp7(bool $valid,string $description):void
{
    if (!$valid) throw new RuntimeException($description);
    echo "PASS WP07: $description\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES' || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"Dedicated world-politics wp_test database required\n");exit(2);
}
$pdo=new PDO(getenv('WP_TEST_DSN'),getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
]);
foreach ([
    'wp_institutional_journal','wp_institutional_receipts','wp_institutional_states',
    'wp_command_receipts','wp_world_events','wp_world_rosters'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach (['001_rosters.sql','002_institutions.sql'] as $filename) {
    $raw=file_get_contents(__DIR__.'/../games/world-politics/database/'.$filename);
    $sql=preg_replace('/^\s*--[^\n]*$/m','',$raw);
    foreach (explode(';',$sql) as $statement) {
        if(trim($statement)!=='') $pdo->exec(trim($statement));
    }
}
$auth=new class implements InstitutionalActorAuthorizer {
    public string $role='prime_minister';
    public bool $clockAllowed=false;
    public bool $revoked=false;
    public function actorId():string{return 'trusted-player';}
    public function allows(string $world,string $country,string $ability):bool {
        if ($this->revoked || $world!=='world-fr' || $country!=='FR') return false;
        return $ability!=='advance_clock' || $this->clockAllowed;
    }
    public function roleFor(string $world,string $country,string $action):string {
        return $this->role;
    }
};
$roster=new PoliticalRoster('world-fr','FR',['prime_minister']);
$pdo->prepare('INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)')
    ->execute(['world-fr','FR',json_encode($roster->snapshot(),JSON_THROW_ON_ERROR),$roster->snapshotHash()]);
$store=new PdoInstitutionalStore($pdo,$auth);
$store->seed(new InstitutionalSimulation('world-fr','FR','2026-11-24'));
$api=new PdoInstitutionalApi($store);
$base='/api/v1/worlds/world-fr/countries/FR/institutions';

$read=$api->handle('GET',$base);
wp7($read['status']===200 && isset($read['headers']['ETag']),'REST represents persisted institutional world');
wp7($api->handle('GET',$base.'/rulebook')['body']['head_of_government']==='prime_minister',
    'REST exposes reviewed French institutions, not generic presidential assumptions');
wp7($api->handle('GET','/api/v1/worlds/world-secret/countries/FR/institutions')['status']===404,
    'unauthorized world does not leak institutional data');
wp7($api->handle('POST',$base.'/decisions',['action'=>'submit_bill','payload'=>['title'=>'Test']])['status']===428,
    'REST requires ETag and idempotency key');
$send=static function(string $endpoint,array $payload,string $key,string $etag) use ($api,$base):array {
    return $api->handle('POST',$base.$endpoint,$payload,[
        'Idempotency-Key'=>$key,'If-Match'=>$etag,
    ]);
};
$impostor=$send('/decisions',[
    'action'=>'submit_bill','payload'=>['title'=>'Budget'],
    'actor_role'=>'president'
],'bill-first-00001',$read['headers']['ETag']);
wp7($impostor['status']===422,'client cannot inject an actor role in the REST body');
$bill=$send('/decisions',[
    'action'=>'submit_bill','payload'=>['title'=>'Dépôt législatif fictif']
],'bill-first-00001',$read['headers']['ETag']);
wp7($bill['status']===202 && isset($bill['body']['bill_id']),'bill accepted with server-derived prime minister role');
$retry=$send('/decisions',[
    'action'=>'submit_bill','payload'=>['title'=>'Dépôt législatif fictif']
],'bill-first-00001','"stale"');
wp7($retry===$bill,'durable idempotent command receipt available through REST');
$auth->role='president';
$illegal=$send('/decisions',[
    'action'=>'dissolve_lower_house','payload'=>[]
],'diss-no-consult-0001',$bill['headers']['ETag']);
wp7($illegal['status']===409,'president cannot claim consultation evidence that does not exist');

$etag=$bill['headers']['ETag'];
foreach(['prime_minister','assembly_speaker','senate_speaker'] as $index=>$role) {
    $auth->role=$role;
    $consult=$send('/decisions',[
        'action'=>'record_consultation','payload'=>[]
    ],'consult-rest-000'.($index+1),$etag);
    wp7($consult['status']===202 && $consult['body']['consulted_institution']===$role,
        'consultation recorded by verified role '.$role);
    $etag=$consult['headers']['ETag'];
}
$auth->role='president';
$diss=$send('/decisions',[
    'action'=>'dissolve_lower_house','payload'=>[]
],'diss-after-consult-01',$etag);
wp7($diss['status']===202 && $diss['body']['election_deadline']==='2027-01-03',
    'consulted presidential dissolution schedules legal election deadline');
$clockReject=$send('/clock',['target_day'=>'2026-12-16'],
    'clock-rest-000001',$diss['headers']['ETag']);
wp7($clockReject['status']===403,'unprivileged clients cannot advance the authoritative simulation clock');
$auth->clockAllowed=true;
$clock=$send('/clock',['target_day'=>'2026-12-16'],
    'clock-rest-000001',$diss['headers']['ETag']);
wp7($clock['status']===200 && $clock['body']['day']==='2026-12-16',
    'trusted scheduler can advance country world through REST facade');
wp7($api->handle('GET',$base)['body']['election']['status']==='voting_window_open',
    'REST reads resulting scheduled election event');
$auth->revoked=true;
wp7($api->handle('GET',$base)['status']===404,'revoked actor loses read access');
wp7($send('/decisions',[
    'action'=>'submit_bill','payload'=>['title'=>'Dépôt législatif fictif']
],'bill-first-00001','"stale"')['status']===403,
    'revocation prevents replay of a historical receipt');
$events=(int)$pdo->query('SELECT COUNT(*) FROM wp_institutional_journal')->fetchColumn();
wp7($events===6,'successful bill, consultations, dissolution and clock are journaled; rejected calls are not');
echo "WP07 institutional REST and MariaDB integration passed.\n";
