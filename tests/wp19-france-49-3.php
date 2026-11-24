<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrenchConstitutionalCrisis.php';

use Aeternum\WorldPolitics\{
    FrenchConstitutionalCrisis,FrenchConstitutionalPrincipal
};

function wp19(bool $ok,string $name):void
{
    if (!$ok) throw new RuntimeException('FAILED WP19: '.$name);
    echo "PASS WP19: ".$name."\n";
}
function wp19reject(callable $call,string $name):void
{
    try {$call();}
    catch (DomainException|InvalidArgumentException $error) {
        wp19(true,$name);return;
    }
    throw new RuntimeException('Expected refusal: '.$name);
}
$office=new class implements FrenchConstitutionalPrincipal {
    public bool $primeMinister=true;
    public bool $council=true;
    public bool $clerk=true;
    public bool $scheduler=true;
    public array $signatures=[];
    public array $votes=[];
    public function actorId():string{return 'verified-french-government-service';}
    public function allows(string $world,string $ability):bool
    {
        if ($world!=='french-scenario') return false;
        return match($ability) {
            'fr.49_3.engage'=>$this->primeMinister,
            'fr.censure.file','fr.censure.vote'=>$this->clerk,
            'fr.constitution.clock','fr.constitution.session'=>$this->scheduler,
            default=>false
        };
    }
    public function councilDeliberated(string $world,string $bill):bool
    {
        return $this->council && $world==='french-scenario';
    }
    public function verifiedSignatures(string $world,string $motion):int
    {
        return $this->signatures[$motion]??0;
    }
    public function verifiedVotesFor(string $world,string $motion):int
    {
        return $this->votes[$motion]??0;
    }
};
$t0='2026-11-24T09:00:00+01:00';
$epoch=FrenchConstitutionalCrisis::instant($t0);
wp19($epoch===FrenchConstitutionalCrisis::instant('2026-11-24T08:00:00Z'),
    '49.3 time windows use offset-aware instants, not ambiguous local days');
wp19reject(fn()=>FrenchConstitutionalCrisis::instant('2026-11-24T09:00'),
    'naive local date-time cannot silently bypass 24/48h procedural limits');
wp19reject(fn()=>FrenchConstitutionalCrisis::instant('2026-02-30T09:00:00+01:00'),
    'invalid constitutional calendar day rejected');
$sim=new FrenchConstitutionalCrisis('french-scenario','regular-2026',$epoch,577);
$office->primeMinister=false;
wp19reject(fn()=>$sim->engage($office,'bill-budget',
    'finance',$t0,'engage-illegal'),
    'only verified prime minister authority can engage governmental responsibility');
$office->primeMinister=true;
$office->council=false;
wp19reject(fn()=>$sim->engage($office,'bill-budget',
    'finance',$t0,'engage-without-council'),
    '49.3 engagement cannot bypass prior Council of Ministers deliberation');
$office->council=true;
wp19reject(fn()=>$sim->engage($office,'wrong-format',
    'constitutional_revision',$t0,'engage-unknown-category'),
    'revision of Constitution cannot be treated as ordinary 49.3 text');

$engaged=$sim->engage($office,'bill-budget','finance',$t0,'budget-engagement');
wp19($engaged['status']==='censure_window_open'
    && $engaged['filing_closes_at']===$epoch+86400
    && $engaged['law_promulgated']===false,
    'approved finance engagement opens precisely 24 hours, not promulgation');
wp19($sim->engage($office,'bill-budget','finance',$t0,'budget-engagement')===$engaged,
    'constitutionally verified engagement idempotently replays');

$office->signatures['motion-one']=57;
wp19reject(fn()=>$sim->fileMotion($office,'motion-one',
    '2026-11-24T10:00:00+01:00','file-too-few'),
    '577 deputies require at least 58 valid signatures to file a motion');
$office->signatures['motion-one']=58;
$filing=$sim->fileMotion($office,'motion-one',
    '2026-11-24T10:00:00+01:00','file-motion-one');
wp19($filing['needed_votes']===289
    && $filing['earliest_vote_at']===FrenchConstitutionalCrisis::instant('2026-11-26T10:00:00+01:00'),
    'majority absolute 289 positive votes and 48-hour minimum after deposit');
