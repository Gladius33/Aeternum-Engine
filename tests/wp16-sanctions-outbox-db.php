<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoDiplomaticStore.php';
require __DIR__.'/../games/world-politics/src/SanctionsOutboxDispatcher.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,EconomicReferenceRegistry,
    DiplomaticSimulation,PdoDiplomaticStore,DiplomaticPrincipal,
    WorldEconomicSimulation,PdoEconomicStore,EconomicPolicyPrincipal,
    SanctionsOutboxDispatcher
};

function wp16(bool $ok,string $label):void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS WP16: ".$label."\n";
}
function wp16reject(callable $fn,string $label):void {
    try {$fn();}
    catch (DomainException|InvalidArgumentException|UnexpectedValueException $e) {
        wp16(true,$label);return;
    }
    throw new RuntimeException('Expected outbox rejection: '.$label);
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP16 tests only run with isolated wp_test MariaDB\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$user=getenv('WP_TEST_USER');$password=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$db=$connect();
foreach ([
    'wp_diplomatic_outbox','wp_diplomatic_journal','wp_diplomatic_receipts','wp_diplomatic_states',
    'wp_economic_journal','wp_economic_receipts','wp_economic_states'
] as $table) $db->exec('DROP TABLE IF EXISTS '.$table);
foreach ([
    '003_diplomacy.sql','005_economic_policies.sql','006_diplomatic_outbox.sql'
] as $file) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$file));
    foreach (explode(';',$sql) as $stmt) if (trim($stmt)!=='') $db->exec(trim($stmt));
}

$data=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
$data['entities'][]=[
    'id'=>'state_fr','label'=>'France','iso2'=>'FR','kind'=>'state',
    'un_status'=>'un_member','parent_id'=>null,'controller'=>'human_or_ai',
    'representation'=>'international_state_actor',
    'sources'=>['https://www.un.org/en/about-us/member-states'],
    'strategic_programmes'=>[]
];
$actors=new GeopoliticalActorRegistry($data);
$reference=new EconomicReferenceRegistry('2026-11-24',[[
    'actor_id'=>'state_fr','partner_id'=>'state_ir','metric'=>'bilateral_trade_usd',
    'value'=>20000000,'unit'=>'USD','year'=>2025,
    'kind'=>'scenario_assumption','quality'=>'assumed',
    'source_url'=>null,'license'=>'fictional_scenario'
]],$actors);

$dipUser=new class implements DiplomaticPrincipal {
    public function actorId():string{return 'validated-french-diplomat';}
    public function allows(string $world,string $actor,string $ability):bool {
        if ($world!=='world-sanctions') return false;
        if ($actor===$this->actorId() && $ability==='diplomacy.read') return true;
        return $actor==='state_fr' && $ability==='diplomacy.propose';
    }
};
$bridgeWorker=new class implements EconomicPolicyPrincipal {
    public function actorId():string{return 'verified-outbox-scheduler';}
    public function allows(string $world,string $actor,string $ability):bool {
        if ($world!=='world-sanctions')return false;
        if ($actor===$this->actorId()
            && in_array($ability,['economy.read','economy.bridge_dispatch'],true)) return true;
        return $actor==='state_fr' && $ability==='economy.propose';
    }
    public function legalApproval(string $world,string $actor,string $operation):bool{return false;}
};
$fiscal=new class implements EconomicPolicyPrincipal {
    public function actorId():string{return 'authorized-fiscal-official';}
    public function allows(string $world,string $actor,string $ability):bool {
        if ($world!=='world-sanctions')return false;
        if ($actor===$this->actorId() && $ability==='economy.read')return true;
        return $actor==='state_fr' && $ability==='economy.approve';
    }
    public function legalApproval(string $world,string $actor,string $operation):bool {
        return $world==='world-sanctions' && $actor==='state_fr' && $operation==='trade_restriction';
    }
};
$clock=new class implements EconomicPolicyPrincipal {
    public function actorId():string{return 'simulation-world-clock';}
    public function allows(string $world,string $actor,string $ability):bool {
        return $world==='world-sanctions' && $actor===$this->actorId()
            && in_array($ability,['economy.read','economy.clock'],true);
    }
    public function legalApproval(string $world,string $actor,string $operation):bool{return false;}
};

$dipPdo=$connect();$economicPdo=$connect();$sourcePdo=$connect();
$dip=new PdoDiplomaticStore($dipPdo,$actors,$dipUser);
$econ=new PdoEconomicStore($economicPdo,$actors,$reference,$bridgeWorker);
$fiscalStore=new PdoEconomicStore($connect(),$actors,$reference,$fiscal);
$clockStore=new PdoEconomicStore($connect(),$actors,$reference,$clock);
$dip->seed(new DiplomaticSimulation('world-sanctions','2026-11-24',$actors));
$econ->seed(new WorldEconomicSimulation('world-sanctions','2026-11-24',$actors,$reference));
$dispatcher=new SanctionsOutboxDispatcher($sourcePdo,$econ,$bridgeWorker);
$assumptions=[
    'effect_bps'=>1500,'low_bps'=>800,'high_bps'=>2200,
    'effective_on'=>'2026-12-10','kind'=>'scenario_assumption'
];

$before=$dip->read('world-sanctions');
$sanction=$dip->execute('world-sanctions','propose',[
    'from'=>'state_fr','to'=>'state_ir','action'=>'sanctions_proposal'
],'fr-sanction-open-0001',$before['etag']);
wp16($sanction['status']===202
    && $sanction['result']['status']==='pending_domestic_approval',
    'country diplomatic proposal committed without economic effect');
