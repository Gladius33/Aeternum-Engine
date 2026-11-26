<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoDiplomaticApi.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,DiplomaticSimulation,DiplomaticPrincipal,
    PdoDiplomaticStore,PdoDiplomaticApi
};

function wp8(bool $ok,string $label):void
{
    if (!$ok) throw new \RuntimeException($label);
    echo "PASS WP08: ".$label."\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"Only a dedicated wp_test database is permitted\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');
$user=getenv('WP_TEST_USER')?:'';
$pass=getenv('WP_TEST_PASSWORD')?:'';
$connect=static fn():\PDO=>new \PDO($dsn,$user,$pass,[
    \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach (['wp_diplomatic_outbox','wp_diplomatic_journal','wp_diplomatic_receipts','wp_diplomatic_states'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS '.$table);
}
$sql=preg_replace('/^\s*--[^\n]*$/m','',
    file_get_contents(__DIR__.'/../games/world-politics/database/003_diplomacy.sql'));
foreach(explode(';',$sql) as $statement) {
    if (trim($statement)!=='') $pdo->exec(trim($statement));
}

$extra=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(__DIR__.'/../games/world-politics/database/006_diplomatic_outbox.sql'));
foreach (explode(';',$extra) as $statement) {
    if (trim($statement)!=='') $pdo->exec(trim($statement));
}

$baseline=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
$baseline['entities'][]=[
    'id'=>'state_fr','label'=>'France','iso2'=>'FR','kind'=>'state',
    'un_status'=>'un_member','parent_id'=>null,'controller'=>'human_or_ai',
    'representation'=>'international_state_actor',
    'sources'=>['https://www.un.org/en/about-us/member-states'],
    'strategic_programmes'=>[]
];
$registry=new GeopoliticalActorRegistry($baseline);

$human=new class implements DiplomaticPrincipal {
    public bool $enabled=true;
    public string $world='world-dip-a';
    public function actorId():string {return 'authenticated-player-fr';}
    public function allows(string $world,string $actorId,string $ability):bool
    {
        if (!$this->enabled || $world!==$this->world) return false;
        if ($actorId===$this->actorId() && $ability==='diplomacy.read') return true;
        return $actorId==='state_fr' && $ability==='diplomacy.propose';
    }
};
$ai=new class implements DiplomaticPrincipal {
    public bool $enabled=true;
    public function actorId():string {return 'trusted-ai-worker';}
    public function allows(string $world,string $actorId,string $ability):bool
    {
        if (!$this->enabled || $world!=='world-dip-a') return false;
        if ($actorId===$this->actorId()
            && in_array($ability,['diplomacy.read','diplomacy.ai_scheduler','diplomacy.clock'],true)) return true;
        return in_array($actorId,['state_ir','state_il','state_ps','authority_pa'],true)
            && in_array($ability,['diplomacy.respond','diplomacy.ai_controller','diplomacy.propose'],true);
    }
};
$humanStore=new PdoDiplomaticStore($pdo,$registry,$human);
$botStore=new PdoDiplomaticStore($pdo,$registry,$ai);
$humanStore->seed(new DiplomaticSimulation('world-dip-a','2026-11-24',$registry));
$humanStore->seed(new DiplomaticSimulation('world-dip-b','2026-11-24',$registry));
$api=new PdoDiplomaticApi($humanStore);
$botApi=new PdoDiplomaticApi($botStore);
$root='/api/v1/worlds/world-dip-a/diplomacy';
$initial=$api->handle('GET',$root);
wp8($initial['status']===200 && $initial['body']['world_id']==='world-dip-a',
    'first REST GET loads persisted diplomatic world');
wp8($api->handle('GET',$root.'/actors')['status']===200
    && count($api->handle('GET',$root.'/actors')['body']['actors'])===5,
    'GET actor registry includes distinct Palestine and Authority and France');
wp8($api->handle('GET','/api/v1/worlds/world-dip-b/diplomacy')['status']===404,
    'cross-world diplomatic reads are concealed');

$wrong=$api->handle('POST',$root.'/proposals',[
    'from'=>'state_ir','to'=>'state_fr','action'=>'dialogue'
],[
    'Idempotency-Key'=>'spoof-iran-00001',
    'If-Match'=>$initial['headers']['ETag']
]);
wp8($wrong['status']===403,'player cannot impersonate Iranian diplomatic administration');
wp8($api->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'state_ir','action'=>'dialogue'
])['status']===428,'REST rejects writes lacking idempotency and optimistic lock');

$offer=$api->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'state_ir','action'=>'dialogue'
],[
    'Idempotency-Key'=>'offer-dialogue-00001','If-Match'=>$initial['headers']['ETag']
]);
wp8($offer['status']===202 && str_starts_with($offer['body']['id'],'cmd-'),
    'French player sent a persistent dialogue proposal to Iran');
