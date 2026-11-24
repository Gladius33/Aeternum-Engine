<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/WorldClockApi.php';
require_once __DIR__.'/../games/world-politics/src/PoliticalRoster.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,EconomicReferenceRegistry,
    DiplomaticSimulation,OrganizationSimulation,WorldEconomicSimulation,
    InstitutionalSimulation,PoliticalRoster,
    PdoDiplomaticStore,PdoOrganizationStore,PdoEconomicStore,
    PdoInstitutionalStore,InstitutionalActorAuthorizer,
    WorldClockCoordinator,WorldClockApi,EconomicPolicyPrincipal
};

function wp18(bool $condition,string $label):void
{
    if (!$condition) throw new RuntimeException('FAILED WP18: '.$label);
    echo "PASS WP18: ".$label."\n";
}
function wp18reject(callable $callback,string $label):void
{
    try {$callback();}
    catch (\DomainException|\InvalidArgumentException|\UnexpectedValueException $error) {
        wp18(true,$label);return;
    }
    throw new RuntimeException('Expected refusal: '.$label);
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"Dedicated wp_test MariaDB required\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');
$user=getenv('WP_TEST_USER');
$password=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach ([
    'wp_world_clock_country_steps','wp_world_clock_countries',
    'wp_world_clock_steps','wp_world_clock',
    'wp_institutional_journal','wp_institutional_receipts','wp_institutional_states',
    'wp_command_receipts','wp_world_events','wp_world_rosters',
    'wp_diplomatic_outbox','wp_diplomatic_journal',
    'wp_diplomatic_receipts','wp_diplomatic_states',
    'wp_organization_journal','wp_organization_receipts','wp_organization_states',
    'wp_economic_journal','wp_economic_receipts','wp_economic_states'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach ([
    '001_rosters.sql','002_institutions.sql','003_diplomacy.sql',
    '004_organizations.sql','005_economic_policies.sql',
    '006_diplomatic_outbox.sql','007_world_clock.sql',
    '008_world_clock_countries.sql'
] as $filename) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$filename)
    );
    foreach(explode(';',$sql) as $query) if(trim($query)!=='') $pdo->exec(trim($query));
}
$world='federated-world';
$baseline=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
foreach(['FR'=>'France','DE'=>'Germany','US'=>'United States'] as $iso=>$name) {
    $baseline['entities'][]=[
        'id'=>'state_'.strtolower($iso),'label'=>$name,'iso2'=>$iso,
        'kind'=>'state','un_status'=>'un_member','parent_id'=>null,
        'controller'=>'human_or_ai','representation'=>'international_state_actor',
        'sources'=>['https://www.un.org/en/about-us/member-states'],
        'strategic_programmes'=>[]
    ];
}
$actors=new GeopoliticalActorRegistry($baseline);
$references=new EconomicReferenceRegistry('2026-11-24',[[
    'actor_id'=>'state_fr','partner_id'=>'state_ir',
    'metric'=>'bilateral_trade_usd','value'=>70000000,'unit'=>'USD',
    'year'=>2025,'kind'=>'scenario_assumption','quality'=>'assumed',
    'source_url'=>null,'license'=>'fictional_scenario'
]],$actors);
$charters=[[
    'id'=>'fictional-forum','name'=>'Organisation internationale imaginaire',
    'status'=>'fictional_scenario','sources'=>[],
    'approval_rule'=>'unanimous','ratification_days'=>5,
    'withdrawal_notice_days'=>10,'founders'=>['state_fr','state_ir']
]];

$scheduler=new class implements EconomicPolicyPrincipal,InstitutionalActorAuthorizer {
    /** @var array<string,bool> */
    public array $countryEnabled=['FR'=>true,'DE'=>false,'US'=>true];
    public bool $clockEnabled=true;
    public function actorId():string {return 'verified-global-institution-clock';}
    public function allows(string $world,string $entity,string $ability):bool
    {
        if ($world!=='federated-world') return false;
        if ($entity===$this->actorId()) {
            return match($ability) {
                'world.clock.bootstrap','world.clock.read'=>true,
                'world.clock.advance'=>$this->clockEnabled,
                'diplomacy.read','organizations.read','economy.read',
                'diplomacy.clock','organizations.clock','economy.clock'=>true,
                default=>false,
            };
        }
        if (in_array($entity,['FR','DE','US'],true)) {
            return in_array($ability,['read_institutions','advance_clock'],true)
                && ($ability==='read_institutions'
                    || ($this->countryEnabled[$entity]??false));
        }
        if ($entity==='state_fr') {
            return in_array($ability,[
                'diplomacy.propose','economy.propose','economy.approve',
                'organizations.withdraw'
            ],true);
        }
        return false;
    }
    public function legalApproval(string $world,string $state,string $instrument):bool
    {
        return $world==='federated-world' && $state==='state_fr'
            && $instrument==='trade_restriction';
    }
    public function roleFor(string $world,string $country,string $action):string
    {
        return 'simulation_clock';
    }
};

