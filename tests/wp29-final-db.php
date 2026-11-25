<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/WorldClockApi.php';
require __DIR__.'/../games/world-politics/src/DomesticEnactmentRegistry.php';
require __DIR__.'/../games/world-politics/src/DomesticPolicyApi.php';
use Aeternum\WorldPolitics\{PdoDomesticPolicyStore,PersistentDomesticPolicyAuthority,VerifiedDomesticPolicyAuthority,DomesticEnactmentRegistry,DomesticPolicyApi};

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
    'wp_domestic_enactments','wp_domestic_receipts','wp_domestic_journal','wp_domestic_states',
    'wp_world_clock_fr_parliament_steps','wp_world_clock_fr_parliament',
    'wp_world_clock_country_steps','wp_world_clock_countries',
    'wp_world_clock_steps','wp_world_mutation_fences','wp_world_clock',
    'wp_diplomatic_outbox','wp_diplomatic_journal',
    'wp_diplomatic_receipts','wp_diplomatic_states',
    'wp_organization_journal','wp_organization_receipts','wp_organization_states',
    'wp_economic_journal','wp_economic_receipts','wp_economic_states'
] as $table) $setup->exec('DROP TABLE IF EXISTS '.$table);
foreach ([
    '003_diplomacy.sql','004_organizations.sql',
    '005_economic_policies.sql','006_diplomatic_outbox.sql',
    '007_world_clock.sql','008_world_clock_countries.sql','011_fr_parliament_world_clock.sql','013_world_mutation_fences.sql','017_wp29b_domestic_policy.sql','018_wp29_final.sql'
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


$base=new class implements PersistentDomesticPolicyAuthority {
    public bool $clockAllowed=true;
    public string $day='2026-11-25';
    public function actorId():string{return 'verified-world-scheduler';}
    public function allows(string $world,string $ability):bool {
        return $world==='clock-world' && ($ability!=='domestic.clock'||$this->clockAllowed);
    }
    public function enactedInstrument(string $world,string $policy):?string{return str_repeat('f',64);}
    public function clockDay(string $world):string{return $this->day;}
};
$registry=new DomesticEnactmentRegistry($connect(),$base);
$verified=new VerifiedDomesticPolicyAuthority($base,$registry);
$domestic=new PdoDomesticPolicyStore($connect(),$verified);
$domestic->seed('clock-world',['start_day'=>'2026-11-24','currency'=>'EUR','opening_balance'=>1000,
    'baseline_revenue'=>100,'baseline_expense'=>90,'cohorts'=>['workers'=>400],
    'catalog'=>['reform'=>['revenue_delta'=>20,'expense_delta'=>5,'pressure_delta'=>['workers'=>200],'duration_days'=>2]]]);
$clock=new WorldClockCoordinator($connect(),$organizationStore,$diplomacyStore,$economicStore,$scheduler,null,[] ,null,$domestic);
$clock->seed('clock-world','2026-11-24');
$api=new DomesticPolicyApi($domestic);
$url='/api/v1/worlds/clock-world/domestic';
$a=$api->handle('GET',$url);
$headers=['If-Match'=>$a['headers']['ETag'],'Idempotency-Key'=>'policy-proposal-001'];
$p=$api->handle('POST',$url.'/proposals',['policy_id'=>'reform','effective_day'=>'2026-11-25'],$headers);
wp17($p['status']===202,'WP29 coordinated proposal');
$h=['If-Match'=>$p['headers']['ETag'],'Idempotency-Key'=>'policy-enact-00001'];
wp17($api->handle('POST',$url.'/enactments',['policy_id'=>'reform'],$h)['status']===409,'WP29 no enactment without registry proof');
$proof=$registry->publish('clock-world','reform','curated_final_act',str_repeat('b',64),str_repeat('c',64));
$e=$api->handle('POST',$url.'/enactments',['policy_id'=>'reform'],$h);
wp17($e['status']===200 && $e['body']['policy']['instrument_sha']===$proof,'WP29 proof comes from trusted registry');
$begin=$clock->read('clock-world');
wp17($begin['state']['domestic_enabled']===true,'WP29 domestic pinned');
$omitted=new WorldClockCoordinator($connect(),$organizationStore,$diplomacyStore,$economicStore,$scheduler);
wp17reject(fn()=>$omitted->read('clock-world'),'WP29 restart cannot omit domestic');
wp17reject(fn()=>$clock->advance('clock-world','2026-11-26','skip-two-days-0001',$begin['etag']),'WP29 cannot skip daily ledger');
wp17reject(fn()=>$domestic->execute('clock-world','clock',['target_day'=>'2026-11-25'],'direct-clock-0001',$e['headers']['ETag']),'WP29 direct idle clock denied');
$scheduler->allowEconomyClock=true;$base->clockAllowed=false;
wp17reject(fn()=>$clock->advance('clock-world','2026-11-25','domestic-tick-0001',$begin['etag']),'WP29 failure leaves tick pending');
wp17($clock->read('clock-world')['state']['status']==='advancing','WP29 cannot publish partial day');
$base->clockAllowed=true;
$current=$domestic->read('clock-world');
$stepKey='tick-'.substr(hash('sha256',"clock-world\n2026-11-25\ndomestic\nwp17"),0,32);
$domestic->execute('clock-world','clock',['target_day'=>'2026-11-25'],$stepKey,$current['etag']);
$result=$clock->resumePending('clock-world');
wp17($result['result']['committed_day']==='2026-11-25' && in_array('domestic',$result['result']['completed_subsystems'],true),'WP29 recovered domestic checkpoint');
wp17($domestic->read('clock-world')['state']['balance']===1025,'WP29 daily fiscal effects');
wp17((int)$setup->query("SELECT recovered FROM wp_world_clock_steps WHERE subsystem='domestic'")->fetchColumn()===1,'WP29 recovered exactly once');
$summary=$api->handle('GET',$url.'/summary');
wp17($summary['body']['net_flow']===25 && $summary['body']['days_observed']===1,'WP29 fiscal summary API');
$base->day='2026-11-26';$next=$clock->advance('clock-world',$base->day,'domestic-tick-0002',$result['etag']);
wp17($next['result']['coherent']===true && $domestic->read('clock-world')['state']['balance']===1050,'WP29 next day coordinated');
foreach (['wp_domestic_enactments','wp_domestic_receipts','wp_domestic_journal','wp_domestic_states'] as $t) $setup->exec('DROP TABLE '.$t);
echo "WP29 final coordinated domestic integration passed.\n";
