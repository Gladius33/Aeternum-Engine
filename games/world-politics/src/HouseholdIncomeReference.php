<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Historical national accounts reference and arithmetic benchmark only.
 * Does not calibrate behavioural coefficients, forecast 2027, or award score. */
final class HouseholdIncomeReference
{
    public const VERSION='wp74-household-income-reference-v1';
    private const FIELDS=['rdb_tenth_billion_eur','net_wages_tenth_billion_eur','cash_benefits_tenth_billion_eur','nominal_rdb_growth_bps','consumption_deflator_growth_bps','real_rdb_per_uc_growth_bps','uc_growth_bps','saving_rate_bps'];
    public static function pin():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-household-income-v1.json'),true,32,JSON_THROW_ON_ERROR);
        self::validate($p);
        return ['pack'=>$p,'sha'=>self::digest($p)];
    }
    private static function digest(array $p):string
    {return hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));}
    private static function validate(array $p):void
    {
        if($p['schema']!==self::VERSION||$p['country']!=='FR'||$p['retrieved_on']!=='2026-11-26'||count($p['annual'])!==6||count($p['quarterly'])!==4)
            throw new \DomainException('Invalid household reference scope');
        foreach($p['sources'] as $s)if(!str_starts_with($s['url'],'https://www.insee.fr/')||$s['published_on']>$p['retrieved_on'])throw new \DomainException('Invalid source provenance');
        foreach($p['annual'] as $i=>$r){
            if($r['year']!==2020+$i||$r['status']!==($i===5?'provisional':($i===4?'semi_final':'published')))throw new \DomainException('Invalid annual vintage');
            foreach(self::FIELDS as $k)if(!is_int($r[$k]??null)||abs($r[$k])>1000000)throw new \DomainException('Invalid annual observation');
            if($r['rdb_tenth_billion_eur']<=0||$r['net_wages_tenth_billion_eur']<0||$r['cash_benefits_tenth_billion_eur']<0)throw new \DomainException('Invalid household flow');
        }
        foreach($p['quarterly'] as $i=>$r){
            if($r['period']!==['2025-Q3','2025-Q4','2026-Q1','2026-Q2'][$i])throw new \DomainException('Invalid quarterly vintage');
            foreach(['nominal_rdb_growth_bps','nominal_rdb_per_uc_growth_bps','real_rdb_per_uc_growth_bps'] as $k)if(!is_int($r[$k]??null)||abs($r[$k])>5000)throw new \DomainException('Invalid quarterly observation');
        }
    }
    /** Arithmetic identity applied to rounded rates; not a behavioural model. */
    public static function realPerUcGrowth(int $nominal,int $prices,int $uc):int
    {
        foreach([$nominal,$prices,$uc] as $v)if($v<=-10000||$v>100000)throw new \InvalidArgumentException('Invalid relative growth');
        $den=(10000+$prices)*(10000+$uc);
        return intdiv((10000+$nominal)*100000000+intdiv($den,2),$den)-10000;
    }
    public static function report(array $pin):array
    {
        $p=$pin['pack'];self::validate($p);
        if(!hash_equals($pin['sha'],self::digest($p)))throw new \DomainException('Household reference digest mismatch');
        $bench=[];
        foreach($p['annual'] as $r){
            $computed=self::realPerUcGrowth($r['nominal_rdb_growth_bps'],$r['consumption_deflator_growth_bps'],$r['uc_growth_bps']);
            $bench[]=['year'=>$r['year'],'computed_bps'=>$computed,'published_bps'=>$r['real_rdb_per_uc_growth_bps'],
                'rounding_residual_bps'=>$computed-$r['real_rdb_per_uc_growth_bps']];
        }
        $sensitivity=[];
        foreach([-200,-100,0,100,200] as $shock)$sensitivity[]=['deflator_growth_bps'=>200+$shock,'nominal_rdb_growth_bps'=>300,'uc_growth_bps'=>50,
            'real_per_uc_growth_bps'=>self::realPerUcGrowth(300,200+$shock,50)];
        return ['version'=>self::VERSION,'reference_sha'=>$pin['sha'],'reference'=>$p,'arithmetic_benchmark'=>$bench,
            'illustrative_sensitivity'=>$sensitivity,'benchmark_is_behavioral_calibration'=>false,
            'investiture_real_rdb_per_uc_minor'=>null,'score_eligible'=>false,'actual_2027_observation'=>false];
    }
}
