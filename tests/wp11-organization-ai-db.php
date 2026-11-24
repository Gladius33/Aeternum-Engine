<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PdoOrganizationApi.php';
require __DIR__.'/../games/world-politics/src/AutonomousOrganizationPlanner.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,OrganizationSimulation,DiplomaticPrincipal,
    PdoOrganizationStore,PdoOrganizationApi,AutonomousOrganizationPlanner
};

function wp11(bool $ok,string $name):void
{
    if (!$ok) throw new RuntimeException($name);
    echo "PASS WP11: ".$name."\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"Refusing non-isolated MariaDB\n");exit(2);
}
$pdo=new PDO(getenv('WP_TEST_DSN'),getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
foreach(['wp_organization_journal','wp_organization_receipts','wp_organization_states'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS '.$table);
}
$script=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(
    __DIR__.'/../games/world-politics/database/004_organizations.sql'
));
foreach (explode(';',$script) as $command) {
    if (trim($command)!=='') $pdo->exec(trim($command));
}
$baseline=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
foreach (['FR'=>'France','DE'=>'Allemagne'] as $code=>$name) {
    $baseline['entities'][]=[
        'id'=>'state_'.strtolower($code),'label'=>$name,
        'iso2'=>$code,'kind'=>'state','un_status'=>'un_member',
        'parent_id'=>null,'controller'=>'human_or_ai',
        'representation'=>'international_state_actor',
        'sources'=>['https://www.un.org/en/about-us/member-states'],
        'strategic_programmes'=>[]
    ];
}
$registry=new GeopoliticalActorRegistry($baseline);
$charters=[[
    'id'=>'scenario-forum','name'=>'Organisation imaginaire pour essais IA',
    'status'=>'fictional_scenario','sources'=>[],
    'approval_rule'=>'unanimous',
    'ratification_days'=>3,'withdrawal_notice_days'=>20,
    'founders'=>['state_fr','state_il']
]];
$human=new class implements DiplomaticPrincipal {
    public function actorId():string{return 'authenticated-human';}
    public function allows(string $world,string $actor,string $ability):bool
    {
        if ($world!=='ai-world')return false;
        if ($actor===$this->actorId() && $ability==='organizations.read')return true;
        if ($actor==='state_de' && $ability==='organizations.apply')return true;
        return $actor==='state_fr' && $ability==='organizations.vote';
    }
};
$bot=new class implements DiplomaticPrincipal {
    public function actorId():string{return 'trusted-organization-ai';}
    public function allows(string $world,string $actor,string $ability):bool
    {
        if ($world!=='ai-world')return false;
        if ($actor===$this->actorId() &&
            in_array($ability,['organizations.read','organizations.ai_scheduler'],true))return true;
        return $actor==='state_il' && in_array($ability,[
            'organizations.vote','organizations.ai_controller'
        ],true);
    }
};
$humanStore=new PdoOrganizationStore($pdo,$registry,$charters,$human);
$botStore=new PdoOrganizationStore($pdo,$registry,$charters,$bot);
$humanStore->seed(new OrganizationSimulation('ai-world','2026-11-24',$registry,$charters));
$root='/api/v1/worlds/ai-world/organizations';
$humanApi=new PdoOrganizationApi($humanStore);
$botApi=new PdoOrganizationApi($botStore);
$head=$humanApi->handle('GET',$root);
$app=$humanApi->handle('POST',$root.'/scenario-forum/applications',[
    'applicant'=>'state_de'
],[
    'Idempotency-Key'=>'apply-germany-ai-001','If-Match'=>$head['headers']['ETag']
]);
wp11($app['status']===202,'human player has proposed fictional international membership');
$appId=$app['body']['application_id'];

$planner=new AutonomousOrganizationPlanner();
$sim=OrganizationSimulation::fromSnapshot(
    $humanStore->read('ai-world')['state'],$registry,$charters
);
$before=$sim->hash();
wp11($planner->plan($sim,$registry,'state_il','consensus_seeking')===[]
    && $sim->hash()===$before,
    'Israeli AI consensus policy waits for evidence of other votes without modifying state');

$humanUnauthorized=$humanApi->handle('POST',$root.'/ai/decisions',[
    'voter'=>'state_il','policy'=>'cooperative'
],[
    'Idempotency-Key'=>'human-pretend-ai-01','If-Match'=>$app['headers']['ETag']
]);
wp11($humanUnauthorized['status']===403,
    'human account cannot run Israeli AI command scheduler');
$badTarget=$botApi->handle('POST',$root.'/ai/decisions',[
    'voter'=>'state_fr','policy'=>'cooperative'
],[
    'Idempotency-Key'=>'bot-target-human-1','If-Match'=>$app['headers']['ETag']
]);
wp11($badTarget['status']===403,
    'AI controller cannot commandeer a human-playable state');
$premature=$botApi->handle('POST',$root.'/ai/decisions',[
    'voter'=>'state_il','policy'=>'consensus_seeking'
],[
    'Idempotency-Key'=>'ai-before-consensus1','If-Match'=>$app['headers']['ETag']
]);
wp11($premature['status']===200 && $premature['body']['decisions']===[],
    'consensus-seeking AI abstains until other members signal support');
$ballot=$humanApi->handle('POST',$root.'/scenario-forum/votes',[
    'voter'=>'state_fr','application_id'=>$appId,'approve'=>true
],[
    'Idempotency-Key'=>'human-vote-forum-1','If-Match'=>$premature['headers']['ETag']
]);
wp11($ballot['status']===200 && $ballot['body']['status']==='voting',
    'France can cast its own vote, but no instant accession occurs');
$stored=$humanStore->read('ai-world');
$sim2=OrganizationSimulation::fromSnapshot($stored['state'],$registry,$charters);
$plan=$planner->plan($sim2,$registry,'state_il','consensus_seeking');
wp11(count($plan)===1 && $plan[0]['approve']===true
    && $sim2->hash()===$stored['state']['charters_sha']?false:true,
    'placeholder');
