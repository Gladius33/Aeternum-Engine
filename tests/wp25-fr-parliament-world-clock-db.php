<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/WorldClockApi.php';
require_once __DIR__.'/../games/world-politics/src/PoliticalRoster.php';
require_once __DIR__.'/../games/world-politics/src/FrenchConstitutionalCrisis.php';
require_once __DIR__.'/../games/world-politics/src/PdoFrenchSittingCalendarStore.php';
require_once __DIR__.'/../games/world-politics/src/WorldTickRecoveryWorker.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,EconomicReferenceRegistry,
    DiplomaticSimulation,OrganizationSimulation,WorldEconomicSimulation,
    InstitutionalSimulation,PoliticalRoster,
    PdoDiplomaticStore,PdoOrganizationStore,PdoEconomicStore,
    PdoInstitutionalStore,InstitutionalActorAuthorizer,
    WorldClockCoordinator,WorldClockApi,EconomicPolicyPrincipal,
    FrenchConstitutionalCrisis,FrenchConstitutionalPrincipal,
    PdoFrenchConstitutionalStore,FrenchSittingAuthority,
    FrenchSittingCalendar,PdoFrenchSittingCalendarStore,WorldTickRecoveryWorker
};

function wp25(bool $condition,string $label):void
{
    if (!$condition) throw new RuntimeException('FAILED WP25: '.$label);
    echo "PASS WP25: ".$label."\n";
}
function wp25reject(callable $callback,string $label):void
{
    try {$callback();}
    catch (\DomainException|\InvalidArgumentException|\UnexpectedValueException $error) {
        wp25(true,$label);return;
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
    'wp_world_clock_fr_parliament_steps','wp_world_clock_fr_parliament',
    'wp_fr_sitting_journal','wp_fr_sitting_calendars',
    'wp_fr_constitutional_journal','wp_fr_constitutional_receipts',
    'wp_fr_constitutional_states',
    'wp_world_clock_country_steps','wp_world_clock_countries',
    'wp_world_clock_steps','wp_world_mutation_fences','wp_world_clock',
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
    '008_world_clock_countries.sql','009_fr_constitutional_49_3.sql',
    '011_fr_parliament_world_clock.sql','012_fr_sitting_calendar.sql','013_world_mutation_fences.sql'
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
    wp25($result['status']==='filed',
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


/**
 * Same ACTOR identity as the global scheduler, but a separate trusted
 * French parliamentary clerk/clock authority with distinct signature.
 * No web client may supply a verified timestamp or parliamentary roll-call.
 */
$frPrincipal=new class($scheduler) implements FrenchSittingAuthority {
    public bool $clockAllowed=false;
    public string $now='2026-11-24T09:00:00+01:00';
    public function __construct(private readonly object $globalScheduler) {}
    public function actorId():string {return $this->globalScheduler->actorId();}
    public function allows(string $world,string $ability):bool {
        if ($world!=='federated-world') return false;
        return match($ability) {
            'fr.constitution.read'=>true,
            'fr.49_3.engage'=>true,
            'fr.calendar.bootstrap','fr.calendar.read','fr.calendar.schedule'=>true,
            'fr.constitution.clock'=>$this->clockAllowed,
            default=>false
        };
    }
    public function councilDeliberated(string $world,string $bill):bool {
        return $world==='federated-world';
    }
    public function verifiedBillCategory(string $world,string $bill):string {
        return 'finance';
    }
    public function verifiedSignatures(string $world,string $motion):int{return 58;}
    public function verifiedVotesFor(string $world,string $motion):int{return 0;}
    public function verifiedInstant(string $world,string $operation):string {
        return $this->now;
    }
    public function verifiedCensureVoteAt(string $world,string $motion):string {
        return '2026-11-27T15:00:00+01:00';
    }
};
$frSitting=new PdoFrenchSittingCalendarStore($connect(),$frPrincipal);
$frSitting->seed(new FrenchSittingCalendar($world,[
    '2026-11-24','2026-11-25','2026-11-26',
    '2026-11-27','2026-11-30','2026-12-01',
    '2026-12-02','2026-12-03','2027-03-28','2027-10-31'
],'fictional_scenario','https://www.assemblee-nationale.fr/'));
$frStore=new PdoFrenchConstitutionalStore(
    $connect(),$frPrincipal,$frSitting
);
wp25($frStore->hasSittingCalendar()===true,
    'worldwide constitutional store uses mandatory reviewed French sitting calendar');
$frStore->seed(new FrenchConstitutionalCrisis(
    $world,'regular-2026',
    FrenchConstitutionalCrisis::instant($frPrincipal->now),577
));
wp25reject(fn()=>(new PdoFrenchConstitutionalStore(
    $connect(),$frPrincipal
))->read($world),
    'global world test refuses a worker that omitted the pinned sitting calendar');
$lawInitial=$frStore->read($world);
$engagement=$frStore->execute($world,'engage',[
    'bill_id'=>'fr-finance-budget',
    'category'=>'finance',
    'at'=>$frPrincipal->now
],'verified-budget-49-3-01',$lawInitial['etag']);
wp25($engagement['status']===202,
    'France constitution received a verified government budget 49.3 engagement');

$scheduler->countryEnabled['DE']=true;
$clock=new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,
    $institutions,['US','FR','DE'],$frStore
);
wp25reject(fn()=>new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,
    $institutions,['DE','US'],$frStore
),'French parliamentary calendar cannot be enabled while FR is omitted');
$clock->seed($world,'2026-11-24');
$first=$clock->read($world);
wp25($first['state']['france_parliament_enabled']===true
    && $first['state']['countries']===['DE','FR','US']
    && $first['state']['coherent']===true,
    'pinned French constitutional calendar registered alongside three country institutions');
wp25((int)$pdo->query(
    "SELECT COUNT(*) FROM wp_world_clock_fr_parliament
     WHERE world_id='federated-world'"
)->fetchColumn()===1,
    'French parliamentary presence is persisted in world configuration');
$targetDay='2026-12-15';
$key='world-tick-france-budget-001';
wp25reject(
    fn()=>$clock->advance($world,$targetDay,$key,$first['etag']),
    'world clock stops when trusted French parliament denies constitutional tick'
);
$pendingFence=$pdo->prepare(
    'SELECT state,pending_key,target_day,controller_id
     FROM wp_world_mutation_fences WHERE world_id=?'
);
$pendingFence->execute([$world]);
$fenceRecord=$pendingFence->fetch(PDO::FETCH_ASSOC);
wp25($fenceRecord['state']==='advancing'
    && $fenceRecord['target_day']===$targetDay
    && $fenceRecord['controller_id']===$scheduler->actorId(),
    'saga declares a durable, identity-bound WP27 writer fence before partial subsystem commits');
function wp25fenced(callable $callback,string $description):void
{
    try {$callback();}
    catch (\DomainException $error) {
        wp25(str_contains($error->getMessage(),'World transition in progress'),
            $description);
        return;
    }
    throw new \RuntimeException('Expected WorldMutationFence refusal: '.$description);
}
wp25fenced(fn()=>$orgStore->execute(
    $world,'apply',[],
    'blocked-organization-decision-01',$orgStore->read($world)['etag']
),'independent international organization command is fenced during partial world tick');
wp25fenced(fn()=>$dipStore->execute(
    $world,'propose',[],
    'blocked-diplomatic-command-01',$dipStore->read($world)['etag']
),'independent diplomacy write cannot change a partially advanced world');
wp25fenced(fn()=>$ecoStore->execute(
    $world,'propose',[],
    'blocked-economic-command-01',$ecoStore->read($world)['etag']
),'economic proposals cannot race against worldwide economic clock');
wp25fenced(fn()=>$frStore->execute(
    $world,'engage',[
        'bill_id'=>'fr-budget-extra',
        'category'=>'finance',
        'at'=>$frPrincipal->now
    ],'blocked-french-cabinet-decision',$frStore->read($world)['etag']
),'French Government cannot start a second constitutional act during world transition');
$pending=$clock->read($world);
wp25($pending['state']['pending_day']===$targetDay
    && $pending['state']['committed_day']==='2026-11-24'
    && $pending['state']['coherent']===false
    && $pending['state']['france_parliament_completed']===false,
    'constitutionally blocked tick cannot publish next global calendar day');
wp25($pending['state']['completed_country_clocks']===['DE','FR'],
    'Germany and formal French national calendar are durably checkpointed');
wp25($institutions->read($world,'US')['state']['day']==='2026-11-24'
    && $dipStore->read($world)['state']['day']==='2026-11-24',
    'USA and international diplomacy remain on previous date during French constitutional blocker');
wp25($frStore->read($world)['state']['current_bill']['status']==='censure_window_open',
    'France 49.3 remains unresolved before trustworthy parliament clock advancement');

$frPrincipal->clockAllowed=true;
$frPrincipal->now='2026-12-15T09:00:00+01:00';
wp25reject(
    fn()=>$clock->advance($world,$targetDay,$key,$first['etag']),
    'French parliamentary date cannot advance to a timestamp not independently certified by authority'
);
wp25($clock->read($world)['state']['coherent']===false,
    'unverified clock timestamp leaves global date pending and unreleased');
$frPrincipal->now='2026-12-15T23:59:59+01:00';
$frBefore=$frStore->read($world);
$componentKey='tick-'.substr(hash('sha256',
    $world."\n".$targetDay."\nfrance-parliament\nwp17"
),0,32);
$precommitted=$frStore->execute(
    $world,'clock',['at'=>$frPrincipal->now],
    $componentKey,$frBefore['etag']
);
wp25($precommitted['status']===200
    && $precommitted['result']['now']===
        FrenchConstitutionalCrisis::instant($frPrincipal->now),
    'simulate crash between French legal clock commit and world checkpoint');
wp25((int)$pdo->query(
    'SELECT COUNT(*) FROM wp_world_clock_fr_parliament_steps'
)->fetchColumn()===0,
    'world checkpoint is still absent after independent France domain commit');
wp25($frStore->read($world)['state']['history'][0]['status']
        ==='adopted_by_assembly',
    'without any censure motion the French finance bill is adopted by Assembly after statutory window');

$worker=new WorldTickRecoveryWorker($connect(),
    static fn(string $targetWorld):WorldClockCoordinator=>
        $targetWorld===$world
            ?$clock
            :throw new \DomainException('No trusted coordinator for this world')
);
$recoveredByWorker=$worker->runOnce(5);
wp25(count($recoveredByWorker)===1
    && $recoveredByWorker[0]['status']==='recovered'
    && $recoveredByWorker[0]['committed_day']===$targetDay,
    'trusted WP27 worker resumes a saved tick without original client idempotency key');
wp25($worker->runOnce(5)===[] && $clock->resumePending($world)===null,
    'bounded WP27 recovery queue is empty after all audited checkpoints publish the day');
$api=new WorldClockApi($clock);
$url='/api/v1/worlds/'.$world.'/clock';
$resumed=$api->handle('POST',$url.'/advances',[
    'target_day'=>$targetDay
],[
    'Idempotency-Key'=>$key,
    'If-Match'=>$first['etag']
]);
wp25($resumed['status']===200
    && $resumed['body']['coherent']===true
    && $resumed['body']['france_parliament_enabled']===true,
    'resumed world tick publishes France plus country institutions and global subsystems');
$after=$clock->read($world);
$releasedFence=$pdo->prepare(
    'SELECT state,pending_key,controller_id FROM wp_world_mutation_fences
     WHERE world_id=?'
);
$releasedFence->execute([$world]);
$fenceReleased=$releasedFence->fetch(PDO::FETCH_ASSOC);
wp25($fenceReleased['state']==='idle'
    && $fenceReleased['pending_key']===null
    && $fenceReleased['controller_id']===null,
    'authoritative world-day publication releases WP27 fence atomically');
$ordinary=$connect();
$ordinary->beginTransaction();
\Aeternum\WorldPolitics\WorldMutationFence::admit(
    $ordinary,$world,'authenticated-human-principal','nominate'
);
$ordinary->commit();
wp25(true,'ordinary political decisions become writable again after coherent publication');
wp25($after['state']['committed_day']===$targetDay
    && $after['state']['pending_day']===null,
    'global clock commits once all components are consistent with Paris-local date');
$step=$pdo->prepare(
    'SELECT recovered,after_instant FROM wp_world_clock_fr_parliament_steps
     WHERE world_id=?'
);
$step->execute([$world]);
$stepRow=$step->fetch(PDO::FETCH_ASSOC);
wp25((int)$stepRow['recovered']===1
    && (int)$stepRow['after_instant']===
        FrenchConstitutionalCrisis::instant('2026-12-15T23:59:59+01:00'),
    'French domain transaction is recognized from durable legal snapshot without duplicate execution');
wp25((int)$pdo->query(
    "SELECT COUNT(*) FROM wp_fr_constitutional_journal
     WHERE operation='clock'"
)->fetchColumn()===1,
    'recovery does not duplicate French constitutional passage-of-time journal');
wp25($api->handle('POST',$url.'/advances',[
    'target_day'=>$targetDay
],[
    'Idempotency-Key'=>$key,'If-Match'=>'"stale"'
])===$resumed,'same worldwide clock command replays idempotently after restart');
$scheduler->clockEnabled=false;
wp25($api->handle('POST',$url.'/advances',[
    'target_day'=>$targetDay
],[
    'Idempotency-Key'=>$key,'If-Match'=>'"stale"'
])['status']===403,
    'revoked scheduler cannot obtain prior completed France parliamentary tick receipt');
$scheduler->clockEnabled=true;
wp25reject(fn()=>(new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,
    $institutions,['FR','DE','US']
))->read($world),
    'restarting without pinned French parliament store must fail closed');
