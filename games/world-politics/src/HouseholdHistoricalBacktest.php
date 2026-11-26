<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/HouseholdIncomeReference.php';

/** Out-of-sample diagnostic of the residual-income assumption, conditional
 * on observed wages and cash benefits. Not a forecast or causal calibration. */
final class HouseholdHistoricalBacktest
{
    public const VERSION='wp83-household-backtest-v1';
    // Insee 2830244 annual national accounts, 29 May 2026 vintage.
    private const POPULATION_GROWTH=[2020=>50,2021=>40,2022=>50,2023=>40,2024=>30,2025=>30];
    private static function estimate(array $previous,array $current,int $weightBps,bool $uc):int
    {
        if($weightBps<0||$weightBps>10000)throw new \InvalidArgumentException('Invalid residual price weight');
        $base=$previous['rdb_tenth_billion_eur'];$residual=$base-$previous['net_wages_tenth_billion_eur']-$previous['cash_benefits_tenth_billion_eur'];
        $price=$current['consumption_deflator_growth_bps'];$factor=10000+intdiv($price*$weightBps,10000);
        $amount=($current['net_wages_tenth_billion_eur']+$current['cash_benefits_tenth_billion_eur'])*10000+$residual*$factor;
        if($base<=0||$residual<0||$amount<=0)throw new \DomainException('Invalid historical account');
        $growth=intdiv($amount+intdiv($base,2),$base)-10000;
        $den=$uc?$current['uc_growth_bps']:self::POPULATION_GROWTH[$current['year']];
        return HouseholdIncomeReference::realPerUcGrowth($growth,$price,$den);
    }
    private static function metrics(array $rows,string $key):array
    {
        $sum=0;$abs=0;$sq=0;$n=count($rows);
        if($n===0)throw new \DomainException('Empty benchmark set');
        foreach($rows as $r){$error=$r[$key]-$r['observed_bps'];$sum+=$error;$abs+=abs($error);$sq+=$error*$error;}
        return ['observations'=>$n,'mean_error_bps'=>intdiv($sum,$n),'mean_absolute_error_bps'=>intdiv($abs,$n),
            'mean_squared_error_bps2'=>intdiv($sq,$n)];
    }
    public static function report(array $pin):array
    {
        $ref=HouseholdIncomeReference::report($pin);$annual=$ref['reference']['annual'];
        $rows=[];$best=null;
        for($i=1;$i<count($annual);$i++){
            $p=$annual[$i-1];$c=$annual[$i];
            $rows[]=['year'=>$c['year'],'partition'=>$c['year']<=2023?'train':'holdout',
                'observed_bps'=>$c['real_rdb_per_uc_growth_bps'],'population_growth_bps'=>self::POPULATION_GROWTH[$c['year']],
                'uc_growth_bps'=>$c['uc_growth_bps'],'constant_residual_uc_bps'=>self::estimate($p,$c,0,true),
                'indexed_residual_uc_bps'=>self::estimate($p,$c,10000,true),'midpoint_uc_bps'=>self::estimate($p,$c,5000,true),
                'midpoint_population_bps'=>self::estimate($p,$c,5000,false)];
        }
        // Fit only 2021–23; 2024–25 never influence the selected weight.
        // Tie-break toward the original 50% assumption, then smaller weight.
        for($w=0;$w<=10000;$w+=100){
            $loss=0;
            for($i=1;$i<=3;$i++)$loss+=abs(self::estimate($annual[$i-1],$annual[$i],$w,true)-$annual[$i]['real_rdb_per_uc_growth_bps']);
            $candidate=[$loss,abs($w-5000),$w];
            if($best===null||$candidate<$best)$best=$candidate;
        }
        $selected=$best[2];
        foreach($rows as $i=>&$r)$r['fitted_uc_bps']=self::estimate($annual[$i],$annual[$i+1],$selected,true);
        unset($r);
        $metrics=[];
        foreach(['train','holdout'] as $partition){
            $subset=array_values(array_filter($rows,fn($r)=>$r['partition']===$partition));
            foreach(['constant_residual_uc_bps','indexed_residual_uc_bps','midpoint_uc_bps','midpoint_population_bps','fitted_uc_bps'] as $key)
                $metrics[$partition][$key]=self::metrics($subset,$key);
        }
        return ['version'=>self::VERSION,'reference_sha'=>$ref['reference_sha'],'source'=>'https://www.insee.fr/fr/statistiques/2830244',
            'vintage'=>'2026-05-29','classification'=>'conditional_accounting_backtest_not_behavioral_calibration',
            'fitted_price_weight_bps'=>$selected,'training_years'=>[2021,2022,2023],'holdout_years'=>[2024,2025],
            'rows'=>$rows,'metrics'=>$metrics,'sample_size'=>count($rows),'uses_observed_wages_and_benefits'=>true,
            'uses_observed_uc_growth'=>true,'score_rules_changed'=>false,'official_ranking_eligible'=>false,
            'limitations'=>['annual rounded data','one-year resets to actual previous accounts','only five transitions','residual mixes taxes, capital and other income','observed future-year wages and benefits supplied; not genuine forecasting']];
    }
}