$institutions=new PdoInstitutionalStore($connect(),$scheduler);
foreach ([
    'FR'=>['office'=>'prime_minister','bill_role'=>'prime_minister'],
    'DE'=>['office'=>'federal_chancellor','bill_role'=>'federal_government'],
    'US'=>['office'=>'president','bill_role'=>'member_of_congress']
] as $country=>$details) {
    $roster=new PoliticalRoster($world,$country,[$details['office']]);
    $pdo->prepare(
        'INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)'
    )->execute([
        $world,$country,
        json_encode($roster->snapshot(),JSON_THROW_ON_ERROR),
        $roster->snapshotHash()
    ]);
    $sim=new InstitutionalSimulation($world,$country,'2026-11-24');
    $result=$sim->decide(
        $details['bill_role'],'submit_bill',
        ['title'=>'Fictional budget debate '.$country],
        'bill-'.strtolower($country)
    );
    wp18($result['status']==='filed',
        'country-specific legislative role admitted bill for '.$country);
    $institutions->seed($sim);
}
$organizations=new OrganizationSimulation(
    $world,'2026-11-24',$actors,$charters
);
$organizations->requestWithdrawal(
    $scheduler,'fictional-forum','state_fr','fr-withdraw'
);
$diplomacy=new DiplomaticSimulation($world,'2026-11-24',$actors);
$diplomacy->propose($scheduler,'state_fr','state_ir','dialogue','fr-dialogue');
$economy=new WorldEconomicSimulation($world,'2026-11-24',$actors,$references);
$measure=$economy->proposeTradeRestriction(
    $scheduler,'state_fr','state_ir',1500,800,2200,
    '2026-12-12','fr-sanction-measure'
);
$economy->authorizeRestriction(
    $scheduler,$measure['policy_id'],'fiscal-lawfulness'
);
$orgStore=new PdoOrganizationStore($connect(),$actors,$charters,$scheduler);
$dipStore=new PdoDiplomaticStore($connect(),$actors,$scheduler);
$ecoStore=new PdoEconomicStore($connect(),$actors,$references,$scheduler);
$orgStore->seed($organizations);
$dipStore->seed($diplomacy);
$ecoStore->seed($economy);

$clock=new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,
    $institutions,['US','FR','DE']
);
$clock->seed($world,'2026-11-24');
$read=$clock->read($world);
wp18($read['state']['countries']===['DE','FR','US']
    && $read['state']['coherent']===true,
    'three national calendars pinned and canonicalized in world clock');
wp18((int)$pdo->query('SELECT COUNT(*) FROM wp_world_clock_countries')->fetchColumn()===3,
    'national constitutional roster has three durable country keys');
wp18reject(fn()=>new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,
    $institutions,['CH']
),'countries without reviewed constitutional model rejected');
wp18reject(fn()=>new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,
    $institutions,['FR','FR']
),'duplicate countries cannot be registered for national time');