$wrongFrench=new class implements FrenchConstitutionalPrincipal {
    public function actorId():string{return 'different-unverified-scheduler';}
    public function allows(string $world,string $ability):bool{return true;}
    public function councilDeliberated(string $world,string $bill):bool{return true;}
    public function verifiedBillCategory(string $world,string $bill):string{return 'finance';}
    public function verifiedSignatures(string $world,string $motion):int{return 58;}
    public function verifiedVotesFor(string $world,string $motion):int{return 0;}
    public function verifiedInstant(string $world,string $operation):string {
        return '2026-12-15T23:59:59+01:00';
    }
};
$foreign=new WorldClockCoordinator(
    $connect(),$orgStore,$dipStore,$ecoStore,$scheduler,$institutions,
    ['FR','DE','US'],
    new PdoFrenchConstitutionalStore($connect(),$wrongFrench)
);
wp25reject(fn()=>$foreign->read($world),
    'world clock cannot borrow a different server identity for French parliament');
$frPrincipal->now='2027-03-28T23:59:59+02:00';
$next=$api->handle('POST',$url.'/advances',[
    'target_day'=>'2027-03-28'
],[
    'Idempotency-Key'=>'france-dst-transition-2027',
    'If-Match'=>$resumed['headers']['ETag']
]);
wp25($next['status']===200
    && $next['body']['revision']===2
    && $frStore->read($world)['state']['now']===
        FrenchConstitutionalCrisis::instant('2027-03-28T23:59:59+02:00'),
    'Europe/Paris clock observes actual DST spring offset on later world day');
