<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/VerifiedFrenchParliamentPrincipal.php';
require __DIR__.'/../games/world-politics/src/FrenchParliamentApi.php';

use Aeternum\WorldPolitics\{
    FrenchParliament,FrenchParliamentaryPrincipal,FrenchAssemblyAuthorizer,
    PdoFrenchConstitutionalStore,FrenchConstitutionalCrisis,
    PdoFrenchParliamentStore,VerifiedFrenchParliamentPrincipal,
    FrenchParliamentApi
};

function wp28db(bool $valid,string $label):void {
    if (!$valid) throw new RuntimeException('FAILED WP28 DB: '.$label);
    echo "PASS WP28 DB: ".$label."\n";
}
function wp28dbReject(callable $f,string $label):void {
    try{$f();}
    catch(\DomainException|\InvalidArgumentException|\UnexpectedValueException $e) {
        wp28db(true,$label);return;
    }
    throw new RuntimeException('Expected refusal: '.$label);
}
if (getenv('WP_TEST_ISOLATED')!=='YES'
    || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP28 test only uses explicitly isolated wp_test MariaDB\n");exit(2);
}
$dsn=getenv('WP_TEST_DSN');$user=getenv('WP_TEST_USER');
$password=getenv('WP_TEST_PASSWORD');
$connect=static fn():PDO=>new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
foreach([
    'wp_fr_parliament_journal','wp_fr_parliament_receipts','wp_fr_parliament_states',
    'wp_fr_constitutional_journal','wp_fr_constitutional_receipts',
    'wp_fr_constitutional_states'
] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach(['009_fr_constitutional_49_3.sql','015_fr_parliament.sql'] as $file) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',
        file_get_contents(__DIR__.'/../games/world-politics/database/'.$file));
    foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') {
        $pdo->exec(trim($statement));
    }
}
$world='fr-wp28-world';
$groups=[
    [
        'id'=>'cabinet','name'=>'Fictional cabinet parties',
        'alignment'=>'government',
        'positions'=>[
            'budget'=>600,'social_policy'=>500,'general_policy'=>550,
            'security'=>200,'foreign_policy'=>100
        ],'whip_bps'=>8500
    ],[
        'id'=>'opposition','name'=>'Fictional opposition',
        'alignment'=>'opposition',
        'positions'=>[
            'budget'=>-600,'social_policy'=>-670,'general_policy'=>-580,
            'security'=>-150,'foreign_policy'=>-100
        ],'whip_bps'=>9000
    ],[
        'id'=>'swing','name'=>'Fictional centrist independents',
        'alignment'=>'nonaligned',
        'positions'=>[
            'budget'=>-50,'social_policy'=>-100,'general_policy'=>-50,
            'security'=>40,'foreign_policy'=>0
        ],'whip_bps'=>1500
    ]
];
$deputies=[];
for($n=1;$n<=577;$n++) {
    $deputies[]=[
        'id'=>'deputy-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),
        'seat'=>$n,
        'group'=>$n<=240?'cabinet':($n<=505?'opposition':'swing'),
        'control'=>$n===1?'human':'ai',
        'loyalty_bps'=>7800,'attendance_bps'=>10000,
        'independence'=>$n>505?(($n%7)-3)*70:0
    ];
}
$authority=new class implements FrenchParliamentaryPrincipal,FrenchAssemblyAuthorizer {
    public string $now='2026-11-24T09:00:00+01:00';
    public bool $authorized=true;
    public bool $controlsHuman=true;
    public array $approved=[
        'policy-a'=>[
            'kind'=>'confidence','topic'=>'general_policy',
            'public_pressure'=>0,'concessions'=>['swing'=>500]
        ],
        'motion-one'=>[
            'kind'=>'censure_49_2','topic'=>'social_policy',
            'public_pressure'=>-560,'concessions'=>[]
        ],
        'policy-stale'=>[
            'kind'=>'confidence','topic'=>'general_policy',
            'public_pressure'=>0,'concessions'=>['swing'=>500]
        ]
    ];
    public array $coalitions=[
        'governing-bloc'=>['cabinet','swing'],
        'opposition-bloc'=>['opposition','swing']
    ];
    public array $motionSigners=[];
    public function actorId():string{return 'verified-assembly-clerk-wp28';}
    public function allows(string $world,string $ability):bool {
        return $this->authorized
            && in_array($world,['fr-wp28-world','fr-wp28-stale'],true)
            && in_array($ability,[
                'fr.constitution.read','fr.constitution.clock',
                'fr.49_1.engage','fr.49_1.vote',
                'fr.49_2.file','fr.49_2.vote',
                'fr.49_3.engage','fr.censure.file','fr.censure.vote',
                'fr.assembly.bootstrap','fr.assembly.read',
                'fr.assembly.agenda','fr.assembly.vote',
                'fr.assembly.clerk','fr.assembly.coalition'
            ],true);
    }
    public function controlsDeputy(string $world,string $deputy):bool {
        return $this->controlsHuman && $deputy==='deputy-001';
    }
    public function approvedMatter(string $world,string $matter):array {
        if (!isset($this->approved[$matter])) {
            throw new DomainException('Unapproved scenario policy from service');
        }
        return $this->approved[$matter];
    }
    public function verifiedMotionSignatories(string $world,string $motion):array {
        if (!isset($this->motionSigners[$motion])) {
            throw new DomainException('No signed parliamentary motion registry');
        }
        return $this->motionSigners[$motion];
    }
    public function verifiedCoalitionGroups(string $world,string $coalition):array {
        if (!isset($this->coalitions[$coalition])) {
            throw new DomainException('Coalition not authenticated');
        }
        return $this->coalitions[$coalition];
    }
    public function councilDeliberated(string $world,string $bill):bool{return true;}
    public function verifiedBillCategory(string $world,string $bill):string{return 'finance';}
    public function verifiedSignatures(string $world,string $motion):int{return 577;}
    public function verifiedVotesFor(string $world,string $motion):int{return 577;}
    public function verifiedDeputySignatories(string $world,string $motion):array{return [];}
    public function verifiedConfidenceBallot(string $world,string $statement):array {
        return ['for'=>577,'against'=>0,'abstain'=>0];
    }
    public function verifiedSessionKind(string $world,string $session):string{return 'ordinary';}
    public function verifiedInstant(string $world,string $operation):string{return $this->now;}
};

