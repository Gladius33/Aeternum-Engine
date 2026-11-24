<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoEconomicApi.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,EconomicReferenceRegistry,WorldEconomicSimulation,
    PdoEconomicStore,PdoEconomicApi,EconomicPolicyPrincipal
};

function wp13(bool $yes,string $message):void {
    if (!$yes) throw new RuntimeException($message);
    echo "PASS WP13: ".$message."\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"Danger: WP_TEST_ISOLATED=YES and wp_test DSN mandatory\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$user=getenv('WP_TEST_USER');$secret=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$user,$secret,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach(['wp_economic_journal','wp_economic_receipts','wp_economic_states'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS '.$table);
}
$sql=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(
    __DIR__.'/../games/world-politics/database/005_economic_policies.sql'
));
foreach(explode(';',$sql) as $statement) {
    if (trim($statement)!=='') $pdo->exec(trim($statement));
}

$fixture=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
$fixture['entities'][]=[
    'id'=>'state_fr','label'=>'France','iso2'=>'FR',
    'kind'=>'state','un_status'=>'un_member','parent_id'=>null,
    'controller'=>'human_or_ai','representation'=>'international_state_actor',
    'sources'=>['https://www.un.org/en/about-us/member-states'],
    'strategic_programmes'=>[]
];
$actors=new GeopoliticalActorRegistry($fixture);
$fixtureData=[
    'actor_id'=>'state_fr','partner_id'=>'state_ir',
    'metric'=>'bilateral_trade_usd','value'=>80000000,'unit'=>'USD',
    'year'=>2025,'kind'=>'scenario_assumption','quality'=>'assumed',
    'source_url'=>null,'license'=>'fictional_scenario'
];
$references=new EconomicReferenceRegistry('2026-11-24',[$fixtureData],$actors);

$player=new class implements EconomicPolicyPrincipal {
    public bool $read=true;
    public bool $legal=false;
    public function actorId():string{return 'authenticated-france';}
    public function allows(string $world,string $actor,string $ability):bool {
        if (!$this->read || $world!=='world-economy')return false;
        if ($actor===$this->actorId() && $ability==='economy.read')return true;
        return $actor==='state_fr' && in_array($ability,['economy.propose','economy.approve'],true);
    }
    public function legalApproval(string $world,string $country,string $action):bool {
        return $this->legal && $world==='world-economy'
            && $country==='state_fr' && $action==='trade_restriction';
    }
};
$clock=new class implements EconomicPolicyPrincipal {
    public function actorId():string{return 'trusted-scenario-clock';}
    public function allows(string $world,string $actor,string $ability):bool {
        return $world==='world-economy' && $actor===$this->actorId()
            && in_array($ability,['economy.read','economy.clock'],true);
    }
    public function legalApproval(string $world,string $country,string $action):bool{return false;}
};

$store=new PdoEconomicStore($pdo,$actors,$references,$player);
$clockStore=new PdoEconomicStore($pdo,$actors,$references,$clock);
$store->seed(new WorldEconomicSimulation('world-economy','2026-11-24',$actors,$references));
$store->seed(new WorldEconomicSimulation('world-hidden','2026-11-24',$actors,$references));
$api=new PdoEconomicApi($store);
$worker=new PdoEconomicApi($clockStore);
$root='/api/v1/worlds/world-economy/economy';
$before=$api->handle('GET',$root);
wp13($before['status']===200 && $before['body']['projections']===[],
    'GET exposes unmodified world economic state and ETag');
$indicators=$api->handle('GET',$root.'/indicators');
wp13($indicators['status']===200
    && $indicators['body']['indicators'][0]['license']==='fictional_scenario'
    && $indicators['body']['reference_sha']===$references->hash(),
    'REST retains explicit scenario provenance and data snapshot hash');
wp13($api->handle('GET','/api/v1/worlds/world-hidden/economy')['status']===404,
    'unauthorized second world is concealed');
wp13($api->handle('POST',$root.'/policies',[
    'from'=>'state_fr','to'=>'state_ir','effect_bps'=>1500,
    'low_bps'=>800,'high_bps'=>2200,'effective_on'=>'2026-12-01'
])['status']===428,'state changing REST requires strong precondition and receipt key');
wp13($api->handle('POST',$root.'/policies',[
    'from'=>'state_ir','to'=>'state_fr','effect_bps'=>1500,
    'low_bps'=>800,'high_bps'=>2200,'effective_on'=>'2026-12-01'
],[
    'Idempotency-Key'=>'impersonate-iran-1','If-Match'=>$before['headers']['ETag']
])['status']===403,'user cannot issue restrictions as non-playable Iran');

$payload=[
    'from'=>'state_fr','to'=>'state_ir','effect_bps'=>1500,
    'low_bps'=>800,'high_bps'=>2200,'effective_on'=>'2026-12-01'
];
$proposed=$api->handle('POST',$root.'/policies',$payload,[
    'Idempotency-Key'=>'fr-trade-proposal1','If-Match'=>$before['headers']['ETag']
]);
wp13($proposed['status']===202 && $proposed['body']['status']==='proposed',
    'bilateral restriction is a nonbinding proposal');
$policyId=$proposed['body']['policy_id'];
wp13($api->handle('GET',$root.'/policies/'.$policyId)['body']['projection']===null,
    'unapproved policy has no projection or claimed GDP effect');

$newPdo=new PDO(getenv('WP_TEST_DSN'),getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
$restart=new PdoEconomicApi(new PdoEconomicStore(
    $newPdo,$actors,$references,$player
));
wp13($restart->handle('GET',$root)['body']['policies'][$policyId]['status']==='proposed',
    'persisted economic policy survives new database connection');
wp13($restart->handle('POST',$root.'/policies',$payload,[
    'idempotency-key'=>'fr-trade-proposal1','if-match'=>'"stale"'
])===$proposed,'durable idempotency receipt survives reconnection and stale state');
$altered=$payload;$altered['effect_bps']=3000;
wp13($api->handle('POST',$root.'/policies',$altered,[
    'Idempotency-Key'=>'fr-trade-proposal1','If-Match'=>$proposed['headers']['ETag']
])['status']===409,'same receipt key cannot fund a different hypothetical policy');
wp13($api->handle('POST',$root.'/policies/'.$policyId.'/authorization',[],[
    'Idempotency-Key'=>'fr-legal-approval-1','If-Match'=>$proposed['headers']['ETag']
])['status']===403,'legal authorization absent, no economic effects');
$player->legal=true;
$approved=$api->handle('POST',$root.'/policies/'.$policyId.'/authorization',[],[
    'Idempotency-Key'=>'fr-legal-approval-1','If-Match'=>$proposed['headers']['ETag']
]);
wp13($approved['status']===200 && $approved['body']['status']==='authorized',
    'country-specific legal procedure needed for authorization');
wp13($api->handle('GET',$root)['body']['projections']===[],
    'authorized policy still has no materialized effects before date');
wp13($api->handle('POST',$root.'/clock',['target_day'=>'2026-12-01'],[
    'Idempotency-Key'=>'client-forced-time1','If-Match'=>$approved['headers']['ETag']
])['status']===403,'player cannot advance simulation clock');

$simulated=$worker->handle('POST',$root.'/clock',[
    'target_day'=>'2026-12-01'
],[
    'Idempotency-Key'=>'server-clock-dec01','If-Match'=>$approved['headers']['ETag']
]);
wp13($simulated['status']===200
    && $simulated['body']['effective_policies']===[$policyId],
    'trusted clock activates only the legally authorized policy');
$impact=$api->handle('GET',$root.'/policies/'.$policyId)['body']['projection'];
wp13($impact['central_usd']===12000000
    && $impact['lower_usd']===6400000
    && $impact['upper_usd']===17600000,
    'projected bilateral effect and explicit hypothetical bounds reproducible');
wp13($impact['not_a_forecast']===true
    && $impact['does_not_change_recorded_gdp']===true
    && $impact['reference_kind']==='scenario_assumption',
    'API explicitly does not claim official statistics or GDP forecasts');

$rows=(int)$pdo->query("SELECT COUNT(*) FROM wp_economic_journal WHERE world_id='world-economy'")->fetchColumn();
$receipts=(int)$pdo->query("SELECT COUNT(*) FROM wp_economic_receipts WHERE world_id='world-economy'")->fetchColumn();
$other=(int)$pdo->query("SELECT COUNT(*) FROM wp_economic_journal WHERE world_id='world-hidden'")->fetchColumn();
wp13($rows===3 && $receipts===3 && $other===0,
    'three successful commands journaled atomically, no leaked second-world event');
$player->read=false;
wp13($api->handle('GET',$root)['status']===404,
    'revoking game principal immediately revokes access');
wp13($api->handle('POST',$root.'/policies',$payload,[
    'Idempotency-Key'=>'fr-trade-proposal1','If-Match'=>'"old"'
])['status']===404,'revoking player blocks retrieval of prior idempotent command');
$player->read=true;
$changed=new EconomicReferenceRegistry('2026-11-24',[
    array_replace($fixtureData,['value'=>90000000])
],$actors);
wp13((new PdoEconomicApi(new PdoEconomicStore($newPdo,$actors,$changed,$player)))
    ->handle('GET',$root)['status']===404,
    'altering pinned scenario data cannot silently overwrite a running world');
$pdo->exec("UPDATE wp_economic_states SET state_sha=REPEAT('0',64) WHERE world_id='world-economy'");
wp13($api->handle('GET',$root)['status']===503,
    'tampered economic snapshot denied without leaking SQL details');

echo "WP13 durable economy REST/MariaDB tests passed.\n";