$target='2026-12-15';
$key='national-clock-step-001';
wp18reject(
    fn()=>$clock->advance($world,$target,$key,$read['etag']),
    'German institutional clock failure interrupts global date publication'
);
$partial=$clock->read($world);
wp18($partial['state']['pending_day']===$target
    && $partial['state']['committed_day']==='2026-11-24'
    && $partial['state']['coherent']===false,
    'global day stays old while a required country's date has not advanced');
wp18($orgStore->read($world)['state']['day']===$target
    && $institutions->read($world,'DE')['state']['day']==='2026-11-24',
    'organizations advanced but blocked German constitutional institution has not');
wp18($partial['state']['completed_country_clocks']===[],
    'no fictional success checkpoint for a denied country');

$scheduler->countryEnabled['DE']=true;
$scheduler->countryEnabled['US']=false;
wp18reject(
    fn()=>$clock->advance($world,$target,$key,$read['etag']),
    'after German and French calendars, blocked US calendar halts publication'
);
$second=$clock->read($world);
wp18($second['state']['completed_country_clocks']===['DE','FR']
    && $institutions->read($world,'DE')['state']['day']===$target
    && $institutions->read($world,'FR')['state']['day']===$target
    && $institutions->read($world,'US')['state']['day']==='2026-11-24',
    'per-country progress checkpoints survive consecutive partial failures');
wp18($dipStore->read($world)['state']['day']==='2026-11-24',
    'diplomacy is not advanced before all required national institutions');

$scheduler->countryEnabled['US']=true;
$beforeUs=$institutions->read($world,'US');
$countryStepKey='tick-'.substr(hash('sha256',
    $world."\n".$target."\ninstitution-US\nwp17"
),0,32);
$usCommit=$institutions->advance(
    $world,'US',$target,$countryStepKey,$beforeUs['etag']
);
wp18($usCommit['status']===200
    && $institutions->read($world,'US')['state']['day']===$target,
    'simulated crash after US institution commit, before country checkpoint');
$beforeResume=(int)$pdo->query(
    "SELECT COUNT(*) FROM wp_world_clock_country_steps"
)->fetchColumn();
wp18($beforeResume===2,
    'two country checkpoints exist; precommitted US step awaits recovery');

$api=new WorldClockApi($clock);
$endpoint='/api/v1/worlds/'.$world.'/clock/advances';
$finished=$api->handle('POST',$endpoint,[
    'target_day'=>$target
],[
    'Idempotency-Key'=>$key,
    'If-Match'=>$read['etag']
]);
wp18($finished['status']===200
    && $finished['body']['countries']===['DE','FR','US']
    && $finished['body']['coherent']===true,
    'resume finishes all constitutional and international aggregates');
$checkpoint=$pdo->prepare(
    "SELECT recovered FROM wp_world_clock_country_steps
     WHERE world_id=? AND country_code='US'"
);
$checkpoint->execute([$world]);
wp18((int)$checkpoint->fetchColumn()===1,
    'precommitted US event recovered once rather than repeated');
foreach (['DE','FR','US'] as $country) {
    $state=$institutions->read($world,$country)['state'];
    $bill=$state['bills']['bill-'.strtolower($country)];
    wp18($state['day']===$target && $bill['status']==='debate_ready',
        'national legislation schedule processed under its own constitutional rulebook '.$country);
}
wp18($clock->read($world)['state']['committed_day']===$target,
    'authoritative world date published only once all 6 aggregates aligned');
wp18($dipStore->read($world)['state']['proposals']['fr-dialogue']['status']==='expired'
    && $orgStore->read($world)['state']['withdrawals']['fr-withdraw']['status']==='effective'
    && $ecoStore->read($world)['state']['policies']['fr-sanction-measure']['status']==='effective',
    'national event scheduling coexists with diplomacy, organization and economy transitions');

wp18($api->handle('POST',$endpoint,[
    'target_day'=>$target
],[
    'Idempotency-Key'=>$key,'If-Match'=>'"old"'
])===$finished,'completed worldwide clock command replays without duplicate dates');
foreach (['FR','DE','US'] as $country) {
    $q=$pdo->prepare(
        'SELECT COUNT(*) FROM wp_institutional_journal WHERE world_id=? AND country_code=? AND operation=?'
    );
    $q->execute([$world,$country,'advance_clock']);
    wp18((int)$q->fetchColumn()===1,
        'country calendar step has exactly one durable journal event '.$country);
}
wp18reject(fn()=>(new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,
    $institutions,['FR']
))->read($world),
    'worker cannot silently drop Germany or the United States from pinned roster');

$scheduler->clockEnabled=false;
wp18($api->handle('POST',$endpoint,[
    'target_day'=>$target
],[
    'Idempotency-Key'=>$key,'If-Match'=>'"stale"'
])['status']===403,'revoked world-clock principal cannot replay a prior tick');
$scheduler->clockEnabled=true;
$next=$api->handle('POST',$endpoint,[
    'target_day'=>'2026-12-16'
],[
    'Idempotency-Key'=>'next-national-clock-01',
    'If-Match'=>$finished['headers']['ETag']
]);
wp18($next['status']===200 && $next['body']['revision']===2,
    'following day also advances all pinned national and global calendars');

echo "WP18 country-specific, crash-safe institutional world clock passed.\n";
