<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/FrenchSocialDynamics.php';

use Aeternum\WorldPolitics\{
    FrenchSocialDynamics,FrenchConstitutionalCrisis,FrenchConstitutionalPrincipal
};

function wp20(bool $ok,string $label):void
{
    if (!$ok) throw new RuntimeException('FAILED WP20: '.$label);
    echo "PASS WP20: ".$label."\n";
}
function wp20reject(callable $fn,string $label):void
{
    try {$fn();}
    catch (DomainException|InvalidArgumentException $error) {
        wp20(true,$label);return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
$trusted=new class implements FrenchConstitutionalPrincipal {
    public function actorId():string{return 'trusted-france-simulation';}
    public function allows(string $world,string $ability):bool {
        return $world==='social-world'
            && in_array($ability,[
                'fr.49_3.engage','fr.censure.file','fr.censure.vote',
                'fr.constitution.clock','fr.constitution.session'
            ],true);
    }
    public function councilDeliberated(string $world,string $bill):bool {
        return $world==='social-world';
    }
    public function verifiedBillCategory(string $world,string $bill):string
    {
        return $bill==='bill-social-2027'?'social_security_finance':'finance';
    }
    public function verifiedSignatures(string $world,string $motion):int{return 58;}
    public function verifiedVotesFor(string $world,string $motion):int{return 0;}
};
$constitution=new FrenchConstitutionalCrisis(
    'social-world','regular-2026',
    FrenchConstitutionalCrisis::instant('2026-11-24T09:00:00+01:00'),577
);
$constitution->engage(
    $trusted,'bill-social-2026','finance',
    '2026-11-24T09:00:00+01:00','fr-engage-social-1'
);
$sectors=[
    [
        'id'=>'healthcare','name'=>'Soins et santé',
        'population_weight_bps'=>2500,
        'base_grievance'=>620,'collective_organization'=>740,
        'economic_dependence'=>790,'mobility_constraint'=>250,
        'workplace_fragility'=>600
    ],
    [
        'id'=>'industry','name'=>'Industrie et manufacture',
        'population_weight_bps'=>3000,
        'base_grievance'=>460,'collective_organization'=>680,
        'economic_dependence'=>840,'mobility_constraint'=>130,
        'workplace_fragility'=>780
    ],
    [
        'id'=>'education','name'=>'Enseignement',
        'population_weight_bps'=>2200,
        'base_grievance'=>500,'collective_organization'=>700,
        'economic_dependence'=>540,'mobility_constraint'=>200,
        'workplace_fragility'=>480
    ],
    [
        'id'=>'services','name'=>'Services et commerce',
        'population_weight_bps'=>2300,
        'base_grievance'=>300,'collective_organization'=>270,
        'economic_dependence'=>680,'mobility_constraint'=>300,
        'workplace_fragility'=>350
    ]
];
$sim=new FrenchSocialDynamics('social-world',$sectors);
wp20($sim->cohortHash()===str_repeat('',0).$sim->snapshot()['cohort_sha'],
    'social cohort assumptions deterministically pinned in snapshot');
$impacts=[
    'education'=>450,'healthcare'=>800,
    'industry'=>300,'services'=>-300
];
$concessions=[
    'education'=>50,'healthcare'=>0,
    'industry'=>100,'services'=>100
];
$context=[
    'civil_liberties_trust'=>700,
    'public_institutional_trust'=>450,
    'parliamentary_support'=>320,
    'government_cohesion'=>740,
    'security_institutional_cohesion'=>920,
    'military_constitutional_cohesion'=>980,
    'elite_cohesion'=>700,
    'economic_resilience'=>510
];
$high=$sim->assess49_3(
    $constitution,'bill-social-2026',
    $impacts,$concessions,$context
);
wp20($high['classification']==='scenario_assumptions_not_survey_or_forecast',
    'public reactions cannot be presented as actual French survey data');
wp20($high['aggregate']['grievance_index']>0
    && $high['aggregate']['peaceful_protest_index']>0
    && $high['aggregate']['production_disruption_index']>0,
    'unpopular verified 49.3 use creates differentiated protest and economic signals');
wp20($high['cohorts']['healthcare']['grievance_index']
    >$high['cohorts']['services']['grievance_index'],
    'healthcare and service workers respond differently to the same policy');
wp20($high['cohorts']['industry']['work_withdrawal_propensity_index']
    !==$high['cohorts']['services']['work_withdrawal_propensity_index'],
    'organization, exposure and workplace vulnerability affect labor withdrawal differently');
wp20($high['aggregate']['production_loss_bps_high']
    >=$high['aggregate']['production_loss_bps_low']
    && $high['aggregate']['production_loss_bps_high']<=1000,
    'economic disruption includes bounded scenario uncertainty, not fabricated GDP statistics');
wp20($high['government_removed']===false
    && $high['regime_changed']===false
    && $high['automatic_security_solution']===false,
    'large grievances never automatically overthrow government or guarantee security-force protection');
wp20($sim->assess49_3(
    $constitution,'bill-social-2026',
    $impacts,$concessions,$context
)===$high,'repeated report returns identical immutable output');

$concessionsHigher=$concessions;
$concessionsHigher['healthcare']=850;
wp20reject(fn()=>$sim->assess49_3(
    $constitution,'bill-social-2026',
    $impacts,$concessionsHigher,$context
),'same law report cannot be silently regenerated with better assumptions');
wp20reject(fn()=>$sim->assess49_3(
    $constitution,'unapproved-bill',
    $impacts,$concessions,$context
),'no social 49.3 event can be fabricated without legal domain proof');

$other=new FrenchConstitutionalCrisis(
    'other-world','regular-2026',
    FrenchConstitutionalCrisis::instant('2026-11-24T09:00:00+01:00'),577
);
wp20reject(fn()=>$sim->assess49_3(
    $other,'bill-social-2026',
    $impacts,$concessions,$context
),'cross-world constitutional activity may not influence another game world');

$bad=$sectors;$bad[0]['population_weight_bps']=3000;
wp20reject(fn()=>new FrenchSocialDynamics('social-world',$bad),
    'unbalanced demographic weights cannot inflate a single cohort influence');
$bad=$sectors;$bad[1]['id']='healthcare';
wp20reject(fn()=>new FrenchSocialDynamics('social-world',$bad),
    'duplicate professions cannot be counted twice');
$bad=$impacts;unset($bad['education']);
wp20reject(fn()=>(new FrenchSocialDynamics('social-world',$sectors))->assess49_3(
    $constitution,'bill-social-2026',$bad,$concessions,$context
),'every sector must have an explicit scenario impact');
$bad=$impacts;$bad['industry']=2000;
wp20reject(fn()=>(new FrenchSocialDynamics('social-world',$sectors))->assess49_3(
    $constitution,'bill-social-2026',$bad,$concessions,$context
),'unbounded sector discontent projections rejected');

$constitution->advance(
    $trusted,'2026-11-25T09:00:01+01:00','close-social-finance-law'
);
$constitution->engage(
    $trusted,'bill-social-2027','social_security_finance',
    '2026-11-26T09:00:00+01:00','engage-new-social-policy'
);
$otherModel=new FrenchSocialDynamics('social-world',$sectors);
$concessionsHigh=[
    'education'=>500,'healthcare'=>850,
    'industry'=>650,'services'=>250
];
$lower=$otherModel->assess49_3(
    $constitution,'bill-social-2027',
    $impacts,$concessionsHigh,$context
);
wp20($lower['cohorts']['healthcare']['grievance_index']
    <$high['cohorts']['healthcare']['grievance_index']
    && $lower['aggregate']['production_disruption_index']
        <$high['aggregate']['production_disruption_index'],
    'targeted voluntary concessions can lower disruption without a universal outcome rule');

$civicContext=$context;$civicContext['civil_liberties_trust']=100;
$restrictive=new FrenchSocialDynamics('social-world',$sectors);
$restricted=$restrictive->assess49_3(
    $constitution,'bill-social-2027',
    $impacts,$concessionsHigh,$civicContext
);
wp20($restricted['aggregate']['peaceful_protest_index']
    <$lower['aggregate']['peaceful_protest_index']
    && $restricted['aggregate']['work_withdrawal_index']
        >$lower['aggregate']['work_withdrawal_index'],
    'lower civic-space trust may reduce visible rallies while increasing hidden labor non-cooperation');
wp20($restricted['aggregate']['institutional_strain_index']
    >$lower['aggregate']['institutional_strain_index']
    && $restricted['regime_changed']===false,
    'institutional harm increases without mechanically asserting a revolution');

echo "WP20 heterogeneous French social and economic response simulation passed.\n";
