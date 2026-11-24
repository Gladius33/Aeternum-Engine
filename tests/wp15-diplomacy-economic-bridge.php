<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/DiplomacyEconomicBridge.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,EconomicReferenceRegistry,
    DiplomaticSimulation,WorldEconomicSimulation,
    DiplomacyEconomicBridge,EconomicPolicyPrincipal
};

function wp15(bool $ok,string $label):void
{
    if (!$ok) throw new RuntimeException($label);
    echo "PASS WP15: ".$label."\n";
}
function wp15reject(callable $action,string $label):void
{
    try {$action();}
    catch (DomainException|InvalidArgumentException $e) {
        wp15(true,$label);return;
    }
    throw new RuntimeException('Expected security rejection: '.$label);
}
$sample=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
$sample['entities'][]=[
    'id'=>'state_fr','label'=>'France','iso2'=>'FR','kind'=>'state',
    'un_status'=>'un_member','parent_id'=>null,'controller'=>'human_or_ai',
    'representation'=>'international_state_actor',
    'sources'=>['https://www.un.org/en/about-us/member-states'],
    'strategic_programmes'=>[]
];
$actors=new GeopoliticalActorRegistry($sample);
$refs=new EconomicReferenceRegistry('2026-11-24',[[
    'actor_id'=>'state_fr','partner_id'=>'state_ir','metric'=>'bilateral_trade_usd',
    'value'=>20000000,'unit'=>'USD','year'=>2025,
    'kind'=>'scenario_assumption','quality'=>'assumed',
    'source_url'=>null,'license'=>'fictional_scenario'
]],$actors);
$human=new class implements EconomicPolicyPrincipal {
    public bool $legal=false;
    public function actorId():string{return 'french-player';}
    public function allows(string $world,string $actor,string $ability):bool {
        return $world==='bridge-world' && $actor==='state_fr'
            && in_array($ability,[
                'diplomacy.propose','economy.propose','economy.approve'
            ],true);
    }
    public function legalApproval(string $world,string $actor,string $kind):bool {
        return $this->legal && $world==='bridge-world'
            && $actor==='state_fr' && $kind==='trade_restriction';
    }
};
$server=new class implements EconomicPolicyPrincipal {
    public function actorId():string{return 'trusted-world-clock';}
    public function allows(string $world,string $actor,string $ability):bool {
        return $world==='bridge-world' && $actor===$this->actorId()
            && $ability==='economy.clock';
    }
    public function legalApproval(string $world,string $actor,string $kind):bool{return false;}
};

$diplomacy=new DiplomaticSimulation('bridge-world','2026-11-24',$actors);
$economy=new WorldEconomicSimulation('bridge-world','2026-11-24',$actors,$refs);
$bridge=new DiplomacyEconomicBridge();
$proposal=$diplomacy->propose(
    $human,'state_fr','state_ir','sanctions_proposal','sanctions-from-fr'
);
wp15($proposal['status']==='pending_domestic_approval',
    'international sanction is initially only a diplomatic proposal');
wp15($economy->snapshot()['policies']===[],
    'diplomatic proposal cannot directly create economic effects');

$linked=$bridge->proposeFromDiplomaticSanction(
    $diplomacy,$economy,$human,'sanctions-from-fr',
    1000,500,1700,'2026-12-05'
);
wp15($linked['needs_domestic_authorization']===true
    && $linked['economic_effect_applied']===false
    && str_starts_with($linked['policy_id'],'bridge-'),
    'trusted bridge produces unapproved economic measure with traceable origin');
wp15($economy->snapshot()['projections']===[],
    'no GDP impact and no projected trade reduction while measure pending');
$repeat=$bridge->proposeFromDiplomaticSanction(
    $diplomacy,$economy,$human,'sanctions-from-fr',
    1000,500,1700,'2026-12-05'
);
wp15($linked===$repeat,'same origin yields identical idempotent economic proposal');
wp15reject(fn()=>$bridge->proposeFromDiplomaticSanction(
    $diplomacy,$economy,$human,'sanctions-from-fr',
    2000,500,2100,'2026-12-05'
),'same sanctions intention cannot be silently replaced by incompatible effect assumption');

$diplomacy->propose(
    $human,'state_fr','state_ir','trade_cooperation','cooperation-only'
);
wp15reject(fn()=>$bridge->proposeFromDiplomaticSanction(
    $diplomacy,$economy,$human,'cooperation-only',
    1000,500,1700,'2026-12-05'
),'trade cooperation cannot be impersonated as a sanctions instrument');
wp15reject(fn()=>$bridge->proposeFromDiplomaticSanction(
    $diplomacy,$economy,$human,'absent-diplomatic-proof',
    1000,500,1700,'2026-12-05'
),'nonexistent diplomatic sanction proof cannot enter economy');

$otherEconomy=new WorldEconomicSimulation('other-scenario','2026-11-24',$actors,$refs);
wp15reject(fn()=>$bridge->proposeFromDiplomaticSanction(
    $diplomacy,$otherEconomy,$human,'sanctions-from-fr',
    1000,500,1700,'2026-12-05'
),'events from one scenario cannot mutate another world');
wp15reject(fn()=>$economy->authorizeRestriction(
    $human,$linked['policy_id'],'approve-without-law'
),'a national legal approval is required after diplomatic intention');
$human->legal=true;
$approved=$economy->authorizeRestriction(
    $human,$linked['policy_id'],'ministerial-review'
);
wp15($approved['status']==='authorized' && $approved['binding_effect']===false,
    'reviewed domestic authorization still respects delayed effective date');
$early=$economy->advanceTo($server,'2026-12-04','before-sanction-date');
wp15($early['effective_policies']===[],
    'one day before effective date there is no economic change');
$done=$economy->advanceTo($server,'2026-12-05','sanction-effective-date');
wp15($done['effective_policies']===[$linked['policy_id']],
    'only legal and time-effective fictional policy produces a projection');
$estimate=$economy->snapshot()['projections'][$linked['policy_id']];
wp15($estimate['central_usd']===2000000
    && $estimate['lower_usd']===1000000
    && $estimate['upper_usd']===3400000
    && $estimate['not_a_forecast']===true,
    'scenario-only trade estimate computed with explicit uncertainty');
wp15($estimate['does_not_change_recorded_gdp']===true,
    'historical GDP remains untouched by hypothetical diplomatic actions');
echo "WP15 diplomatic-economy authorization bridge tests passed.\n";