wp25((int)$pdo->query(
    "SELECT COUNT(*) FROM wp_world_clock_fr_parliament_steps"
)->fetchColumn()===2,
    'each global day receives one source-pinned French parliamentary checkpoint');
wp25((int)$pdo->query(
    "SELECT COUNT(*) FROM wp_fr_constitutional_journal WHERE operation='clock'"
)->fetchColumn()===2,
    'two completed world advancements produce exactly two French day-close events');
$frPrincipal->now='2027-10-31T23:59:59+01:00';
$fall=$api->handle('POST',$url.'/advances',[
    'target_day'=>'2027-10-31'
],[
    'Idempotency-Key'=>'france-dst-autumn-2027',
    'If-Match'=>$next['headers']['ETag']
]);
wp25($fall['status']===200
    && $fall['body']['revision']===3
    && $frStore->read($world)['state']['now']===
        FrenchConstitutionalCrisis::instant('2027-10-31T23:59:59+01:00'),
    'France parliamentary calendar also respects the autumn Paris UTC offset');
wp25((int)$pdo->query(
    "SELECT COUNT(*) FROM wp_fr_constitutional_journal WHERE operation='clock'"
)->fetchColumn()===3,
    'repeated DST-aware game-world advancement cannot duplicate constitutional votes or clock entries');
echo "WP25 France parliamentary world-clock saga and DST crash recovery passed.\n";
