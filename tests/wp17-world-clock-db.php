<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/WorldClockApi.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,EconomicReferenceRegistry,
    DiplomaticSimulation,OrganizationSimulation,WorldEconomicSimulation,
    PdoDiplomaticStore,PdoOrganizationStore,PdoEconomicStore,
    WorldClockCoordinator,WorldClockApi,EconomicPolicyPrincipal,
    DiplomaticPrincipal
};

function wp17(bool $condition,string $message):void
{
    if (!$condition) throw new RuntimeException('FAILED WP17: '.$message);
    echo "PASS WP17: ".$message."\n";
}
function wp17reject(callable $fn,string $label):void
{
    try {$fn();}
    catch (DomainException|InvalidArgumentException|UnexpectedValueException $error) {
        wp17(true,$label);return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP17 test requires separate wp_test MariaDB\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');
$dbUser=getenv('WP_TEST_USER');
$dbPassword=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO(
    $dsn,$dbUser,$dbPassword,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$setup=$connect();
foreach ([
    'wp_world_clock_fr_parliament_steps','wp_world_clock_fr_parliament',
    'wp_world_clock_country_steps','wp_world_clock_countries',
    'wp_world_clock_steps','wp_world_clock',
    'wp_diplomatic_outbox','wp_diplomatic_journal',
    'wp_diplomatic_receipts','wp_diplomatic_states',
    'wp_organization_journal','wp_organization_receipts','wp_organization_states',
    'wp_economic_journal','wp_economic_receipts','wp_economic_states'
] as $table) $setup->exec('DROP TABLE IF EXISTS '.$table);
foreach ([
    '003_diplomacy.sql','004_organizations.sql',
    '005_economic_policies.sql','006_diplomatic_outbox.sql',
    '007_world_clock.sql','008_world_clock_countries.sql','011_fr_parliament_world_clock.sql'
] as $name) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$name)
    );
    foreach (explode(';',$sql) as $statement) {
        if (trim($statement)!=='') $setup->exec(trim($statement));
    }
}

$fixture=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
$fixture['entities'][]=[
    'id'=>'state_fr','label'=>'France','iso2'=>'FR','kind'=>'state',
    'un_status'=>'un_member','parent_id'=>null,'controller'=>'human_or_ai',
    'representation'=>'international_state_actor',
    'sources'=>['https://www.un.org/en/about-us/member-states'],
    'strategic_programmes'=>[]
];
$actors=new GeopoliticalActorRegistry($fixture);
$references=new EconomicReferenceRegistry('2026-11-24',[[
    'actor_id'=>'state_fr','partner_id'=>'state_ir','metric'=>'bilateral_trade_usd',
    'value'=>80000000,'unit'=>'USD','year'=>2025,
    'kind'=>'scenario_assumption','quality'=>'assumed',
    'source_url'=>null,'license'=>'fictional_scenario'
]],$actors);
$charters=[[
    'id'=>'scenario-forum','name'=>'Organisation fictive du monde-test',
    'status'=>'fictional_scenario','sources'=>[],
    'approval_rule'=>'unanimous','ratification_days'=>5,
    'withdrawal_notice_days'=>15,
    'founders'=>['state_fr','state_ir']
]];

$scheduler=new class implements EconomicPolicyPrincipal {
    public bool $allowWorldAdvance=true;
    public bool $allowEconomyClock=false;
    public function actorId():string{return 'verified-world-scheduler';}
    public function allows(string $world,string $actor,string $ability):bool
    {
        if ($world!=='clock-world') return false;
        if ($actor===$this->actorId()) {
            return match ($ability) {
                'world.clock.bootstrap','world.clock.read'=>true,
                'world.clock.advance'=>$this->allowWorldAdvance,
                'diplomacy.read','organizations.read','economy.read'=>true,
                'organizations.clock','diplomacy.clock'=>true,
                'economy.clock'=>$this->allowEconomyClock,
                default=>false,
            };
        }
        if ($actor==='state_fr') {
            return in_array($ability,[
                'diplomacy.propose','organizations.withdraw',
                'economy.propose','economy.approve'
            ],true);
        }
        return false;
    }
    public function legalApproval(string $world,string $actor,string $instrument):bool
    {
        return $world==='clock-world' && $actor==='state_fr'
            && $instrument==='trade_restriction';
    }
};

