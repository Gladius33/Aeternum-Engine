<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/WorldEconomicSimulation.php';

use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,EconomicReferenceRegistry,
    EconomicPolicyPrincipal,WorldEconomicSimulation
};

function wp12(bool $ok,string $label):void {
    if (!$ok) throw new \RuntimeException($label);
    echo "PASS WP12: ".$label."\n";
}
function wp12reject(callable $f,string $label):void {
    try {$f();}
    catch (\DomainException|\InvalidArgumentException $e) {
        wp12(true,$label);return;
    }
    throw new \RuntimeException('Expected rejection: '.$label);
}
$source=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
foreach (['FR'=>'France','DE'=>'Germany'] as $iso=>$name) {
    $source['entities'][]=[
        'id'=>'state_'.strtolower($iso),'label'=>$name,'iso2'=>$iso,
        'kind'=>'state','un_status'=>'un_member','parent_id'=>null,
        'controller'=>'human_or_ai','representation'=>'international_state_actor',
        'sources'=>['https://www.un.org/en/about-us/member-states'],
        'strategic_programmes'=>[]
    ];
}
$actors=new GeopoliticalActorRegistry($source);
$assumption=static fn(string $actor,?string $partner,string $metric,int $value,int $year=2025):array=>[
    'actor_id'=>$actor,'partner_id'=>$partner,'metric'=>$metric,
    'value'=>$value,'unit'=>$metric==='population_total'?'persons':'USD',
    'year'=>$year,'kind'=>'scenario_assumption','quality'=>'assumed',
    'source_url'=>null,'license'=>'fictional_scenario',
];
$trade=$assumption('state_fr','state_ir','bilateral_trade_usd',10000000);
$revenue=$assumption('state_fr',null,'government_budget_usd',250000000);
$missing=[
    'actor_id'=>'state_de','partner_id'=>'state_il','metric'=>'bilateral_trade_usd',
    'value'=>null,'unit'=>'USD','year'=>2025,'kind'=>'missing','quality'=>'missing',
    'source_url'=>null,'license'=>'not_applicable'
];
$observed=[
    'actor_id'=>'state_fr','partner_id'=>null,'metric'=>'population_total',
    'value'=>68000000,'unit'=>'persons','year'=>2024,
    'kind'=>'observed','quality'=>'estimated',
    'source_url'=>'https://api.worldbank.org/v2/country/FR/indicator/SP.POP.TOTL',
    'license'=>'example_license_claim_requires_admin_verification'
];
$registry=new EconomicReferenceRegistry('2026-11-24',[$trade,$revenue,$missing,$observed],$actors);
wp12($registry->count()===4,'reference registry contains four time-stamped facts or assumptions');
wp12($registry->latest('state_fr','bilateral_trade_usd','state_ir')['kind']==='scenario_assumption',
    'imaginary bilateral trade cannot masquerade as official statistics');
wp12($registry->latest('state_de','bilateral_trade_usd','state_il')['value']===null,
    'missing trade data remains explicitly null');
wp12($registry->latest('state_fr','population_total')['source_url']===$observed['source_url'],
    'reference retains observation URL and license');
wp12($registry->latest('state_il','gdp_current_usd')===null,
    'nonexistent GDP is not silently estimated');
$invalid=$observed;$invalid['source_url']=null;
wp12reject(fn()=>new EconomicReferenceRegistry('2026-11-24',[$invalid],$actors),
    'published-looking values without source rejected');
$invalid=$trade;$invalid['quality']='published';
wp12reject(fn()=>new EconomicReferenceRegistry('2026-11-24',[$invalid],$actors),
    'scenario estimate cannot masquerade as published value');
$invalid=$trade;$invalid['value']=-1;
wp12reject(fn()=>new EconomicReferenceRegistry('2026-11-24',[$invalid],$actors),
    'negative trade volume rejected');
$invalid=$trade;$invalid['actor_id']='authority_pa';
wp12reject(fn()=>new EconomicReferenceRegistry('2026-11-24',[$invalid],$actors),
    'administrative authority cannot be silently upgraded to sovereign trade statistics');
$invalid=$trade;$invalid['year']=2030;
wp12reject(fn()=>new EconomicReferenceRegistry('2026-11-24',[$invalid],$actors),
    'future economic observations cannot contaminate historical scenario');

$player=new class implements EconomicPolicyPrincipal {
    public bool $legal=false;
    public function actorId():string{return 'player-one';}
    public function allows(string $world,string $actor,string $permission):bool {
        return $world==='world-impact'
            && $actor==='state_fr'
            && in_array($permission,['economy.propose','economy.approve'],true);
    }
    public function legalApproval(string $world,string $state,string $action):bool {
        return $this->legal && $world==='world-impact' && $state==='state_fr'
            && $action==='trade_restriction';
    }
};
$bot=new class implements EconomicPolicyPrincipal {
    public function actorId():string{return 'trusted-simulation-clock';}
    public function allows(string $world,string $actor,string $permission):bool {
        return $world==='world-impact' && $actor===$this->actorId()
            && $permission==='economy.clock';
    }
    public function legalApproval(string $world,string $state,string $action):bool {
        return false;
    }
};
$sim=new WorldEconomicSimulation('world-impact','2026-11-24',$actors,$registry);
wp12reject(fn()=>$sim->proposeTradeRestriction($player,
    'state_ir','state_fr',1000,500,2000,'2026-12-01','spoof-iran'),
    'human player cannot act as Iranian administration');