$office->votes['motion-one']=288;
wp19reject(fn()=>$sim->vote($office,'motion-one',
    '2026-11-26T09:59:59+01:00','too-early'),
    'motion cannot be put to a vote even one second before 48-hour waiting period');
$vote=$sim->vote($office,'motion-one',
    '2026-11-26T10:00:00+01:00','vote-motion-one');
wp19($vote['motion_status']==='rejected'
    && $vote['government']==='in_office',
    '288 votes fail majority absolute; abstentions do not count as censure');
$state=$sim->snapshot();
wp19($state['government']==='in_office'
    && $state['history'][0]['status']==='adopted_by_assembly'
    && $state['history'][0]['assembly_adoption']===true
    && $state['current_bill']===null,
    'failed motion yields adoption by Assembly only, never regime collapse');
$roundTrip=FrenchConstitutionalCrisis::restore($sim->snapshot());
wp19($roundTrip->hash()===$sim->hash(),
    'historic 49.3 acts round-trip deterministically');
$office->votes['motion-one']=289;
wp19reject(fn()=>$sim->vote($office,'motion-one',
    '2026-11-27T10:00:00+01:00','change-after-rejected'),
    'a rejected motion is final, not recountable by changing a scenario value');

$finance2=$sim->engage($office,'bill-budget-two','finance',
    '2026-11-27T11:00:00+01:00','second-financial-engagement');
wp19($finance2['status']==='censure_window_open',
    'finance bills are not limited to one 49.3 per parliamentary session');
$sim->advance($office,'2026-11-28T11:00:01+01:00','close-budget-window2');
wp19($sim->snapshot()['history'][1]['status']==='adopted_by_assembly',
    'without any censure deposited within 24 hours, Assembly text deemed adopted');

$ordinary=$sim->engage($office,'bill-work','ordinary',
    '2026-11-29T09:00:00+01:00','ordinary-engagement');
wp19($ordinary['status']==='censure_window_open'
    && $sim->snapshot()['ordinary_49_3_used']===true,
    'one ordinary legislative 49.3 available for this session');
$office->signatures['motion-work']=59;
$sim->fileMotion($office,'motion-work',
    '2026-11-30T09:00:00+01:00','ordinary-motion-file');
wp19reject(fn()=>$sim->fileMotion($office,'late-motion',
    '2026-11-30T09:00:01+01:00','late-file'),
    'filing one second after 24-hour window is invalid');
$office->votes['motion-work']=289;
$censure=$sim->vote($office,'motion-work',
    '2026-12-02T09:00:00+01:00','censure-work-vote');
wp19($censure['motion_status']==='adopted'
    && $censure['government']==='resignation_required'
    && $censure['regime_changed']===false
    && $sim->snapshot()['history'][2]['assembly_adoption']===false,
    '289 affirmative votes trigger Article 50 government resignation but NOT Republic overthrow');
wp19reject(fn()=>$sim->engage($office,'another-ordinary','ordinary',
    '2026-12-03T09:00:00+01:00','resigned-ministry'),
    'censured government cannot continue to exercise 49.3');
wp19($sim->snapshot()['ordinary_49_3_used']===true,
    'ordinary usage remains recorded in session after resignation');
$sim->openSession($office,'regular-2027',
    '2026-12-03T10:00:00+01:00','new-session-2027');
wp19($sim->snapshot()['ordinary_49_3_used']===false
    && $sim->snapshot()['government']==='resignation_required',
    'session rollover does not magically appoint a new ministry');
wp19reject(fn()=>FrenchConstitutionalCrisis::restore(
    array_replace($sim->snapshot(),['version'=>'invented-law'])
),'unrecognized constitutional version rejected');

$office->primeMinister=true;$office->council=true;
$fresh=new FrenchConstitutionalCrisis('french-scenario','regular-2026',
    $epoch,577);
$fresh->engage($office,'ordinary-1','ordinary',$t0,'ordinary-once');
$fresh->advance($office,'2026-11-25T09:00:01+01:00','window-closed');
wp19reject(fn()=>$fresh->engage(
    $office,'ordinary-2','ordinary','2026-11-26T09:00:00+01:00','ordinary-twice'
),'second ordinary 49.3 within same session blocked independently of protests');

echo "WP19 Article 49(3), censure timing, Article 50 and Assembly-only adoption passed.\n";