$event=$sanction['result']['id'];
$inbox=$db->prepare('SELECT status FROM wp_diplomatic_outbox WHERE world_id=? AND event_id=?');
$inbox->execute(['world-sanctions',$event]);
wp16($inbox->fetchColumn()==='pending',
    'sanctions intention and corresponding outbox entry committed atomically');
wp16($econ->read('world-sanctions')['state']['policies']===[],
    'economic world still unchanged before worker delivery');

$diplomaticNext=$dip->read('world-sanctions');
$talk=$dip->execute('world-sanctions','propose',[
    'from'=>'state_fr','to'=>'state_ir','action'=>'dialogue'
],'dialogue-not-sanction1',$diplomaticNext['etag']);
$count=(int)$db->query('SELECT COUNT(*) FROM wp_diplomatic_outbox')->fetchColumn();
wp16($talk['status']===202 && $count===1,
    'ordinary dialogue never enters economic sanctions outbox');
wp16reject(fn()=>$dispatcher->deliver('other-world',$event,$assumptions),
    'outbox dispatcher cannot cross game worlds');
$bad=$assumptions;$bad['kind']='observed_official';
wp16reject(fn()=>$dispatcher->deliver('world-sanctions',$event,$bad),
    'worker cannot disguise assumption as observed policy effectiveness');

$result=$dispatcher->deliver('world-sanctions',$event,$assumptions);
wp16($result['status']==='delivered'
    && str_starts_with($result['policy_id'],'eco-'),
    'authorized worker recorded economic proposal and acknowledged the source event');
$wpEcon=$econ->read('world-sanctions');
wp16($wpEcon['state']['policies'][$result['policy_id']]['status']==='proposed'
    && $wpEcon['state']['projections']===[],
    'outbox cannot authorize sanctions or calculate loss automatically');
wp16($dispatcher->deliver('world-sanctions',$event,$assumptions)===$result,
    'same outbox event is replayable without duplicate economic policy');
$changed=$assumptions;$changed['effect_bps']=2000;
wp16reject(fn()=>$dispatcher->deliver('world-sanctions',$event,$changed),
    'delivered event cannot change its original economic assumptions');
wp16((int)$db->query('SELECT COUNT(*) FROM wp_economic_journal')->fetchColumn()===1,
    'retries do not insert repeated economic events');

$next=$dip->read('world-sanctions');
$second=$dip->execute('world-sanctions','propose',[
    'from'=>'state_fr','to'=>'state_ir','action'=>'sanctions_proposal'
],'another-sanction-event1',$next['etag']);
$secondId=$second['result']['id'];
$economicCurrent=$econ->read('world-sanctions');
$receiptKey=SanctionsOutboxDispatcher::receiptKey('world-sanctions',$secondId);
$prewritten=$econ->execute('world-sanctions','propose',[
    'from'=>'state_fr','to'=>'state_ir',
    'effect_bps'=>1500,'low_bps'=>800,'high_bps'=>2200,
    'effective_on'=>'2026-12-10'
],$receiptKey,$economicCurrent['etag']);
wp16($prewritten['status']===202
    && (int)$db->query("SELECT COUNT(*) FROM wp_diplomatic_outbox WHERE status='pending'")->fetchColumn()===1,
    'simulated crash after economy commit leaves source outbox pending');
$recovered=$dispatcher->deliver('world-sanctions',$secondId,$assumptions);
wp16($recovered['status']==='delivered'
    && $recovered['policy_id']===$prewritten['result']['policy_id'],
    'worker reuses original durable receipt after crash, without duplicate policy');
wp16((int)$db->query('SELECT COUNT(*) FROM wp_economic_journal')->fetchColumn()===2,
    'at-least-once delivery yields exactly one economy command per source event');

$latest=$fiscalStore->read('world-sanctions');
$approved=$fiscalStore->execute('world-sanctions','authorize',[
    'policy_id'=>$result['policy_id']
],'fiscal-authorization-01',$latest['etag']);
wp16($approved['status']===200
    && $approved['result']['status']==='authorized'
    && $fiscalStore->read('world-sanctions')['state']['projections']===[],
    'independent country legal gate still precedes economic impact');
$beforeTick=$clockStore->read('world-sanctions');
$tick=$clockStore->execute('world-sanctions','clock',[
    'target_day'=>'2026-12-10'
],'clock-policy-effective1',$beforeTick['etag']);
$projection=$clockStore->read('world-sanctions')['state']['projections'][$result['policy_id']];
wp16($tick['status']===200
    && $projection['central_usd']===3000000
    && $projection['reference_kind']==='scenario_assumption'
    && $projection['not_a_forecast']===true,
    'hypothetical policy effect only after independent fiscal and temporal gates');

$previous=$dip->read('world-sanctions');
$db->exec('DROP TABLE wp_diplomatic_outbox');
try {
    $dip->execute('world-sanctions','propose',[
        'from'=>'state_fr','to'=>'state_ir','action'=>'sanctions_proposal'
    ],'force-db-outbox-failure1',$previous['etag']);
    throw new RuntimeException('Expected missing-outbox SQL failure');
} catch (PDOException $expected) {
    wp16(true,'missing outbox causes sanction-command SQL rejection');
}
wp16($dip->read('world-sanctions')['etag']===$previous['etag'],
    'source command was entirely rolled back without durable outbox');
echo "WP16 durable sanctions outbox/inbox crash recovery passed.\n";