$organizations=new OrganizationSimulation(
    'clock-world','2026-11-24',$actors,$charters
);
$withdraw=$organizations->requestWithdrawal(
    $scheduler,'scenario-forum','state_fr','fr-notice'
);
wp17($withdraw['status']==='pending','source game organization has pending withdrawal');
$diplomacy=new DiplomaticSimulation('clock-world','2026-11-24',$actors);
$offer=$diplomacy->propose(
    $scheduler,'state_fr','state_ir','dialogue','fr-dialogue'
);
wp17($offer['status']==='pending_response','source game diplomacy has unanswered offer');
$economy=new WorldEconomicSimulation(
    'clock-world','2026-11-24',$actors,$references
);
$policy=$economy->proposeTradeRestriction(
    $scheduler,'state_fr','state_ir',
    1500,900,2400,'2026-12-12','fr-restriction'
);
$approval=$economy->authorizeRestriction(
    $scheduler,$policy['policy_id'],'fr-lawful-approval'
);
wp17($approval['status']==='authorized','economic policy was lawfully approved before tick');

$organizationStore=new PdoOrganizationStore(
    $connect(),$actors,$charters,$scheduler
);
$diplomacyStore=new PdoDiplomaticStore(
    $connect(),$actors,$scheduler
);
$economicStore=new PdoEconomicStore(
    $connect(),$actors,$references,$scheduler
);
$organizationStore->seed($organizations);
$diplomacyStore->seed($diplomacy);
$economicStore->seed($economy);

$clock=new WorldClockCoordinator(
    $connect(),$organizationStore,$diplomacyStore,$economicStore,$scheduler
);
$clock->seed('clock-world','2026-11-24');
$api=new WorldClockApi($clock);
$url='/api/v1/worlds/clock-world/clock';
$begin=$api->handle('GET',$url);
wp17($begin['status']===200
    && $begin['body']['committed_day']==='2026-11-24'
    && $begin['body']['coherent']===true,
    'all three domains initialized at one authoritative world date');

wp17($api->handle('GET','/api/v1/worlds/absent/clock')['status']===404,
    'unknown world clock is concealed');
wp17($api->handle('POST',$url.'/advances',[
    'target_day'=>'2026-12-31'
])['status']===428,'REST requires If-Match and Idempotency-Key for world time');
wp17($api->handle('POST',$url.'/advances',[
    'target_day'=>'2026-12-31'
],[
    'Idempotency-Key'=>'clock-wrong-etag1',
    'If-Match'=>'"stale"'
])['status']===412,'stale caller cannot start an unapproved tick');
wp17($api->handle('POST',$url.'/advances',[
    'target_day'=>'2028-01-01'
],[
    'Idempotency-Key'=>'clock-too-far-001',
    'If-Match'=>$begin['headers']['ETag']
])['status']===422,'single world tick cannot skip more than 366 days');
wp17($api->handle('POST',$url.'/advances',[
    'target_day'=>'2026-11-23'
],[
    'Idempotency-Key'=>'clock-backwards01',
    'If-Match'=>$begin['headers']['ETag']
])['status']===409,'world clock cannot run backwards');

$key='world-advance-2026-12-31';
$day='2026-12-31';
wp17reject(
    fn()=>$clock->advance(
        'clock-world',$day,$key,$begin['headers']['ETag']
    ),
    'economic domain clock failure interrupts coordinated tick'
);
$partial=$clock->read('clock-world');
wp17($partial['state']['status']==='advancing'
    && $partial['state']['committed_day']==='2026-11-24'
    && $partial['state']['pending_day']===$day
    && $partial['state']['coherent']===false,
    'partial tick is explicitly pending and NOT published as a completed date');
wp17($diplomacyStore->read('clock-world')['state']['day']===$day
    && $organizationStore->read('clock-world')['state']['day']===$day
    && $economicStore->read('clock-world')['state']['day']==='2026-11-24',
    'first two domains committed and economy retains original date');
