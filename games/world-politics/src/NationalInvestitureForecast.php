<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Forecast-backed opening scenario, never an observed future value.
 * Fixed-point units are normalized to annual projected 2027 GDP, not money.
 * CPI is a proxy for the unknown GDP deflator. */
final class NationalInvestitureForecast
{
    public const VERSION='wp130-investiture-forecast-v1';
    public const BASE=1000000000;
    private const BLOB='3e027de4b0b79861e78a9ec907f748b3ad49e567';
    public static function pack():array{
        static $p=null;if($p!==null)return $p;
        $raw=file_get_contents(__DIR__.'/../data/wp130-investiture-macro-forecast-v1.json');
        if(!is_string($raw)||!hash_equals(self::BLOB,sha1('blob '.strlen($raw)."\0".$raw)))throw new \DomainException('Unpinned macro forecast');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if($p['opening_day']!=='2027-05-14'||count($p['countries'])!==43)throw new \DomainException('Forecast roster');
        return $p;
    }
    private static function lerp(int $a,int $b,int $days):int{return $a+intdiv(($b-$a)*$days,365);}
    public static function opening(string $cc):array{
        $p=self::pack();$r=$p['countries'][$cc]??throw new \DomainException('Unknown forecast country');
        $d=$r['fiscal'];$m=$r['weo'];$days=134;
        if($cc==='FR'){
            $g26=$d['nominal_gdp_million_eur'][0];$g27=$d['nominal_gdp_million_eur'][1];
            $macro=$p['sources']['france'];
        }else{
            $m27=$m['real_growth_bps'][1];$c27=$m['consumer_price_inflation_bps'][1];
            $g26=intdiv(self::BASE*100000000,(10000+$m27)*(10000+$c27));$g27=self::BASE;$macro=$p['sources']['macro'];
        }
        $debt26=intdiv($g26*$d['gross_debt_bps'][0],10000);$debt27=intdiv($g27*$d['gross_debt_bps'][1],10000);
        $debt=self::lerp($debt26,$debt27,$days);
        $balance=$cc==='FR'?-$d['balance_bps'][1]:$d['expense_bps'][1]-$d['revenue_bps'][1];
        $deficit=intdiv($g27*$balance,10000);$adjustment=$debt27-$debt26-$deficit;
        return ['version'=>self::VERSION,'classification'=>'estimated_opening_not_observed','country'=>$cc,'opening_day'=>$p['opening_day'],
            'data_as_of'=>$p['as_of'],'reference_blob'=>self::BLOB,'unit'=>$cc==='FR'?'million_EUR':'normalized_reference_GDP_units',
            'annual_nominal_gdp_2026'=>$g26,'annual_nominal_gdp_2027'=>$g27,'opening_gross_debt'=>$debt,
            'debt_bridge'=>['end_2026'=>$debt26,'end_2027'=>$debt27,'days'=>$days,'deficit_to_day'=>intdiv($deficit*$days,365),
                'stock_flow_adjustment_to_day'=>$debt-$debt26-intdiv($deficit*$days,365),'annual_stock_flow_residual'=>$adjustment],
            'real_growth_bps_2027'=>$macro['real_growth_bps'][1]??$m['real_growth_bps'][1],
            'inflation_bps_2027'=>$macro['consumer_price_inflation_bps'][1]??$m['consumer_price_inflation_bps'][1],
            'unemployment_bps_2027'=>($macro['unemployment_bps']??$m['unemployment_bps'])[1],
            'unemployment_opening_bps'=>($m['unemployment_bps'][0]===null||$m['unemployment_bps'][1]===null)?null:self::lerp(($macro['unemployment_bps']??$m['unemployment_bps'])[0],($macro['unemployment_bps']??$m['unemployment_bps'])[1],$days),
            'fiscal_forecasts'=>$d,'macro_source'=>$macro,'fiscal_source'=>$cc==='FR'?$p['sources']['france']:$p['sources']['fiscal'],
            'nominal_gdp_source'=>$cc==='FR'?$p['sources']['france_nominal_gdp']:null,'method'=>'linear stock interpolation across annual forecast endpoints; CPI deflator proxy for foreign normalized GDP; unemployment annual-average interpolation convention',
            'scenario_uncertainty'=>'mixed_vintage_uncalibrated_not_confidence_interval','rankable'=>false];
    }
    /** Daily real-output, consumer-price and indicative real-wage index.
     * Pure conditional model: spending shortfall reduces demand (0.5 multiplier);
     * Okun response 0.4; price response 0.1; wage proxy is not disposable income.
     * All coefficients are explicit, uncalibrated and bounded. */
    public static function step(array $previous,array $opening,int $primaryShortfall):array{
        if(abs($primaryShortfall)>self::BASE)throw new \DomainException('Fiscal shock range');
        $shock=max(-2000,min(2000,intdiv($primaryShortfall*365*10000,self::BASE)));
        $g=max(-3000,min(3000,$opening['real_growth_bps_2027']-intdiv($shock,2)));
        $pi=max(-1000,min(6000,$opening['inflation_bps_2027']-intdiv($shock,10)));
        $u=$previous['unemployment_scaled'];
        if($u!==null)$u=max(0,min(100000000,$u+intdiv(($opening['real_growth_bps_2027']-$g)*400,365)));
        // Index values are 1e9 at investiture. 1e9*6000 remains within int64.
        $grow=static fn(int $v,int $bps):int=>max(1,min(1000000000000,$v+intdiv($v*$bps,3650000)));
        return ['real_output_index'=>$grow($previous['real_output_index'],$g),'price_index'=>$grow($previous['price_index'],$pi),
            'indicative_real_wage_index'=>$grow($previous['indicative_real_wage_index'],intdiv($g,2)),
            'unemployment_scaled'=>$u,'growth_bps'=>$g,'inflation_bps'=>$pi,'fiscal_impulse_bps'=>$shock,
            'classification'=>'synthetic_uncalibrated_daily_macro_scenario_not_purchasing_power'];
    }
}
