<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrenchSocialApi.php';

use Aeternum\WorldPolitics\{
    FrenchConstitutionalCrisis,FrenchConstitutionalPrincipal,
    FrenchSocialDynamics,PdoFrenchConstitutionalStore,
    PdoFrenchSocialStore,FrenchSocialApi
};

function wp24(bool $passed,string $message):void
{
    if (!$passed) throw new RuntimeException('FAILED WP24: '.$message);
    echo "PASS WP24: ".$message."\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP24 demands isolated wp_test database\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$user=getenv('WP_TEST_USER');
$pass=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach([
    'wp_fr_social_journal','wp_fr_social_receipts',
    'wp_fr_social_assumptions','wp_fr_social_states',
    'wp_fr_constitutional_journal','wp_fr_constitutional_receipts',
    'wp_fr_constitutional_states'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach([
    '009_fr_constitutional_49_3.sql','010_fr_social_scenarios.sql'
] as $file) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$file));
    foreach(explode(';',$sql) as $statement) {
        if (trim($statement)!=='') $pdo->exec(trim($statement));
    }
}
$principal=new class implements FrenchConstitutionalPrincipal {
    public bool $allowed=true;
    public string $now='2026-11-24T09:00:00+01:00';
    public function actorId():string{return 'verified-french-social-policy-worker';}
    public function allows(string $world,string $ability):bool {
        return $this->allowed && $world==='french-social-world'
            && in_array($ability,[
                'fr.constitution.read','fr.49_3.engage',
                'fr.constitution.clock','fr.social.admin',
                'fr.social.read','fr.social.assess'
            ],true);
    }
    public function councilDeliberated(string $world,string $bill):bool{return true;}
    public function verifiedBillCategory(string $world,string $bill):string {
        return 'finance';
    }
    public function verifiedSignatures(string $world,string $motion):int{return 58;}
    public function verifiedVotesFor(string $world,string $motion):int{return 0;}
    public function verifiedInstant(string $world,string $operation):string {
        return $this->now;
    }
};
$constitutional=new PdoFrenchConstitutionalStore($connect(),$principal);
$constitutional->seed(new FrenchConstitutionalCrisis(
    'french-social-world','regular-2026',
    FrenchConstitutionalCrisis::instant($principal->now),577
));
$soc=new PdoFrenchSocialStore($pdo,$constitutional,$principal);
$cohorts=[
    [
        'id'=>'health','name'=>'Soins hospitaliers',
        'population_weight_bps'=>3500,
        'base_grievance'=>650,
        'collective_organization'=>790,
        'economic_dependence'=>850,
        'mobility_constraint'=>100,
        'workplace_fragility'=>820
    ],
    [
        'id'=>'manufacturing','name'=>'Manufacture',
        'population_weight_bps'=>4000,
        'base_grievance'=>500,
        'collective_organization'=>680,
        'economic_dependence'=>900,
        'mobility_constraint'=>200,
        'workplace_fragility'=>700
    ],
    [
        'id'=>'retail','name'=>'Services et commerce',
        'population_weight_bps'=>2500,
        'base_grievance'=>200,
        'collective_organization'=>320,
        'economic_dependence'=>600,
        'mobility_constraint'=>300,
        'workplace_fragility'=>350
    ]
];
$context=[
    'civil_liberties_trust'=>730,
    'public_institutional_trust'=>470,
    'parliamentary_support'=>390,
    'government_cohesion'=>720,
    'security_institutional_cohesion'=>860,
    'military_constitutional_cohesion'=>950,
    'elite_cohesion'=>700,
    'economic_resilience'=>560
];
$impacts=['health'=>700,'manufacturing'=>350,'retail'=>-100];
$baseline=[
    'impacts'=>$impacts,
    'concessions'=>['health'=>0,'manufacturing'=>20,'retail'=>80],
    'context'=>$context,
    'provenance'=>'fictional_scenario_curated'
];
$negotiated=[
    'impacts'=>$impacts,
    'concessions'=>['health'=>850,'manufacturing'=>600,'retail'=>80],
    'context'=>$context,
    'provenance'=>'fictional_scenario_curated'
];
$soc->seed(new FrenchSocialDynamics(
    'french-social-world',$cohorts
),[
    'bill-budget-a'=>$baseline,
    'bill-budget-b'=>$negotiated
]);
$api=new FrenchSocialApi($soc);
$root='/api/v1/worlds/french-social-world/france/social';
$initial=$api->handle('GET',$root);
wp24($initial['status']===200
    && $initial['body']['history']===[],
    'initial curated French population model exposed without fabricated findings');
$blocked=$api->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-a'
],[
    'Idempotency-Key'=>'before-constitutional-law-0001',
    'If-Match'=>$initial['headers']['ETag']
]);
wp24($blocked['status']===409,
    'no player can generate social reaction to a 49.3 act that has not legally occurred');