$reconnect=new PdoDiplomaticStore($connect(),$registry,$human);
$apiAgain=new PdoDiplomaticApi($reconnect);
wp8(isset($apiAgain->handle('GET',$root)['body']['proposals'][$offer['body']['id']]),
    'offer survives process/database reconnection');
wp8($apiAgain->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'state_ir','action'=>'dialogue'
],[
    'Idempotency-Key'=>'offer-dialogue-00001','If-Match'=>'"stale"'
])===$offer,'durable idempotency receipt survives reconnect and stale ETag');
$duplicateChanged=$apiAgain->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'state_ps','action'=>'dialogue'
],[
    'Idempotency-Key'=>'offer-dialogue-00001','If-Match'=>$offer['headers']['ETag']
]);
wp8($duplicateChanged['status']===409,'changed payload cannot reuse an idempotency key');
wp8($api->handle('POST',$root.'/ai/decisions',[
    'responder'=>'state_ir','policy'=>'open_dialogue'
],[
    'Idempotency-Key'=>'ai-command-fraud-01','If-Match'=>$offer['headers']['ETag']
])['status']===403,'human cannot run trusted AI scheduler');
$inbox=$botApi->handle('GET',$root);
$iranAnswer=$botApi->handle('POST',$root.'/ai/decisions',[
    'responder'=>'state_ir','policy'=>'open_dialogue'
],[
    'Idempotency-Key'=>'ai-answer-iran-0001','If-Match'=>$inbox['headers']['ETag']
]);
wp8($iranAnswer['status']===200
    && count($iranAnswer['body']['decisions'])===1
    && $iranAnswer['body']['decisions'][0]['status']==='accepted_in_principle',
    'trusted Iran AI responds deterministically, never signs a treaty');

$next=$api->handle('GET',$root);
$offerPalestine=$api->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'state_ps','action'=>'treaty_proposal'
],[
    'Idempotency-Key'=>'offer-palestine-001','If-Match'=>$next['headers']['ETag']
]);
wp8($offerPalestine['status']===202,'State of Palestine can receive treaty negotiations');
$invalidAuthority=$api->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'authority_pa','action'=>'treaty_proposal'
],[
    'Idempotency-Key'=>'invalid-treaty-pa1','If-Match'=>$offerPalestine['headers']['ETag']
]);
wp8($invalidAuthority['status']===409,'PA is not silently treated as interstate treaty party');

$botLatest=$botApi->handle('GET',$root);
$palestineAI=$botApi->handle('POST',$root.'/ai/decisions',[
    'responder'=>'state_ps','policy'=>'cautious'
],[
    'Idempotency-Key'=>'ai-palestine-00001','If-Match'=>$botLatest['headers']['ETag']
]);
wp8($palestineAI['status']===200
    && $palestineAI['body']['decisions'][0]['status']==='declined',
    'Palestinian AI response can decline fictional treaty proposal');

$state=$api->handle('GET',$root);
$trade=$api->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'state_il','action'=>'trade_cooperation'
],[
    'Idempotency-Key'=>'israel-trade-00001','If-Match'=>$state['headers']['ETag']
]);
wp8($trade['status']===202,'Israel can receive nonmilitary trade overtures');

$human->enabled=false;
wp8($api->handle('GET',$root)['status']===404,'revocation instantly blocks read access');
wp8($api->handle('POST',$root.'/proposals',[
    'from'=>'state_fr','to'=>'state_ir','action'=>'dialogue'
],[
    'Idempotency-Key'=>'offer-dialogue-00001','If-Match'=>'"bad"'
])['status']===404,'revocation also blocks stored idempotency replay');
$human->enabled=true;

$atClock=$botApi->handle('GET',$root);
$tick=$botApi->handle('POST',$root.'/clock',[
    'target_day'=>'2026-12-15'
],[
    'Idempotency-Key'=>'clock-to-dec-15-01','If-Match'=>$atClock['headers']['ETag']
]);
wp8($tick['status']===200
    && in_array($trade['body']['id'],$tick['body']['expired'],true),
    'trusted game clock expires unanswered Israeli offer');
wp8($api->handle('GET',$root)['body']['proposals'][$trade['body']['id']]['status']==='expired',
    'expired offer persists through REST');

$events=(int)$pdo->query("SELECT COUNT(*) FROM wp_diplomatic_journal WHERE world_id='world-dip-a'")->fetchColumn();
$receipts=(int)$pdo->query("SELECT COUNT(*) FROM wp_diplomatic_receipts WHERE world_id='world-dip-a'")->fetchColumn();
wp8($events===6 && $receipts===6,'successful world mutations and receipts are both atomic and unique');
$other=(int)$pdo->query("SELECT COUNT(*) FROM wp_diplomatic_journal WHERE world_id='world-dip-b'")->fetchColumn();
wp8($other===0,'other world never received leaked diplomatic events');
$pdo->exec("UPDATE wp_diplomatic_states SET state_sha=REPEAT('0',64) WHERE world_id='world-dip-a'");
wp8($api->handle('GET',$root)['status']===503
    && $api->handle('GET',$root)['body']['code']==='backend_unavailable',
    'corrupted database digest blocked without leaking SQL detail');

