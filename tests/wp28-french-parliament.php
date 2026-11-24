<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrenchParliament.php';
use Aeternum\WorldPolitics\FrenchParliament;

function wp28(bool $condition,string $label):void {
    if (!$condition) throw new RuntimeException('FAILED WP28: '.$label);
    echo "PASS WP28: ".$label."\n";
}
function wp28Reject(callable $f,string $label):void {
    try{$f();}
    catch(\DomainException|\InvalidArgumentException|\UnexpectedValueException $e){
        wp28(true,$label);return;
    }
    throw new RuntimeException('Expected refusal: '.$label);
}
$positions=static function(int $score):array {
    return [
        'budget'=>$score,
        'social_policy'=>$score,
        'general_policy'=>$score,
        'security'=>$score,
        'foreign_policy'=>$score
    ];
};
$groups=[
    ['id'=>'cabinet','name'=>'Groupe du gouvernement (fictif)','alignment'=>'government',
     'positions'=>$positions(360),'whip_bps'=>8000],
    ['id'=>'opposition','name'=>'Opposition parlementaire (fictive)','alignment'=>'opposition',
     'positions'=>$positions(-570),'whip_bps'=>9000],
    ['id'=>'swing','name'=>'Indépendants et groupe charnière (fictifs)','alignment'=>'nonaligned',
     'positions'=>$positions(-70),'whip_bps'=>1800]
];
$deputies=[];
for($n=1;$n<=577;$n++) {
    $group=$n<=240?'cabinet':($n<=505?'opposition':'swing');
    $deputies[]=[
        'id'=>'deputy-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),
        'seat'=>$n,'group'=>$group,
        'control'=>$n===1?'human':'ai',
        'loyalty_bps'=>8000,
        'attendance_bps'=>10000,
        'independence'=>$n>505?(($n%7)-3)*100:0
    ];
}
$roster=new FrenchParliament(
    'fr-wp28-test','fictional-legislature-2026','2026-11-24',
    'fictional_scenario','https://www.assemblee-nationale.fr/',
    $groups,$deputies
);
wp28($roster->snapshot()['occupied_seats']===577
    && count($roster->snapshot()['deputies'])===577
    && $roster->snapshot()['provenance']==='fictional_scenario',
    '577 occupied seats, unique member identities and explicit fictional provenance');
wp28Reject(fn()=>new FrenchParliament(
    'fr-wp28-test','fictional-legislature-2026','2026-11-24',
    'fictional_scenario','https://www.assemblee-nationale.fr/',
    $groups,array_merge($deputies,[$deputies[1]])
),'duplicate seat or ID cannot invent an additional deputy');
$groupIDs=['cabinet','swing'];
$coalition=$roster->coalition('governing-alliance',$groupIDs,true);
wp28($coalition['active']===true && $coalition['groups']===$groupIDs,
    'explicit coalition is recorded independently of existing government');
wp28Reject(
    fn()=>$roster->coalition('second-coalition',['swing','opposition'],true),
    'one parliamentary group cannot belong to two active coalitions');
$sha=str_repeat('a',64);
$opened=$roster->openMatter(
    'confidence-programme','confidence','general_policy',
    0,['swing'=>550],$sha
);
wp28($opened['status']==='awaiting_ballots'
    && $opened['source_constitution_sha']===$sha,
    'programme confidence is anchored to specific constitutional revision');
wp28Reject(
    fn()=>$roster->resolve('confidence-programme'),
    'human player must cast or explicitly declare absence before resolving roll call'
);
wp28Reject(
    fn()=>$roster->humanBallot('confidence-programme','deputy-002','for'),
    'a player cannot cast a ballot for an AI-controlled deputy');
$roster->humanBallot('confidence-programme','deputy-001','for');
wp28Reject(
    fn()=>$roster->humanBallot('confidence-programme','deputy-001','against'),
    'nominative human vote is sealed and cannot be silently rewritten');
$approved=$roster->resolve('confidence-programme');
wp28($approved['kind']==='confidence'
    && $approved['occupied_seats']===577
    && array_sum($approved['tally'])===577
    && $approved['majority_required']===null
    && $approved['regime_changed']===false,
    'Article 49(1) independently counts all deputies and expressed-vote majority');
wp28($approved['tally']['for']>0 && $approved['tally']['against']>0,
    'individual deputy decisions yield genuine political disagreement');
wp28($approved['adopted']===($approved['tally']['for']>$approved['tally']['against']),
    '49(1) confidence applies majority of expressed votes, not 289 mandatory threshold');
wp28Reject(fn()=>$roster->resolve('confidence-programme'),
    'signed roll call is immutable after parliamentary validation');
$restored=FrenchParliament::restore($roster->snapshot());
wp28($restored->hash()===$roster->hash()
    && $restored->result('confidence-programme')===$approved,
    'full individual vote audit can reconstruct identical deterministic scenario');

$roster->coalition('governing-alliance',$groupIDs,false);
$roster->coalition('alternative-alliance',['swing','opposition'],true);
$roster->signatures('censure-social',array_map(
    static fn(int $n):string=>'deputy-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),
    range(250,307)
));
wp28(count($roster->signatories('censure-social'))===58,
    '58 distinct actual deputies provide individually verified spontaneous motion signatories');
wp28Reject(fn()=>$roster->signatures('fake-motion',[
    'deputy-001','deputy-001'
]),'duplicate deputy signatures cannot inflate a constitutional quorum');
wp28Reject(fn()=>$roster->signatures('fake-motion',[
    'deputy-599'
]),'non-elected actor cannot sign a parliamentary motion');
$roster->openMatter(
    'censure-social','censure_49_2','social_policy',
    -300,['cabinet'=>0],str_repeat('b',64)
);
$roster->humanBallot('censure-social','deputy-001','against');
$censure=$roster->resolve('censure-social');
wp28($censure['majority_required']===289
    && $censure['adopted']===($censure['tally']['for']>=289)
    && array_sum($censure['tally'])===577,
    'Article 49(2) censure requires absolute majority of occupied seats only');
wp28($censure['tally']['for']>$approved['tally']['against'],
    'societal pressure and changed coalition can materially alter opposition support');
$replay=FrenchParliament::restore($roster->snapshot());
wp28($replay->hash()===$roster->hash()
    && $replay->result('censure-social')===$censure,
    'new coalition, censure votes and constitutional source survive deterministic replay');

$vacant=$deputies;
array_pop($vacant);
$withVacancy=new FrenchParliament(
    'fr-wp28-test','fictional-legislature-2026','2026-11-24',
    'fictional_scenario','https://www.assemblee-nationale.fr/',
    $groups,$vacant
);
$withVacancy->openMatter(
    'censure-vacancy','censure_49_3','budget',0,[],str_repeat('c',64)
);
$withVacancy->humanBallot('censure-vacancy','deputy-001','abstain');
$resultVacancy=$withVacancy->resolve('censure-vacancy');
wp28($resultVacancy['occupied_seats']===576
    && $resultVacancy['majority_required']===289,
    'vacant seat is excluded and absolute-majority threshold uses occupied seats');
$corrupt=$roster->snapshot();
$corrupt['matters']['censure-social']['tally']['for']=0;
wp28Reject(fn()=>FrenchParliament::restore($corrupt),
    'corrupted tally fails deterministic archive reconstruction');
$corrupt=$roster->snapshot();
$corrupt['matters']['censure-social']['roll_call']['deputy-001']='for';
wp28Reject(fn()=>FrenchParliament::restore($corrupt),
    'tampered parliamentary individual roll call fails closed');
echo "WP28 headless Assembly group, coalitions, votes, identity and archive tests passed.\n";