$base=new PdoFrenchConstitutionalStore($connect(),$authority);
$base->seed(new FrenchConstitutionalCrisis(
    $world,'ordinary-2026',
    FrenchConstitutionalCrisis::instant($authority->now),577
));
$parliament=new PdoFrenchParliamentStore($connect(),$authority,$base);
$parliament->seed(new FrenchParliament(
    $world,'fictional-legislature-2026','2026-11-24',
    'fictional_scenario','https://www.assemblee-nationale.fr/',
    $groups,$deputies
));
wp28db((int)$pdo->query(
    "SELECT parliament_required FROM wp_fr_constitutional_states
     WHERE world_id='fr-wp28-world'"
)->fetchColumn()===1,'roster installation atomically makes nominal Assembly votes mandatory');
$api=new FrenchParliamentApi($parliament);
$apiRoot='/api/v1/worlds/'.$world.'/france/assembly';
$published=$api->handle('GET',$apiRoot);
wp28db($published['status']===200
    && $published['body']['occupied_seats']===577,
    'read-only REST returns constitutional roster with all 577 named deputies');
wp28db($api->handle('POST',$apiRoot.'/matters',[
    'matter_id'=>'policy-a','fictional_votes_for'=>577
],[
    'Idempotency-Key'=>'client-forged-policy-a',
    'If-Match'=>$published['headers']['ETag']
])['status']===422,
    'REST rejects fabricated total vote counts and extra policy coefficients');
wp28db($api->handle('POST',$apiRoot.'/matters',[
    'matter_id'=>'policy-a'
])['status']===428,
    'every change requires ETag and idempotency receipt from authenticated client');
$proof=new VerifiedFrenchParliamentPrincipal($authority,$parliament);
$constitution=new PdoFrenchConstitutionalStore($connect(),$proof);
$etag=$base->read($world)['etag'];
wp28dbReject(fn()=>$base->execute($world,'engage_confidence',[
    'statement_id'=>'policy-a','kind'=>'programme','at'=>$authority->now
],'legacy-count-bypass',$etag),
    'legacy clerk with fabricated vote totals can no longer command a pinned Assembly');
$openLegal=$constitution->execute($world,'engage_confidence',[
    'statement_id'=>'policy-a','kind'=>'programme','at'=>$authority->now
],'policy-a-open-49-1',$etag);
wp28db($openLegal['status']===202
    && $openLegal['result']['status']==='awaiting_vote',
    'authenticated constitutional prime minister engages 49(1) confidence');
$state=$parliament->read($world);
$coalition=$parliament->execute($world,'coalition',[
    'coalition_id'=>'governing-bloc','active'=>true
],'coalition-governing-01',$state['etag']);
wp28db($coalition['result']['groups']===['cabinet','swing'],
    'coalition agreement is authenticated by a trusted internal roster');
