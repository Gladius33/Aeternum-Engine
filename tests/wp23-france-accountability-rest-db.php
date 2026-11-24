<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrenchConstitutionalApi.php';

use Aeternum\WorldPolitics\{
    FrenchConstitutionalCrisis,FrenchParliamentaryPrincipal,
    PdoFrenchConstitutionalStore,FrenchConstitutionalApi
};
function wp23(bool $condition,string $label):void {
    if (!$condition) throw new RuntimeException('FAILED WP23: '.$label);
    echo "PASS WP23: ".$label."\n";
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP23 test requires isolated wp_test MariaDB\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$dbUser=getenv('WP_TEST_USER');
$password=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$dbUser,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach([
    'wp_fr_constitutional_journal','wp_fr_constitutional_receipts',
    'wp_fr_constitutional_states'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
$sql=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(
    __DIR__.'/../games/world-politics/database/009_fr_constitutional_49_3.sql'
));
foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') $pdo->exec(trim($statement));

$principal=new class implements FrenchParliamentaryPrincipal {
    public string $now='2026-11-24T09:00:00+01:00';
    public bool $allowed=true;
    public array $signatures=[];
    public array $confidenceVotes=[];
    public array $yesVotes=[];
    public function actorId():string{return 'trusted-france-assembly-clerk';}
    public function allows(string $world,string $ability):bool {
        return $this->allowed
            && in_array($world,['french-trust','french-censure'],true)
            && in_array($ability,[
                'fr.constitution.read','fr.49_3.engage',
                'fr.censure.file','fr.censure.vote',
                'fr.49_1.engage','fr.49_1.vote',
                'fr.49_2.file','fr.49_2.vote',
                'fr.constitution.clock','fr.constitution.session'
            ],true);
    }
    public function councilDeliberated(string $world,string $statement):bool{return true;}
    public function verifiedBillCategory(string $world,string $bill):string{return 'finance';}
    public function verifiedSignatures(string $world,string $motion):int{return 58;}
    public function verifiedVotesFor(string $world,string $motion):int {
        return $this->yesVotes[$motion]??0;
    }
    public function verifiedInstant(string $world,string $operation):string {
        return $this->now;
    }
    public function verifiedDeputySignatories(string $world,string $motion):array {
        return $this->signatures[$motion]??[];
    }
    public function verifiedConfidenceBallot(string $world,string $statement):array {
        return $this->confidenceVotes[$statement]??[
            'for'=>0,'against'=>0,'abstain'=>0
        ];
    }
    public function verifiedSessionKind(string $world,string $session):string {
        return 'ordinary';
    }
};
$store=new PdoFrenchConstitutionalStore($pdo,$principal);
$start=FrenchConstitutionalCrisis::instant($principal->now);
foreach(['french-trust','french-censure'] as $world) {
    $store->seed(new FrenchConstitutionalCrisis(
        $world,'ordinary-session-2026',$start,577
    ));
}
$api=new FrenchConstitutionalApi($store);
$root='/api/v1/worlds/french-trust/france/constitution';
$begin=$api->handle('GET',$root);
wp23($begin['status']===200 && $begin['body']['confidence']===null,
    'GET exposes persisted authentic French parliamentary state');
wp23($api->handle('GET','/api/v1/worlds/unknown/france/constitution')['status']===404,
    'unverified other-world records cannot be enumerated');
$engage=$api->handle('POST',$root.'/confidence/engagements',[
    'statement_id'=>'general-policy-a',
    'kind'=>'general_policy','at'=>$principal->now
],[
    'Idempotency-Key'=>'fr-confidence-open-001',
    'If-Match'=>$begin['headers']['ETag']
]);
wp23($engage['status']===202 && $engage['body']['status']==='awaiting_vote',
    'Article 49(1) confidence debate admitted over documented REST endpoint');
$principal->now='2026-11-24T10:00:00+01:00';
$principal->confidenceVotes['general-policy-a']=[
    'for'=>194,'against'=>364,'abstain'=>15
];
wp23($api->handle('POST',$root.'/confidence/general-policy-a/votes',[
    'at'=>$principal->now,'for'=>577,'against'=>0
],[
    'Idempotency-Key'=>'forged-assembly-votes',
    'If-Match'=>$engage['headers']['ETag']
])['status']===422,
    'player cannot send a fabricated favorable parliamentary vote count');
$denied=$api->handle('POST',$root.'/confidence/general-policy-a/votes',[
    'at'=>$principal->now
],[
    'Idempotency-Key'=>'fr-confidence-vote-001',
    'If-Match'=>$engage['headers']['ETag']
]);
wp23($denied['status']===200
    && $denied['body']['confidence']==='rejected'
    && $denied['body']['government']==='resignation_required'
    && $denied['body']['regime_changed']===false,
    '49(1) defeat requires resignation but never a regime overthrow');
$reopened=new FrenchConstitutionalApi(new PdoFrenchConstitutionalStore(
    $connect(),$principal
));
wp23($reopened->handle('GET',$root)['body']['government']==='resignation_required',
    'second MariaDB connection sees the same government-resignation state');
wp23($reopened->handle('POST',$root.'/confidence/general-policy-a/votes',[
    'at'=>$principal->now
],[
    'Idempotency-Key'=>'fr-confidence-vote-001',
    'If-Match'=>'"obsolete"'
])===$denied,'durable parliamentary vote receipt replays after reconnect');

$other='/api/v1/worlds/french-censure/france/constitution';
$state=$api->handle('GET',$other);
$principal->now='2026-11-25T09:00:00+01:00';
$motion='spontaneous-censure-one';
$principal->signatures[$motion]=array_map(
    static fn(int $n)=>'deputy-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),
    range(1,58)
);
$principal->yesVotes[$motion]=289;
wp23($api->handle('POST',$other.'/spontaneous-motions',[
    'motion_id'=>$motion,'at'=>$principal->now,
    'signed_by'=>array_fill(0,58,'deputy-001')
],[
    'Idempotency-Key'=>'forged-signers-01',
    'If-Match'=>$state['headers']['ETag']
])['status']===422,
    'REST cannot replace server-verified deputy identities with JSON signatures');
$filing=$api->handle('POST',$other.'/spontaneous-motions',[
    'motion_id'=>$motion,'at'=>$principal->now
],[
    'Idempotency-Key'=>'fr-spontaneous-file-01',
    'If-Match'=>$state['headers']['ETag']
]);
wp23($filing['status']===202
    && $filing['body']['signatories_count']===58
    && $filing['body']['earliest_vote_at']===
        FrenchConstitutionalCrisis::instant('2026-11-27T09:00:00+01:00'),
    'Article 49(2) censure filed with 58 real deputies and 48h waiting period');
$principal->now='2026-11-27T08:59:59+01:00';
wp23($api->handle('POST',$other.'/spontaneous-motions/'.$motion.'/votes',[
    'at'=>$principal->now
],[
    'Idempotency-Key'=>'fr-spontaneous-premature',
    'If-Match'=>$filing['headers']['ETag']
])['status']===409,
    'even a quorum of 289 cannot vote one second before statutory 48h');
$principal->now='2026-11-27T09:00:00+01:00';
$success=$api->handle('POST',$other.'/spontaneous-motions/'.$motion.'/votes',[
    'at'=>$principal->now
],[
    'Idempotency-Key'=>'fr-spontaneous-majority',
    'If-Match'=>$filing['headers']['ETag']
]);
wp23($success['status']===200
    && $success['body']['motion_status']==='adopted'
    && $success['body']['government']==='resignation_required'
    && $success['body']['regime_changed']===false,
    'verified 49(2) motion with 289 yes votes censures cabinet but not Fifth Republic');
$read=$api->handle('GET',$other);
wp23($read['body']['spontaneous_motions'][$motion]['status']==='adopted',
    'spontaneous censure persists independently of parliament 49.3 motions');
$events=(int)$pdo->query(
    "SELECT COUNT(*) FROM wp_fr_constitutional_journal"
)->fetchColumn();
wp23($events===4,'four authorized French confidence/censure state transitions journaled');
$principal->allowed=false;
wp23($api->handle('POST',$other.'/spontaneous-motions/'.$motion.'/votes',[
    'at'=>$principal->now
],[
    'Idempotency-Key'=>'fr-spontaneous-majority','If-Match'=>'"stale"'
])['status']===403,'revoked parliamentary clerk cannot replay prior vote receipt');
$principal->allowed=true;
$pdo->exec(
    "UPDATE wp_fr_constitutional_states
     SET state_sha=REPEAT('0',64) WHERE world_id='french-censure'"
);
wp23($api->handle('GET',$other)['status']===503,
    'corrupted constitutional snapshot fails closed without leaking SQL internals');

echo "WP23 French Article 49(1)/49(2) persistent REST/MariaDB passed.\n";
