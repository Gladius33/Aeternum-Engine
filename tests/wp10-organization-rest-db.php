<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoOrganizationApi.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,OrganizationSimulation,
    PdoOrganizationStore,PdoOrganizationApi,DiplomaticPrincipal
};

function wp10(bool $ok,string $label):void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS WP10: ".$label."\n";
}
function wp10reject(callable $f,string $label):void {
    try {$f();}
    catch (DomainException|InvalidArgumentException|UnexpectedValueException $e) {
        wp10(true,$label); return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"Refusing non-isolated database; use wp_test and WP_TEST_ISOLATED=YES\n");
    exit(2);
}
$dsn=getenv('WP_TEST_DSN');$user=getenv('WP_TEST_USER');$secret=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$user,$secret,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach(['wp_organization_journal','wp_organization_receipts','wp_organization_states'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS '.$table);
}
$sql=preg_replace('/^\s*--[^\n]*$/m','',
    file_get_contents(__DIR__.'/../games/world-politics/database/004_organizations.sql'));
foreach(explode(';',$sql) as $statement) {
    if (trim($statement)!=='') $pdo->exec(trim($statement));
}
$baseline=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
foreach(['FR'=>'France','DE'=>'Allemagne'] as $code=>$label) {
    $baseline['entities'][]=[
        'id'=>'state_'.strtolower($code),'label'=>$label,'iso2'=>$code,
        'kind'=>'state','un_status'=>'un_member','parent_id'=>null,
        'controller'=>'human_or_ai','representation'=>'international_state_actor',
        'sources'=>['https://www.un.org/en/about-us/member-states'],
        'strategic_programmes'=>[]
    ];
}
$registry=new GeopoliticalActorRegistry($baseline);
$charters=[[
    'id'=>'fictional-forum',
    'name'=>'Forum international imaginaire (fixture, pas une véritable organisation)',
    'status'=>'fictional_scenario','sources'=>[],
    'approval_rule'=>'unanimous','ratification_days'=>5,
    'withdrawal_notice_days'=>30,
    'founders'=>['state_fr','state_ir']
]];
$human=new class implements DiplomaticPrincipal {
    public bool $allowed=true;
    public function actorId():string {return 'verified-human';}
    public function allows(string $world,string $actor,string $ability):bool {
        if (!$this->allowed || $world!=='world-org-a') return false;
        if ($actor===$this->actorId() && $ability==='organizations.read') return true;
        if ($actor==='state_de' && in_array($ability,['organizations.apply','organizations.withdraw'],true)) return true;
        return $actor==='state_fr' && $ability==='organizations.vote';
    }
};
$bot=new class implements DiplomaticPrincipal {
    public bool $allowed=true;
    public function actorId():string {return 'server-ai-service';}
    public function allows(string $world,string $actor,string $ability):bool {
        if (!$this->allowed || $world!=='world-org-a') return false;
        if ($actor===$this->actorId()
            && in_array($ability,['organizations.read','organizations.clock','organizations.formalize'],true)) return true;
        return in_array($actor,['state_ir','authority_pa'],true)
            && in_array($ability,[
                'organizations.vote','organizations.apply','organizations.ai_controller'
            ],true);
    }
};
$store=new PdoOrganizationStore($pdo,$registry,$charters,$human);
$workerStore=new PdoOrganizationStore($pdo,$registry,$charters,$bot);
$store->seed(new OrganizationSimulation('world-org-a','2026-11-24',$registry,$charters));
$store->seed(new OrganizationSimulation('world-org-b','2026-11-24',$registry,$charters));
$api=new PdoOrganizationApi($store);
$worker=new PdoOrganizationApi($workerStore);
$root='/api/v1/worlds/world-org-a/organizations';
$read=$api->handle('GET',$root);
wp10($read['status']===200 && isset($read['headers']['ETag'])
    && count($read['body']['organizations'])===1,'REST GET lists pinned organization state');
wp10($api->handle('GET',$root.'/fictional-forum')['status']===200,
    'REST read of single charter');
wp10($api->handle('GET','/api/v1/worlds/world-org-b/organizations')['status']===404,
    'world B hidden without membership');
wp10($api->handle('GET',$root.'/inexistent')['status']===404,
    'unknown organization hidden');
wp10($api->handle('POST',$root.'/fictional-forum/applications',['applicant'=>'state_de'])['status']===428,
    'missing write preconditions denied');
wp10($api->handle('POST',$root.'/fictional-forum/votes',[
    'voter'=>'state_ir','application_id'=>'untrusted','approve'=>true
],[
    'Idempotency-Key'=>'vote-as-iran-00001','If-Match'=>$read['headers']['ETag']
])['status']===403,'human player cannot vote as Iran AI');

$apply=$api->handle('POST',$root.'/fictional-forum/applications',[
    'applicant'=>'state_de'
],[
    'Idempotency-Key'=>'apply-from-germany-01','If-Match'=>$read['headers']['ETag']
]);
wp10($apply['status']===202
    && $apply['body']['status']==='voting'
    && str_starts_with($apply['body']['application_id'],'op-'),
    'applicant creates pending membership vote, not instant entry');
$appId=$apply['body']['application_id'];
$reconnected=new PdoOrganizationApi(
    new PdoOrganizationStore($connect(),$registry,$charters,$human)
);
wp10(isset($reconnected->handle('GET',$root.'/fictional-forum')['body']['applications'][0]),
    'membership application survives database connection restart');
wp10($reconnected->handle('POST',$root.'/fictional-forum/applications',[
    'applicant'=>'state_de'
],[
    'idempotency-key'=>'apply-from-germany-01','if-match'=>'"stale"'
])===$apply,'durable idempotent result survives stale retries');
wp10($api->handle('POST',$root.'/fictional-forum/applications',[
    'applicant'=>'state_ir'
],[
    'Idempotency-Key'=>'apply-from-germany-01','If-Match'=>$apply['headers']['ETag']
])['status']===403,'actor switch cannot reuse authority');
wp10($api->handle('POST',$root.'/fictional-forum/applications',[
    'applicant'=>'state_fr'
],[
    'Idempotency-Key'=>'apply-from-germany-01','If-Match'=>$apply['headers']['ETag']
])['status']===403,'unauthorized application denied independently of stale idempotency');
wp10($api->handle('POST',$root.'/fictional-forum/votes',[
    'voter'=>'state_fr','application_id'=>$appId,'approve'=>true
],[
    'Idempotency-Key'=>'fr-vote-stale-00001','If-Match'=>'"stale"'
])['status']===412,'stale optimistic lock rejected before action');
$fr=$api->handle('POST',$root.'/fictional-forum/votes',[
    'voter'=>'state_fr','application_id'=>$appId,'approve'=>true
],[
    'Idempotency-Key'=>'fr-vote-00000001','If-Match'=>$apply['headers']['ETag']
]);
wp10($fr['status']===200 && $fr['body']['status']==='voting',
    'French vote is counted without automatic membership');
$otherOrgRoute=$worker->handle('POST',$root.'/unknown-organization/votes',[
    'voter'=>'state_ir','application_id'=>$appId,'approve'=>true
],[
    'Idempotency-Key'=>'cross-org-vote-0001','If-Match'=>$fr['headers']['ETag']
]);
wp10($otherOrgRoute['status']===409,'cross-organization ballot route cannot mutate unrelated application');
$iranVote=$worker->handle('POST',$root.'/fictional-forum/votes',[
    'voter'=>'state_ir','application_id'=>$appId,'approve'=>true
],[
    'Idempotency-Key'=>'iran-vote-0000001','If-Match'=>$fr['headers']['ETag']
]);
wp10($iranVote['status']===200
    && $iranVote['body']['status']==='approved_pending_formalization',
    'Iran AI vote advances to charter formalization');
$early=$worker->handle('POST',$root.'/fictional-forum/formalizations',[
    'application_id'=>$appId
],[
    'Idempotency-Key'=>'ratify-early-000001','If-Match'=>$iranVote['headers']['ETag']
]);
wp10($early['status']===409,'formalization cannot bypass five-day charter delay');
wp10($api->handle('POST',$root.'/clock',[
    'target_day'=>'2026-11-29'
],[
    'Idempotency-Key'=>'human-clock-000001','If-Match'=>$iranVote['headers']['ETag']
])['status']===403,'public principal cannot accelerate world clock');
$tick=$worker->handle('POST',$root.'/clock',[
    'target_day'=>'2026-11-29'
],[
    'Idempotency-Key'=>'clock-to-29-000001','If-Match'=>$iranVote['headers']['ETag']
]);
wp10($tick['status']===200,'trusted service advanced simulation date');
$ratify=$worker->handle('POST',$root.'/fictional-forum/formalizations',[
    'application_id'=>$appId
],[
    'Idempotency-Key'=>'ratify-forum-00001','If-Match'=>$tick['headers']['ETag']
]);
wp10($ratify['status']===200 && $ratify['body']['status']==='member',
    'membership becomes effective after consent and delay');
$state=$api->handle('GET',$root.'/fictional-forum');
wp10(in_array('state_de',$state['body']['organization']['members'],true),
    'country is actually a member after ratification');
$leave=$api->handle('POST',$root.'/fictional-forum/withdrawals',[
    'actor'=>'state_de'
],[
    'Idempotency-Key'=>'leave-forum-000001','If-Match'=>$ratify['headers']['ETag']
]);
wp10($leave['status']===202 && $leave['body']['effective_on']==='2026-12-29',
    'withdrawal scheduled with thirty-day charter notice');
$before=$worker->handle('POST',$root.'/clock',[
    'target_day'=>'2026-12-28'
],[
    'Idempotency-Key'=>'clock-28-00000001','If-Match'=>$leave['headers']['ETag']
]);
wp10(in_array('state_de',$api->handle('GET',$root)['body']['organizations']['fictional-forum']['members'],true),
    'membership remains active before notice expires');
$after=$worker->handle('POST',$root.'/clock',[
    'target_day'=>'2026-12-29'
],[
    'Idempotency-Key'=>'clock-29-00000001','If-Match'=>$before['headers']['ETag']
]);
wp10($after['status']===200
    && !in_array('state_de',$api->handle('GET',$root)['body']['organizations']['fictional-forum']['members'],true),
    'automated withdrawal effective on correct date');

$events=(int)$pdo->query("SELECT COUNT(*) FROM wp_organization_journal WHERE world_id='world-org-a'")->fetchColumn();
$receipts=(int)$pdo->query("SELECT COUNT(*) FROM wp_organization_receipts WHERE world_id='world-org-a'")->fetchColumn();
wp10($events===8 && $receipts===8,'eight successful commands are atomic and have unique receipts');
$other=(int)$pdo->query("SELECT COUNT(*) FROM wp_organization_journal WHERE world_id='world-org-b'")->fetchColumn();
wp10($other===0,'no cross-world event leakage');
$human->allowed=false;
wp10($api->handle('GET',$root)['status']===404,'world access revocation applied to GET');
wp10($api->handle('POST',$root.'/fictional-forum/applications',[
    'applicant'=>'state_de'
],[
    'Idempotency-Key'=>'apply-from-germany-01','If-Match'=>'"old"'
])['status']===404,'revoked user cannot obtain old receipt');
$human->allowed=true;
$changed=$charters;$changed[0]['ratification_days']=13;
$misconfigured=new PdoOrganizationStore($connect(),$registry,$changed,$human);
wp10reject(fn()=>$misconfigured->read('world-org-a'),
    'pinned charter changes rejected rather than silently modifying running campaigns');
$pdo->exec("UPDATE wp_organization_states SET state_sha=REPEAT('0',64) WHERE world_id='world-org-a'");
wp10($api->handle('GET',$root)['status']===503
    && $api->handle('GET',$root)['body']['code']==='backend_unavailable',
    'tampered persistent digest fails closed without exposing SQL');
echo "WP10 organization REST and durable MariaDB lifecycle passed.\n";