$opened=$parliament->execute($world,'open_matter',[
    'matter_id'=>'policy-a'
],'open-policy-a',$coalition['etag']);
wp28db($opened['status']===202
    && $opened['result']['source_constitution_sha']
        ===trim($openLegal['etag'],'"'),
    'Assembly vote agenda pinned to exact legal state of Article 49(1)');
wp28dbReject(fn()=>$parliament->execute($world,'open_matter',[
    'matter_id'=>'fictional-unknown'
],'open-unknown-law',$opened['etag']),
    'a parliamentary group cannot create a vote detached from actual legal proceedings');
wp28dbReject(fn()=>$parliament->execute($world,'cast_ballot',[
    'matter_id'=>'policy-a','deputy_id'=>'deputy-002','choice'=>'for'
],'forged-human-deputy',$opened['etag']),
    'user cannot fabricate votes on behalf of uncontrolled AI deputies');
$human=$parliament->execute($world,'cast_ballot',[
    'matter_id'=>'policy-a','deputy_id'=>'deputy-001','choice'=>'for'
],'cast-policy-deputy-001',$opened['etag']);
$authority->controlsHuman=false;
wp28dbReject(fn()=>$parliament->execute($world,'cast_ballot',[
    'matter_id'=>'policy-a','deputy_id'=>'deputy-001','choice'=>'for'
],'cast-policy-deputy-001','"obsolete"'),
    'revocation blocks replay of a previously accepted human ballot');
$authority->controlsHuman=true;
wp28db($parliament->execute($world,'cast_ballot',[
    'matter_id'=>'policy-a','deputy_id'=>'deputy-001','choice'=>'for'
],'cast-policy-deputy-001','"obsolete"')===$human,
    'valid replay of an authorized deputy ballot is idempotent after reconnect');
wp28db($api->handle('GET',$apiRoot.'/matters/policy-a')
    ['body']['human_votes']['deputy-001']==='for',
    'REST reads verified individual human ballot without deriving a fake tally');
wp28db($api->handle('POST',$apiRoot.'/ballots',[
    'matter_id'=>'policy-a','deputy_id'=>'deputy-002','choice'=>'for'
],[
    'Idempotency-Key'=>'api-forge-other-human',
    'If-Match'=>$human['etag']
])['status']===403,
    'REST enforces deputy control independently of the payload');
$resolved=$parliament->execute($world,'resolve',[
    'matter_id'=>'policy-a'
],'resolve-policy-a-49-1',$human['etag']);
wp28db($resolved['status']===200
    && array_sum($resolved['result']['tally'])===577
    && count($resolved['result']['roll_call'])===577,
    'clerk validates all 577 individual, deterministic human/AI ballots');
$authority->now='2026-11-24T10:00:00+01:00';
$legalVote=$constitution->execute($world,'vote_confidence',[
    'statement_id'=>'policy-a','at'=>$authority->now
],'constitutional-confidence-vote',$openLegal['etag']);
wp28db($legalVote['status']===200
    && $legalVote['result']['votes_for']===$resolved['result']['tally']['for']
    && $legalVote['result']['votes_against']===$resolved['result']['tally']['against']
    && $legalVote['result']['abstentions']===$resolved['result']['tally']['abstain'],
    'Article 49(1) vote now uses proven deputy-by-deputy count, never forged base principal totals');
wp28db($legalVote['result']['regime_changed']===false
    && $legalVote['result']['confidence']==='approved',
    'supported parliamentary coalition approves programme without regime change');

$authority->motionSigners['motion-one']=array_map(
    static fn(int $n):string=>'deputy-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),
    range(241,298)
);
$latestParliament=$parliament->read($world);
$sealed=$parliament->execute($world,'seal_signatures',[
    'motion_id'=>'motion-one'
],'seal-real-elected-deputies',$latestParliament['etag']);
wp28db(count($sealed['result'])===58,
    'verified Assembly signature list comes from 58 actually occupied seats');
$authority->now='2026-11-25T09:00:00+01:00';
$file=$constitution->execute($world,'file_spontaneous',[
    'motion_id'=>'motion-one','at'=>$authority->now
],'file-article-49-2',$legalVote['etag']);
wp28db($file['result']['signatories_count']===58,
    'Constitution 49(2) checks elected deputy identities and their signature quota');