wp24($api->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-a',
    'healthcare_protest_probability'=>1000
],[
    'Idempotency-Key'=>'client-forged-grievances-1',
    'If-Match'=>$initial['headers']['ETag']
])['status']===422,
    'web/mobile client may not send invented social coefficients');
wp24($api->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-a'
])['status']===428,'social assessments require receipt and If-Match preconditions');

$constitutionalBefore=$constitutional->read('french-social-world');
$law=$constitutional->execute('french-social-world','engage',[
    'bill_id'=>'bill-budget-a','category'=>'finance',
    'at'=>$principal->now
],'bill-budget-a-49-3-0001',$constitutionalBefore['etag']);
wp24($law['status']===202,'server-verified 49.3 is recorded before assessing impact');

$result=$api->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-a'
],[
    'Idempotency-Key'=>'assess-social-budget-a',
    'If-Match'=>$initial['headers']['ETag']
]);
wp24($result['status']===202
    && $result['body']['classification']==='scenario_assumptions_not_survey_or_forecast'
    && $result['body']['regime_changed']===false,
    'recorded 49.3 produces pinned non-forecast social scenario');
$firstHealth=$result['body']['cohorts']['health']['grievance_index'];
wp24($firstHealth>$result['body']['cohorts']['retail']['grievance_index'],
    'different professions can have different level of grievance');
$fromAnotherConnection=new FrenchSocialApi(
    new PdoFrenchSocialStore($connect(),$constitutional,$principal)
);
wp24($fromAnotherConnection->handle('GET',$root.'/assessments/bill-budget-a')
        ['body']['scenario_sha']===$result['body']['scenario_sha'],
    'fresh MariaDB connection restores source-attributed professional reaction');
wp24($fromAnotherConnection->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-a'
],[
    'Idempotency-Key'=>'assess-social-budget-a',
    'If-Match'=>'"stale"'
])===$result,'durable receipt makes social assessment repeatable without duplicate journal');
wp24($api->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-b'
],[
    'Idempotency-Key'=>'assess-social-budget-a',
    'If-Match'=>$result['headers']['ETag']
])['status']===409,
    'same idempotency key may not silently switch to a different social scenario');
wp24($api->handle('GET',$root.'/assessments/unknown-law')['status']===404,
    'reports that were never evaluated are not fabricated in the API');

$principal->now='2026-11-25T09:00:01+01:00';
$beforeClose=$constitutional->read('french-social-world');
$close=$constitutional->execute('french-social-world','clock',[
    'at'=>$principal->now
],'close-24-hour-window-a',$beforeClose['etag']);
wp24($close['status']===200,'time service closes constitutional 49.3 window');
$principal->now='2026-11-26T09:00:00+01:00';
$beforeLawTwo=$constitutional->read('french-social-world');
$lawTwo=$constitutional->execute('french-social-world','engage',[
    'bill_id'=>'bill-budget-b','category'=>'finance',
    'at'=>$principal->now
],'bill-budget-b-49-3-0002',$beforeLawTwo['etag']);
wp24($lawTwo['status']===202,
    'second finance bill can be assessed after another verified 49.3 event');
$second=$api->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-b'
],[
    'Idempotency-Key'=>'assess-social-budget-b',
    'If-Match'=>$result['headers']['ETag']
]);
wp24($second['status']===202
    && $second['body']['cohorts']['health']['grievance_index']<$firstHealth,
    'larger curated health-sector concessions reduce health grievance');
wp24($second['body']['aggregate']['production_disruption_index']
    <$result['body']['aggregate']['production_disruption_index'],
    'sector-specific concessions may reduce economic disruption without regime overthrow');
wp24($second['body']['source_constitution_hash']
    !==$result['body']['source_constitution_hash'],
    'social reports remain pinned to the constitutional evidence actually present at assessment time');
wp24((int)$pdo->query('SELECT COUNT(*) FROM wp_fr_social_journal')->fetchColumn()===2,
    'two assessments create exactly two durable journal entries');

$principal->allowed=false;
wp24($api->handle('GET',$root)['status']===404,
    'revocation immediately blocks social world reads');
wp24($api->handle('POST',$root.'/assessments',[
    'bill_id'=>'bill-budget-a'
],[
    'Idempotency-Key'=>'assess-social-budget-a',
    'If-Match'=>'"stale"'
])['status']===403,'revoked social principal cannot replay old receipt');
$principal->allowed=true;
$pdo->exec(
    "UPDATE wp_fr_social_states SET state_sha=REPEAT('0',64)
     WHERE world_id='french-social-world'"
);
wp24($api->handle('GET',$root)['status']===503,
    'corrupt social snapshot fails closed and does not leak raw SQL errors');

echo "WP24 source-pinned persistent social scenario REST/MariaDB passed.\n";
