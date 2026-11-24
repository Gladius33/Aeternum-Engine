<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/FrenchConstitutionalCrisis.php';

use Aeternum\WorldPolitics\{
    FrenchConstitutionalCrisis,FrenchParliamentaryPrincipal
};

function wp22(bool $ok,string $label):void
{
    if (!$ok) throw new RuntimeException('FAILED WP22: '.$label);
    echo "PASS WP22: ".$label."\n";
}
function wp22reject(callable $fn,string $label):void
{
    try {$fn();}
    catch (\DomainException|\InvalidArgumentException $error) {
        wp22(true,$label);return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
$principal=new class implements FrenchParliamentaryPrincipal {
    public array $signatures=[];
    public array $censureVotes=[];
    public array $confidenceVotes=[];
    public bool $council=true;
    public bool $authorized=true;
    public string $sessionKind='ordinary';
    public function actorId():string{return 'authenticated-france-parliament-clerk';}
    public function allows(string $world,string $ability):bool {
        return $world==='fr-constitutional-wp22' && $this->authorized
            && in_array($ability,[
                'fr.49_3.engage','fr.censure.file','fr.censure.vote',
                'fr.constitution.clock','fr.constitution.session',
                'fr.49_1.engage','fr.49_1.vote',
                'fr.49_2.file','fr.49_2.vote'
            ],true);
    }
    public function councilDeliberated(string $world,string $id):bool {
        return $world==='fr-constitutional-wp22' && $this->council;
    }
    public function verifiedBillCategory(string $world,string $id):string {
        return str_contains($id,'budget')?'finance':'ordinary';
    }
    public function verifiedSignatures(string $world,string $id):int{return 58;}
    public function verifiedVotesFor(string $world,string $id):int {
        return $this->censureVotes[$id]??0;
    }
    public function verifiedInstant(string $world,string $operation):string {
        return '2026-11-24T09:00:00+01:00';
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
        return $this->sessionKind;
    }
};
$epoch=FrenchConstitutionalCrisis::instant('2026-11-24T09:00:00+01:00');
$full=static fn()=>new FrenchConstitutionalCrisis(
    'fr-constitutional-wp22','ordinary-2026',$epoch,577
);
$ids=array_map(static fn(int $n):string=>'deputy-'.str_pad(
    (string)$n,3,'0',STR_PAD_LEFT
),range(1,58));

$model=$full();
$principal->council=false;
wp22reject(
    fn()=>$model->engageConfidence($principal,'programme-a','programme',
        '2026-11-24T09:00:00+01:00','no-cabinet'),
    '49(1) requires verified Council of Ministers deliberation'
);
$principal->council=true;
$principal->authorized=false;
wp22reject(
    fn()=>$model->engageConfidence($principal,'programme-a','programme',
        '2026-11-24T09:00:00+01:00','no-pm'),
    '49(1) requires authenticated prime minister authority'
);
$principal->authorized=true;
$opened=$model->engageConfidence(
    $principal,'programme-a','programme','2026-11-24T09:00:00+01:00',
    'engage-programme'
);
wp22($opened['status']==='awaiting_vote',
    'verified general programme can be submitted to Assembly confidence vote');
$principal->confidenceVotes['programme-a']=[
    'for'=>194,'against'=>364,'abstain'=>15
];
$noConfidence=$model->voteConfidence(
    $principal,'programme-a','2026-11-24T10:00:00+01:00','programme-rejected'
);
wp22($noConfidence['confidence']==='rejected'
    && $noConfidence['government']==='resignation_required'
    && $noConfidence['regime_changed']===false,
    '49(1) majority expressed 194 vs 364 rejects policy and applies Article 50 without overthrow');
wp22($model->snapshot()['current_bill']===null
    && $model->snapshot()['confidence']===null,
    'rejected confidence vote has no fictional 49.3 bill');
wp22(FrenchConstitutionalCrisis::restore($model->snapshot())->hash()===$model->hash(),
    '49(1) crisis survives deterministic snapshot reconstruction');
wp22reject(
    fn()=>$model->engageConfidence($principal,'another-programme','programme',
        '2026-11-24T11:00:00+01:00','resigned-cabinet'),
    'resigned government cannot continue submitting confidence motions'
);

$approved=$full();
$approved->engageConfidence($principal,'policy-a','general_policy',
    '2026-11-24T09:00:00+01:00','general-policy');
$principal->confidenceVotes['policy-a']=[
    'for'=>190,'against'=>189,'abstain'=>190
];
$vote=$approved->voteConfidence($principal,'policy-a',
    '2026-11-24T11:00:00+01:00','confidence-approved');
wp22($vote['confidence']==='approved'
    && $vote['government']==='in_office',
    '49(1) confidence succeeds on expressed vote majority despite 190 abstentions');
$invalid=$full();
$invalid->engageConfidence($principal,'policy-b','programme',
    '2026-11-24T09:00:00+01:00','invalid-vote-open');
$principal->confidenceVotes['policy-b']=[
    'for'=>500,'against'=>200,'abstain'=>0
];
wp22reject(
    fn()=>$invalid->voteConfidence($principal,'policy-b',
        '2026-11-24T11:00:00+01:00','fraud-ballot'),
    '49(1) votes cannot exceed number of Assembly seats'
);

$motionState=$full();
foreach(range(1,3) as $n) {
    $fileAt=gmdate('Y-m-d\TH:i:s\Z',$epoch+($n-1)*259200);
    $earlyAt=gmdate('Y-m-d\TH:i:s\Z',$epoch+($n-1)*259200+172799);
    $voteAt=gmdate('Y-m-d\TH:i:s\Z',$epoch+($n-1)*259200+172800);
    $id='spontaneous-'.$n;
    $principal->signatures[$id]=$ids;
    $filed=$motionState->fileSpontaneousMotion(
        $principal,$id,$fileAt,
        'file-spontaneous-'.$n
    );
    wp22($filed['signatories_count']===58 && $filed['per_deputy_limit']===3,
        '49(2) verifies signatories and increments deputy session quota '.$n);
    $principal->censureVotes[$id]=288;
    wp22reject(
        fn()=>$motionState->voteSpontaneousMotion(
            $principal,$id,$earlyAt,
            'early-vote-'.$n
        ),
        '49(2) must wait at least 48 hours after verified filing '.$n
    );
    $result=$motionState->voteSpontaneousMotion(
        $principal,$id,$voteAt,
        'vote-spontaneous-'.$n
    );
    wp22($result['motion_status']==='rejected',
        '288 favorable motions cannot topple government under Article 49(2) '.$n);
}
$principal->signatures['spontaneous-fourth']=$ids;
wp22reject(
    fn()=>$motionState->fileSpontaneousMotion(
        $principal,'spontaneous-fourth',
        '2026-12-02T10:00:00+01:00','illegal-fourth'
    ),
    'same deputy cannot sign fourth spontaneous censure in ordinary session'
);
wp22($motionState->snapshot()['signatory_counters']['deputy-001']===3,
    'per-member motion quota persists even after three rejected ballots');
$principal->signatures['duplicate-motion']=array_fill(0,58,'deputy-001');
wp22reject(
    fn()=>$motionState->fileSpontaneousMotion(
        $principal,'duplicate-motion',
        '2026-12-02T10:00:00+01:00','duplicate-signers'
    ),
    'duplicate signatures cannot forge quorum of one tenth');
$principal->signatures['too-small']=array_slice($ids,0,57);
wp22reject(
    fn()=>$motionState->fileSpontaneousMotion(
        $principal,'too-small',
        '2026-12-02T10:00:00+01:00','too-few-signers'
    ),
    '57 valid deputy signatures are insufficient with 577 members');

$principal->sessionKind='extraordinary';
$motionState->openSession(
    $principal,'extraordinary-2026',
    '2026-12-03T09:00:00+01:00','extraordinary-open'
);
wp22($motionState->snapshot()['session_kind']==='extraordinary'
    && $motionState->snapshot()['signatory_counters']===[],
    'new extraordinary session resets ordinary-session signature counters');
$principal->signatures['extra-one']=$ids;
$motionState->fileSpontaneousMotion(
    $principal,'extra-one',
    '2026-12-03T10:00:00+01:00','extra-file-one'
);
$principal->signatures['extra-two']=$ids;
wp22reject(
    fn()=>$motionState->fileSpontaneousMotion(
        $principal,'extra-two',
        '2026-12-03T10:00:00+01:00','extra-file-two'
    ),
    'one spontaneous motion per deputy in an extraordinary session');
wp22reject(
    fn()=>$motionState->openSession(
        $principal,'extraordinary-next','2026-12-03T11:00:00+01:00',
        'extra-close-pending'
    ),
    'open motions cannot be silently discarded at session transition');

$standalone=$full();
$principal->signatures['censure-success']=$ids;
$standalone->fileSpontaneousMotion(
    $principal,'censure-success','2026-11-24T09:00:00+01:00',
    'censure-success-file'
);
$principal->censureVotes['censure-success']=289;
$censure=$standalone->voteSpontaneousMotion(
    $principal,'censure-success','2026-11-26T09:00:00+01:00',
    'censure-success-vote'
);
wp22($censure['motion_status']==='adopted'
    && $censure['government']==='resignation_required'
    && $censure['regime_changed']===false,
    '49(2) absolute Assembly majority triggers government resignation only');
$principal->censureVotes['censure-success']=288;
wp22reject(
    fn()=>$standalone->voteSpontaneousMotion(
        $principal,'censure-success','2026-11-27T09:00:00+01:00',
        'censure-second-ballot'
    ),
    'a voted motion cannot be reopened by a new simulated roll call');
$restored=FrenchConstitutionalCrisis::restore($standalone->snapshot());
wp22($restored->hash()===$standalone->hash(),
    '49(2) vote and signatory identities restore to same scenario checksum');

$quota=$full();
$quota->engage($principal,'budget-1','finance',
    '2026-11-24T09:00:00+01:00','budget-49-3');
$principal->censureVotes['incidental']=0;
$quota->fileMotion($principal,'incidental',
    '2026-11-24T10:00:00+01:00','49-3-incidental-motion');
wp22($quota->snapshot()['signatory_counters']===[],
    '49(3) incidental censure does NOT consume Article 49(2) signature quotas');

echo "WP22 Articles 49(1), 49(2), verified signatures and Article 50 passed.\n";