wp12reject(fn()=>$sim->proposeTradeRestriction($player,
    'authority_pa','state_fr',1000,500,2000,'2026-12-01','spoof-pa'),
    'Palestinian Authority not silently treated as sovereign trade state');
wp12reject(fn()=>$sim->proposeTradeRestriction($player,
    'state_fr','state_ir',700,1000,2000,'2026-12-01','invalid-range'),
    'impossible uncertainty ordering rejected');

$pending=$sim->proposeTradeRestriction($player,'state_fr','state_ir',
    1500,800,2200,'2026-12-01','scenario-trade-restriction');
wp12($pending['status']==='proposed' && $pending['binding_effect']===false,
    'player proposal is nonbinding');
wp12($sim->proposeTradeRestriction($player,'state_fr','state_ir',
    1500,800,2200,'2026-12-01','scenario-trade-restriction')===$pending,
    'proposal command idempotent');
wp12reject(fn()=>$sim->proposeTradeRestriction($player,'state_fr','state_ir',
    3000,800,3500,'2026-12-01','scenario-trade-restriction'),
    'same command cannot be replaced by changed economic assumptions');
wp12($sim->snapshot()['projections']===[],
    'unapproved sanctions proposals have no economic effect');
wp12reject(fn()=>$sim->authorizeRestriction($player,
    'scenario-trade-restriction','illegal-fiscal-authorization'),
    'country-specific legal authority required before sanction can take effect');
$player->legal=true;
$authorized=$sim->authorizeRestriction($player,
    'scenario-trade-restriction','legal-fiscal-authorization');
wp12($authorized['status']==='authorized' && $authorized['binding_effect']===false,
    'legal approval is not premature economic implementation');
wp12($sim->snapshot()['projections']===[],
    'no impact projected before game-time effect date');
$early=$sim->advanceTo($bot,'2026-11-30','day-before-effective');
wp12($early['effective_policies']===[],'advance before effect date has no synthetic loss');
wp12reject(fn()=>$sim->advanceTo($player,'2026-12-01','client-clock'),
    'human player cannot arbitrarily advance trusted world clock');
$due=$sim->advanceTo($bot,'2026-12-01','date-effect');
wp12($due['effective_policies']===['scenario-trade-restriction'],
    'authorized policy activates on scheduled date');
$effect=$sim->snapshot()['projections']['scenario-trade-restriction'];
wp12($effect['central_usd']===1500000
    && $effect['lower_usd']===800000 && $effect['upper_usd']===2200000,
    'deterministic scenario estimate and explicit uncertainty range computed');
wp12($effect['classification']==='scenario_projection'
    && $effect['reference_kind']==='scenario_assumption'
    && $effect['not_a_forecast']===true
    && $effect['does_not_change_recorded_gdp']===true,
    'result clearly not an observed GDP change or verified forecast');
$restored=WorldEconomicSimulation::fromSnapshot($sim->snapshot(),$actors,$registry);
wp12($restored->hash()===$sim->hash(),
    'snapshot restores identical world economic decisions');
$bad=$sim->snapshot();$bad['reference_sha']=str_repeat('0',64);
wp12reject(fn()=>WorldEconomicSimulation::fromSnapshot($bad,$actors,$registry),
    'pinned economic source mismatch refused');
$bad=$sim->snapshot();$bad['policies']['scenario-trade-restriction']['status']='invalid';
wp12reject(fn()=>WorldEconomicSimulation::fromSnapshot($bad,$actors,$registry),
    'unrecognized persisted policy state refused');
$bad=$sim->snapshot();$bad['projections']['new-injection']=['classification'=>'scenario_projection'];
wp12reject(fn()=>WorldEconomicSimulation::fromSnapshot($bad,$actors,$registry),
    'projection injection without approved policy refused');

$noTrade=new EconomicReferenceRegistry('2026-11-24',[$missing],$actors);
$emptySim=new WorldEconomicSimulation('world-impact','2026-11-24',$actors,$noTrade);
$emptySim->proposeTradeRestriction($player,'state_fr','state_ir',
    1000,500,2000,'2026-12-01','unmeasured-trade');
$emptySim->authorizeRestriction($player,'unmeasured-trade','approve-unmeasured');
$emptySim->advanceTo($bot,'2026-12-01','compute-unmeasured');
wp12($emptySim->snapshot()['projections']['unmeasured-trade']['central_usd']===null,
    'missing bilateral statistics never create fictitious dollar losses');
echo "WP12 source-attributed economic projection tests passed.\n";
