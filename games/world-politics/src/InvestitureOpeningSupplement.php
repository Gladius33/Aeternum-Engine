<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Dated observations and deliberately simple, non-observed stock assumptions.
 * Net Maastricht debt deducts the corresponding financial assets, not total wealth.
 */
final class InvestitureOpeningSupplement
{
    public const VERSION='wp54r-opening-supplement-v1';
    private static function allocate(int $total,array $weights):array
    {
        $den=array_sum($weights);
        if($total<0||$den<=0||$den>10000000)throw new \DomainException('Allocation bounds');
        $out=[];$cum=0;$prev=0;
        foreach($weights as $k=>$w){
            if(!is_int($w)||$w<0)throw new \DomainException('Invalid weight');
            $cum+=$w;
            $n=intdiv($total,$den)*$cum+intdiv(($total%$den)*$cum,$den);
            $out[$k]=$n-$prev;$prev=$n;
        }
        return $out;
    }
    public static function report(int $openingDebt):array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-fr-opening-supplement-v1.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!==self::VERSION||$openingDebt<=0||$openingDebt>1000000000000000)throw new \DomainException('Invalid supplement');
        $d=$p['debt'];$weights=[];
        foreach($d['gross'] as $k=>$v)$weights[$k]=$v-$d['net'][$k];
        $assets=($d['gross_total']-$d['net_total'])*100000000;
        $gross=self::allocate($openingDebt,$d['gross']);$deductions=self::allocate($assets,$weights);$net=[];
        foreach($gross as $k=>$v){$net[$k]=$v-$deductions[$k];if($net[$k]<0)throw new \DomainException('Negative net opening debt');}
        return ['version'=>self::VERSION,'source_pack'=>$p,'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'classification'=>'scenario_projection_not_observation','opening_gross_debt_minor'=>$openingDebt,'opening_net_debt_minor'=>array_sum($net),
            'gross_by_subsector_minor'=>$gross,'net_by_subsector_minor'=>$net,'deductible_assets_by_subsector_minor'=>$deductions,
            'observed_gross_rounding_residual_million'=>$d['gross_total']-array_sum($d['gross']),
            'observed_net_rounding_residual_million'=>$d['net_total']-array_sum($d['net']),
            'method'=>'constant published sector shares; frozen Q2 2026 Maastricht deductible asset stock; normalized rounding',
            'purchasing_power'=>['opening_index_bps'=>10000,'definition'=>'real gross household disposable income per consumption unit','observed_reference'=>$p['households'],'score_eligible'=>false,'dynamic_series_implemented'=>false],
            'civil_aid_commitments'=>['first_fund_envelope_million'=>$p['civil_aid']['first_fund_envelope_million'],'outstanding_payments_minor'=>null,'automatic_cancellation'=>false]];
    }
}