wp17(count($partial['state']['completed_subsystems'])===2,
    'each committed world-clock step has durable checkpoint');
wp17($organizationStore->read('clock-world')['state']['withdrawals']['fr-notice']['status']==='effective',
    'organization exit notice applied on virtual world date');
wp17($diplomacyStore->read('clock-world')['state']['proposals']['fr-dialogue']['status']==='expired',
    'diplomatic unanswered offer expired on virtual world date');
wp17reject(
    fn()=>$clock->advance(
        'clock-world','2027-01-01','different-while-pending1',$partial['etag']
    ),
    'another tick cannot overtake an unfinished pending tick');

$scheduler->allowEconomyClock=true;
// Simulated crash at the narrowest point: the economy has committed its
// own clock receipt but the coordinator did not record its step checkpoint.
$economicCurrent=$economicStore->read('clock-world');
$stepKey='tick-'.substr(hash('sha256',
    "clock-world\n".$day."\neconomy\nwp17"
),0,32);
$precommitted=$economicStore->execute(
    'clock-world','clock',['target_day'=>$day],$stepKey,$economicCurrent['etag']
);
wp17($precommitted['status']===200
    && $economicStore->read('clock-world')['state']['day']===$day,
    'simulated pre-crash economic commit reached target without clock checkpoint');
$retried=$api->handle('POST',$url.'/advances',[
    'target_day'=>$day
],[
    'Idempotency-Key'=>$key,'If-Match'=>$begin['headers']['ETag']
]);
wp17($retried['status']===200
    && $retried['body']['committed_day']===$day
    && $retried['body']['coherent']===true,
    'coordinator resumes same idempotent tick and publishes only after all domains match');
wp17($clock->read('clock-world')['state']['status']==='coherent'
    && $clock->read('clock-world')['state']['revision']===1,
    'world clock now exposes one committed revision');
$recovery=$setup->prepare(
    "SELECT recovered FROM wp_world_clock_steps
     WHERE world_id=? AND subsystem='economy'"
);
$recovery->execute(['clock-world']);
wp17((int)$recovery->fetchColumn()===1,
    'economic step recovered after crash rather than executed twice');
$effect=$economicStore->read('clock-world')['state']['projections']['fr-restriction'];
wp17($effect['central_usd']===12000000
    && $effect['not_a_forecast']===true,
    'economic projections materialize on the coordinated game date');
$econEvents=(int)$setup->query(
    "SELECT COUNT(*) FROM wp_economic_journal WHERE world_id='clock-world'"
)->fetchColumn();
wp17($econEvents===1,
    'recovery produces precisely one economic advance receipt/event');
wp17($api->handle('POST',$url.'/advances',[
    'target_day'=>$day
],[
    'Idempotency-Key'=>$key,'If-Match'=>'"old-etag"'
])===$retried,
    'finished world tick is idempotently replayed despite stale ETag');
wp17($api->handle('POST',$url.'/advances',[
    'target_day'=>'2027-01-01'
],[
    'Idempotency-Key'=>$key,'If-Match'=>$retried['headers']['ETag']
])['status']===409,
    'same clock command cannot be reused with another target');

$scheduler->allowWorldAdvance=false;
wp17($api->handle('POST',$url.'/advances',[
    'target_day'=>$day
],[
    'Idempotency-Key'=>$key,'If-Match'=>'"old-etag"'
])['status']===403,
    'revoked scheduler cannot replay previously completed clock receipt');
$scheduler->allowWorldAdvance=true;

$next=$api->handle('POST',$url.'/advances',[
    'target_day'=>'2027-01-01'
],[
    'Idempotency-Key'=>'new-coherent-tick-2027',
    'If-Match'=>$retried['headers']['ETag']
]);
wp17($next['status']===200
    && $next['body']['revision']===2
    && $clock->read('clock-world')['state']['committed_day']==='2027-01-01',
    'subsequent tick advances all three domains and increments revision');
foreach ([$organizationStore,$diplomacyStore,$economicStore] as $store) {
    wp17($store->read('clock-world')['state']['day']==='2027-01-01',
        'subsystem remains synchronized with authoritative world clock');
}
echo "WP17 durable cross-domain clock and crash recovery passed.\n";
