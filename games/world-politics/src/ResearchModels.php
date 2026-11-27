<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ProgrammeSourceInventory.php';
require_once __DIR__.'/HouseholdHistoricalBacktest.php';
require_once __DIR__.'/HouseholdAccount.php';

/** WP121–122: explicit research hypotheses, no automatic enactment. */
final class ResearchModels
{
    public const VERSION='wp121-122-research-models-v1';
    private const KINDS=['childcare-training','housing-construction','working-hours'];
    private static function fields(array $p,array $keys):void{
        $a=array_keys($p);sort($a);sort($keys);if($a!==$keys)throw new \DomainException('Unexpected model fields');
    }
    private static function integer(array $p,string $k,int $min,int $max):int{
        $v=$p[$k]??null;if(!is_int($v)||$v<$min||$v>$max)throw new \DomainException('Out-of-range model parameter');return $v;
    }
    public static function coverage():array{
        $r=ProgrammeSourceInventory::report();
        $routes=[
            'gabriel-attal'=>['kind'=>'childcare-training','classification'=>'conditional_capacity_accounting'],
            'edouard-philippe'=>['kind'=>'housing-construction','classification'=>'conditional_capacity_accounting'],
            'anasse-kazib'=>['kind'=>'working-hours','classification'=>'conditional_work_volume_accounting'],
            'clara-egger'=>['kind'=>null,'classification'=>'constitutional_research_only_unimplemented']
        ];
        $out=[];foreach($r['identified_unmapped'] as $m){
            $route=$routes[$m['person']]??['kind'=>null,'classification'=>'unreviewed'];
            $out[]=$m+$route+['gameplay_executable'=>false,'candidate_unlocked'=>false];
        }
        return ['version'=>self::VERSION,'inventory_sha'=>$r['sha256'],'election_year'=>2027,
            'rows'=>$out,'exhaustive'=>false,'approved_programme_count'=>count($r['approved_editions'])];
    }
    public static function policy(array $p):array{
        self::fields($p,['kind','years','target_per_year','baseline_per_year','capacity_per_year','unit_cost_eur','completion_bps']);
        if(!in_array($p['kind'],self::KINDS,true))throw new \DomainException('Unknown policy model');
        if($p['kind']==='working-hours')throw new \DomainException('Use working-hours model');
        $years=self::integer($p,'years',1,20);
        $target=self::integer($p,'target_per_year',0,2000000);
        $base=self::integer($p,'baseline_per_year',0,2000000);
        $capacity=self::integer($p,'capacity_per_year',0,2000000);
        $cost=self::integer($p,'unit_cost_eur',0,1000000);
        $completion=self::integer($p,'completion_bps',0,10000);
        $rows=[];$total=0;$cumulative=0;
        for($i=1;$i<=$years;$i++){
            $additional=max(0,$target-$base);$started=min($capacity,$additional);
            $completed=intdiv($started*$completion,10000);
            $outlay=$started*$cost;$total+=$outlay;$cumulative+=$completed;
            $rows[]=['year'=>$i,'additional_target'=>$additional,'started'=>$started,'completed'=>$completed,
                'unmet_target'=>$additional-$completed,'scenario_outlay_eur'=>$outlay,'cumulative_completed'=>$cumulative];
        }
        return ['classification'=>'conditional_accounting_not_forecast','kind'=>$p['kind'],'rows'=>$rows,
            'total_outlay_eur'=>$total,'total_completed'=>$cumulative,'economic_feedback_modelled'=>false,'legal_enactment'=>false];
    }
    public static function hours(array $p):array{
        self::fields($p,['workers','baseline_weekly_hours','target_weekly_hours','productivity_change_bps','replacement_bps']);
        $workers=self::integer($p,'workers',1,50000000);
        $before=self::integer($p,'baseline_weekly_hours',1,80);
        $after=self::integer($p,'target_weekly_hours',1,80);
        $productivity=self::integer($p,'productivity_change_bps',-5000,10000);
        $replacement=self::integer($p,'replacement_bps',0,10000);
        $old=$workers*$before;$new=$workers*$after;
        $replaced=intdiv(max(0,$old-$new)*$replacement,10000);
        $hours=$new+$replaced;$effective=intdiv($hours*(10000+$productivity),10000);
        return ['classification'=>'conditional_work_volume_not_employment_prediction','weekly_hours_before'=>$old,
            'weekly_hours_after'=>$hours,'replacement_hours'=>$replaced,'effective_hours_after'=>$effective,
            'effective_volume_change_bps'=>intdiv($effective*10000,$old)-10000,'wage_feedback_modelled'=>false,'legal_enactment'=>false];
    }
    public static function income(array $p):array{
        self::fields($p,['nominal_growth_bps','price_growth_bps','uc_growth_bps','uncertainty_bps']);
        $n=self::integer($p,'nominal_growth_bps',-5000,10000);
        $price=self::integer($p,'price_growth_bps',-2000,10000);
        $uc=self::integer($p,'uc_growth_bps',-2000,5000);
        $u=self::integer($p,'uncertainty_bps',0,1000);
        $rows=[];
        foreach([-1,0,1] as $a)foreach([-1,0,1] as $b)foreach([-1,0,1] as $c){
            $ng=$n+$a*$u;$pg=$price+$b*$u;$ug=$uc+$c*$u;
            $rows[]=['nominal_growth_bps'=>$ng,'price_growth_bps'=>$pg,'uc_growth_bps'=>$ug,
                'real_per_uc_growth_bps'=>HouseholdIncomeReference::realPerUcGrowth($ng,$pg,$ug)];
        }
        $v=array_column($rows,'real_per_uc_growth_bps');
        return ['classification'=>'deterministic_sensitivity_not_confidence_interval','central_bps'=>$v[13],
            'minimum_bps'=>min($v),'maximum_bps'=>max($v),'rows'=>$rows,'official_score_eligible'=>false];
    }
    /** Conditional annual fiscal stock-flow accounting, not an economic
     * forecast. All quantities are EUR, signed net liability permits assets. */
    public static function fiscal(array $p):array{
        self::fields($p,['years','opening_net_liability_eur','annual_revenue_eur','annual_primary_expense_eur',
            'revenue_growth_bps','expense_growth_bps','interest_bps','annual_measure_cost_eur']);
        $years=self::integer($p,'years',1,20);
        $debt=self::integer($p,'opening_net_liability_eur',-10000000000000,10000000000000);
        $rev=self::integer($p,'annual_revenue_eur',0,10000000000000);
        $expense=self::integer($p,'annual_primary_expense_eur',0,10000000000000);
        $rg=self::integer($p,'revenue_growth_bps',-1000,1000);
        $eg=self::integer($p,'expense_growth_bps',-1000,1000);
        $rate=self::integer($p,'interest_bps',0,2000);
        $measure=self::integer($p,'annual_measure_cost_eur',-1000000000000,1000000000000);
        $rows=[];
        for($year=1;$year<=$years;$year++){
            $opening=$debt;$primary=$expense+$measure;
            if($primary<0)throw new \DomainException('Negative primary spending');
            $interest=intdiv(max(0,$opening)*$rate,10000);
            $deficit=$primary+$interest-$rev;$debt=$opening+$deficit;
            if(abs($debt)>1000000000000000)throw new \DomainException('Fiscal path outside scope');
            $rows[]=['year'=>$year,'opening_net_liability_eur'=>$opening,'revenue_eur'=>$rev,
                'primary_expense_eur'=>$primary,'interest_eur'=>$interest,'deficit_eur'=>$deficit,
                'closing_net_liability_eur'=>$debt];
            $rev=intdiv($rev*(10000+$rg),10000);$expense=intdiv($expense*(10000+$eg),10000);
        }
        return ['classification'=>'conditional_stock_flow_not_macro_forecast','rows'=>$rows,
            'closing_net_liability_eur'=>$debt,'interest_on_assets_modelled'=>false,'legal_enactment'=>false];
    }
    public static function family(array $p):array{
        self::fields($p,['first_births','subsequent_births','first_grant_eur','subsequent_grant_eur','takeup_bps']);
        $a=self::integer($p,'first_births',0,2000000);$b=self::integer($p,'subsequent_births',0,2000000);
        $g=self::integer($p,'first_grant_eur',0,50000);$h=self::integer($p,'subsequent_grant_eur',0,50000);
        $takeup=self::integer($p,'takeup_bps',0,10000);
        $rows=[];
        foreach([['first',$a,$g],['subsequent',$b,$h]] as [$kind,$births,$grant]){
            $recipients=intdiv($births*$takeup,10000);
            $rows[]=['birth_order'=>$kind,'births'=>$births,'recipients'=>$recipients,'one_time_grant_eur'=>$grant,'outlay_eur'=>$recipients*$grant];
        }
        return ['classification'=>'conditional_one_time_transfer_not_fertility_prediction','rows'=>$rows,
            'total_outlay_eur'=>array_sum(array_column($rows,'outlay_eur')),'behavioural_response_modelled'=>false,'legal_enactment'=>false];
    }
    public static function countries(array $p):array{
        self::fields($p,[]);
        $d=json_decode(file_get_contents(__DIR__.'/../data/2026-11-25-un-members-v1.json'),true,32,JSON_THROW_ON_ERROR);
        $rows=$d['countries'];$seen=[];
        foreach($rows as $r){
            if(!preg_match('/^[A-Z]{2}$/D',$r['iso2'])||isset($seen[$r['iso2']])||!is_string($r['name'])||!is_bool($r['un_member']))
                throw new \DomainException('Invalid country reference');
            $seen[$r['iso2']]=true;
        }
        if(count($rows)!==195||count(array_filter($rows,fn($r)=>$r['un_member']))!==193)
            throw new \DomainException('Country coverage mismatch');
        return ['classification'=>'documentary_reference_not_gameplay_eligibility','as_of'=>$d['as_of'],
            'sources'=>[$d['membership_source'],$d['observer_source'],$d['code_source']],'rows'=>$rows,'exhaustive_programmes'=>false];
    }
    public static function calibration():array{
        $r=HouseholdHistoricalBacktest::report(HouseholdIncomeReference::pin());
        $h=$r['metrics']['holdout'];
        return ['classification'=>'conditional_historical_validation_not_causal_calibration','backtest'=>$r,
            'holdout_fitted_minus_midpoint_mae_bps'=>$h['fitted_uc_bps']['mean_absolute_error_bps']-$h['midpoint_uc_bps']['mean_absolute_error_bps'],
            'holdout_observations'=>$h['fitted_uc_bps']['observations'],'enough_for_official_ranking'=>false,
            'score_model_changed'=>false];
    }
}