echo "WP08 durable diplomacy REST/MariaDB tests passed.\n";

// WP66-69: actual persistent commitment decisions and maturity events.
$owner66=new class implements DiplomaticPrincipal {
    public bool $enabled=true;public bool $write=true;
    public function actorId():string{return 'commitment-owner';}
    public function allows(string $world,string $actorId,string $ability):bool{
        return $this->enabled&&$world==='world-commitment-test'&&
            (($actorId===$this->actorId()&&$ability==='diplomacy.read')||
             ($this->write&&$actorId==='state_fr'&&$ability==='diplomacy.commitment'));
    }
};
$clock66=new class implements DiplomaticPrincipal {
    public function actorId():string{return 'commitment-clock';}
    public function allows(string $world,string $actorId,string $ability):bool{
        return $world==='world-commitment-test'&&$actorId===$this->actorId()&&in_array($ability,['diplomacy.read','diplomacy.clock'],true);
    }
};
$sim66=new DiplomaticSimulation('world-commitment-test','2027-05-14',$registry);
$sim66->enableDailyReplies();$sim66->enableAidBridge();$sim66->enableCommitments();$sim66->enableCommitmentRisk();
$store66=new PdoDiplomaticStore($connect(),$registry,$owner66);
$clockStore66=new PdoDiplomaticStore($connect(),$registry,$clock66);
$observation66=static function(string $day):array{return ['world_id'=>'world-commitment-test','day'=>$day,
    'prior_day'=>(new DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d'),'domestic_sha'=>str_repeat('a',64),'rate_bps'=>0];};
for($day66=new DateTimeImmutable('2027-05-15');$day66->format('Y-m-d')<='2027-06-12';$day66=$day66->modify('+1 day')){
    $day=$day66->format('Y-m-d');$sim66->advanceTo($day,$clock66,$observation66($day));
}
$store66->seed($sim66);$api66=new PdoDiplomaticApi($store66);$root66='/api/v1/worlds/world-commitment-test/diplomacy/commitments';
$read66=$api66->handle('GET',$root66);
wp8($read66['status']===200&&$read66['body']['risk']['open_arrears_minor']===0,'WP66 persisted opening risk');
$input66=['commitment_id'=>'ua-civil-fixture','action'=>'withhold'];
$h66=['Idempotency-Key'=>'commitment-db-stop-01','If-Match'=>$read66['headers']['ETag']];
$stop66=$api66->handle('POST',$root66,$input66,$h66);
wp8($stop66['status']===200&&!$stop66['body']['legal_release'],'WP66 transactional payment policy');
$owner66->write=false;
wp8($api66->handle('POST',$root66,$input66,$h66)['status']===403,'WP66 revoked write permission blocks receipt replay');
$owner66->write=true;
$after66=$clockStore66->execute('world-commitment-test','clock',['target_day'=>'2027-06-13','aid_observation'=>$observation66('2027-06-13')],'commitment-db-tick-01',$stop66['headers']['ETag']);
$fresh66=new PdoDiplomaticApi(new PdoDiplomaticStore($connect(),$registry,$owner66));
$state66=$fresh66->handle('GET',$root66);
wp8($state66['status']===200&&$state66['body']['totals']['arrears_minor']===1000000000&&$state66['body']['risk']['actor_signals']['state_ua']<0,'WP69 due arrears and risk survive database reconnection');
wp8($fresh66->handle('POST',$root66,$input66,['Idempotency-Key'=>$h66['Idempotency-Key'],'If-Match'=>'"stale"'])===$stop66,'WP66 original receipt survives maturity event');
$resume66=$fresh66->handle('POST',$root66,['commitment_id'=>'ua-civil-fixture','action'=>'resume'],['Idempotency-Key'=>'commitment-db-resume-01','If-Match'=>$state66['headers']['ETag']]);
wp8($resume66['status']===200,'WP66 resume command admitted');
$clockStore66->execute('world-commitment-test','clock',['target_day'=>'2027-06-14','aid_observation'=>$observation66('2027-06-14')],'commitment-db-tick-02',$resume66['headers']['ETag']);
$settled66=$fresh66->handle('GET',$root66);
wp8($settled66['body']['totals']['arrears_minor']===0&&$settled66['body']['risk']['actor_signals']['state_ua']===0&&$settled66['body']['budget_delta_minor']===null,'WP69 scenario arrears settled without fictional treasury effect');
$j66=(int)$pdo->query("SELECT COUNT(*) FROM wp_diplomatic_journal WHERE world_id='world-commitment-test'")->fetchColumn();
$r66=(int)$pdo->query("SELECT COUNT(*) FROM wp_diplomatic_receipts WHERE world_id='world-commitment-test'")->fetchColumn();
wp8($j66===4&&$r66===4,'WP66 four atomic distinct journal and receipt records');