$currentParliament=$parliament->read($world);
$oppositionCoalition=$parliament->execute($world,'coalition',[
    'coalition_id'=>'governing-bloc','active'=>false
],'end-governing-accord',$currentParliament['etag']);
$newCoalition=$parliament->execute($world,'coalition',[
    'coalition_id'=>'opposition-bloc','active'=>true
],'new-opposition-accord',$oppositionCoalition['etag']);
$matter=$parliament->execute($world,'open_matter',[
    'matter_id'=>'motion-one'
],'open-censure-matter',$newCoalition['etag']);
$humanCensure=$parliament->execute($world,'cast_ballot',[
    'matter_id'=>'motion-one','deputy_id'=>'deputy-001','choice'=>'against'
],'cast-censure-opposition',$matter['etag']);
$censure=$parliament->execute($world,'resolve',[
    'matter_id'=>'motion-one'
],'resolve-actual-censure-rollcall',$humanCensure['etag']);
wp28db($censure['result']['adopted']
    && $censure['result']['tally']['for']>=289
    && $censure['result']['regime_changed']===false,
    'social pressure plus opposition coalition can yield censure majority without automatic revolution');
$authority->now='2026-11-27T09:00:00+01:00';
$governmentVote=$constitution->execute($world,'vote_spontaneous',[
    'motion_id'=>'motion-one','at'=>$authority->now
],'article-49-2-government-censure',$file['etag']);
wp28db($governmentVote['result']['government']==='resignation_required'
    && $governmentVote['result']['motion_status']==='adopted'
    && $governmentVote['result']['verified_votes_for']===$censure['result']['tally']['for'],
    'certified Article 49(2) majority removes cabinet but leaves Fifth Republic intact');
$reopen=new PdoFrenchParliamentStore($connect(),$authority,$base);
wp28db(
    $reopen->certifiedResult($world,'policy-a','confidence')
        ['tally']===$resolved['result']['tally']
    && $reopen->certifiedResult($world,'motion-one','censure_49_2')
        ['tally']===$censure['result']['tally'],
    'separate MariaDB connection reconstructs immutable nominal votes across coalition switch');
wp28dbReject(fn()=>$parliament->execute($world,'resolve',[
    'matter_id'=>'motion-one'
],'resolve-censure-again',$censure['etag']),
    'verified vote cannot be rerun after it has become an immutable historical result');

// Separate world: an intervening constitutional event makes an already
// resolved Assembly roll call stale and constitutionally unadmissible.
$staleWorld='fr-wp28-stale';
$base->seed(new FrenchConstitutionalCrisis(
    $staleWorld,'ordinary-2026',
    FrenchConstitutionalCrisis::instant('2026-11-24T09:00:00+01:00'),577
));
$parliament->seed(new FrenchParliament(
    $staleWorld,'fictional-legislature-2026','2026-11-24',
    'fictional_scenario','https://www.assemblee-nationale.fr/',
    $groups,$deputies
));
$staleStart=$constitution->read($staleWorld);
$authority->now='2026-11-24T09:00:00+01:00';
$staleConfidence=$constitution->execute($staleWorld,'engage_confidence',[
    'statement_id'=>'policy-stale','kind'=>'programme','at'=>$authority->now
],'stale-world-confidence',$staleStart['etag']);
$staleRoster=$parliament->read($staleWorld);
$staleMatter=$parliament->execute($staleWorld,'open_matter',[
    'matter_id'=>'policy-stale'
],'stale-world-open-agenda',$staleRoster['etag']);
$staleHuman=$parliament->execute($staleWorld,'cast_ballot',[
    'matter_id'=>'policy-stale','deputy_id'=>'deputy-001','choice'=>'for'
],'stale-world-human-vote',$staleMatter['etag']);
$parliament->execute($staleWorld,'resolve',[
    'matter_id'=>'policy-stale'
],'stale-world-resolve',$staleHuman['etag']);
$authority->now='2026-11-24T10:00:00+01:00';
$mutated=$constitution->execute($staleWorld,'clock',[
    'at'=>$authority->now
],'stale-world-intervening-event',$staleConfidence['etag']);
wp28dbReject(fn()=>$constitution->execute($staleWorld,'vote_confidence',[
    'statement_id'=>'policy-stale','at'=>$authority->now
],'stale-world-rejected-proof',$mutated['etag']),
    'legal state change after roll call prevents reuse of a stale constitutional ballot proof');
wp28dbReject(fn()=>$parliament->execute($staleWorld,'resolve',[
    'matter_id'=>'policy-stale'
],'stale-world-second-resolution',$parliament->read($staleWorld)['etag']),
    'completed roll call cannot be resimulated to hide an intervening government event');
$authority->authorized=false;
wp28dbReject(fn()=>$reopen->read($world),
    'revoked Assembly clerk loses read access to archived roll calls');
$authority->authorized=true;
$pdo->exec(
    "UPDATE wp_fr_parliament_states SET state_sha=REPEAT('0',64)
     WHERE world_id='fr-wp28-stale'"
);
wp28dbReject(fn()=>$parliament->read($staleWorld),
    'corrupted parliamentary archive checksum is rejected');

echo "WP28 nominal Assembly votes, verified constitutional bridge and MariaDB replay passed.\n";
