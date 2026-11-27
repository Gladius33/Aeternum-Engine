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
    public static function calibration():array{
        $r=HouseholdHistoricalBacktest::report(HouseholdIncomeReference::pin());
        $h=$r['metrics']['holdout'];
        return ['classification'=>'conditional_historical_validation_not_causal_calibration','backtest'=>$r,
            'holdout_fitted_minus_midpoint_mae_bps'=>$h['fitted_uc_bps']['mean_absolute_error_bps']-$h['midpoint_uc_bps']['mean_absolute_error_bps'],
            'holdout_observations'=>$h['fitted_uc_bps']['observations'],'enough_for_official_ranking'=>false,
            'score_model_changed'=>false];
    }
}
