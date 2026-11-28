<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Versioned normalized household / trade feedback scenario.
 * No national-currency accounts, survey-based distribution, or rank authority.
 * Never interpret unknown bilateral exposure as a measured zero. */
final class NationalHouseholdMacro
{
    public const VERSION='wp130-household-trade-v1';
    public const BASE=1000000000;
    public const ASSUMPTIONS=['gross_income_shares_bps'=>['labour'=>7000,'transfers'=>2000,'capital'=>1000],
        'direct_tax_and_contribution_bps_on_labour_and_capital'=>2000,'consumption_units'=>'fixed_opening_index',
        'transfers'=>'price_indexed_then_scaled_by_primary_payment_fraction_proxy_not_actual_benefits',
        'capital'=>'nominal_output_index','labour'=>'nominal_wage_times_employment_ratio',
        'trade_output_gap_pass_through_bps'=>2500,'trade_gap_adjustment_days'=>180,
        'trade_import_price_pass_through_bps'=>1000,'calibrated'=>false];
    public static function mul(int $a,int $b,int $d=self::BASE):int{
        if($a<0||$b<0||$a>1000000000000||$b>1000000000000||$d<1||$d>self::BASE)throw new \DomainException('Household scaling range');
        // Both remainder products < 1e18; fail before an oversized result.
        $whole=intdiv($a,$d);
        if($whole>0&&$b>intdiv(PHP_INT_MAX,$whole))throw new \DomainException('Household scaling overflow');
        $left=$whole*$b;$right=intdiv($b,$d)*($a%$d)+intdiv(($a%$d)*($b%$d),$d);
        if($right>PHP_INT_MAX-$left)throw new \DomainException('Household scaling overflow');
        return $left+$right;
    }
    public static function trade(array $record,string $world,string $country,string $day,?array $gdp):array{
        $s=$record['state'];$sha=trim($record['etag'],'"');
        if($s['world_id']!==$world||$s['day']!==$day||!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new \DomainException('Trade day mismatch');
        $actor='state_'.strtolower($country);$loss=[];$missing=[];
        foreach($s['projections'] as $id=>$v){
            $p=$s['policies'][$id]??throw new \DomainException('Orphan trade projection');
            if($p['from']!==$actor&&$p['to']!==$actor)continue;
            if($p['status']!=='effective'||$p['effective_on']!==$v['effective_on']||$v['effective_on']>$day)throw new \DomainException('Uncommitted trade effect');
            $peer=$p['from']===$actor?$p['to']:$p['from'];
            if($v['central_usd']===null){$missing[$peer]=true;continue;}
            if(!is_int($v['central_usd'])||$v['central_usd']<0)throw new \DomainException('Invalid trade loss');
            $loss[$peer]=max($loss[$peer]??0,$v['central_usd']);
        }
        $hasGDP=($gdp['kind']??null)==='observed'&&is_int($gdp['value']??null)&&$gdp['value']>0;
        $known=$loss===[]?0:($hasGDP?(int)min(10000,round(10000*array_sum($loss)/$gdp['value'])):null);
        return ['source_name'=>'World Politics — committed economic policy projections','data_date'=>$day,'economic_sha256'=>$sha,
            'gdp_reference'=>$gdp,'known_exposure_bps'=>$known,'unknown_counterpart_count'=>count($missing),
            'coverage_complete'=>$missing===[]&&$known!==null,'classification'=>'modeled_trade_exposure_not_observed_loss'];
    }
    public static function step(array $previous,array $opening,int $shortfall,array $trade):array{
        $known=$trade['known_exposure_bps'];
        // Retain the prior target if the denominator is missing, otherwise
        // use only known exposures; a partial estimate is explicitly labeled.
        $target=$known===null?$previous['trade_gap_bps']:-intdiv($known*2500,10000);
        $gap=$previous['trade_gap_bps'];$change=$target-$gap;
        $delta=$change===0?0:($change>0?max(1,intdiv($change,180)):min(-1,intdiv($change,180)));
        $gap+=$delta;
        $adjusted=$opening;
        $adjusted['real_growth_bps_2027']=max(-3000,min(3000,$opening['real_growth_bps_2027']+$delta*365));
        $adjusted['inflation_bps_2027']=max(-1000,min(6000,$opening['inflation_bps_2027']+intdiv(max(0,-$delta)*365,10)));
        $next=NationalInvestitureForecast::step($previous,$adjusted,$shortfall);
        // Okun must use the original forecast rather than the trade-shocked trend.
        if($previous['unemployment_scaled']!==null){
            $u=$previous['unemployment_scaled'];$anchor=$opening['unemployment_bps_2027']*10000;
            $next['unemployment_scaled']=max(0,min(100000000,$u+intdiv($anchor-$u,365)+intdiv(($opening['real_growth_bps_2027']-$next['growth_bps'])*4000,365)));
        }
        $next['trade_gap_bps']=$gap;$next['trade']=$trade;
        return $next;
    }
    public static function household(array $macro,array $opening,int $primaryDue,int $primaryPaid):array{
        if($primaryDue<0||$primaryPaid<0)throw new \DomainException('Invalid household settlement proxy');
        $base=['version'=>self::VERSION,'classification'=>'synthetic_normalized_household_income_scenario',
            'unit'=>'normalized_opening_household_income_units','assumptions'=>self::ASSUMPTIONS,'rankable'=>false];
        $u0=$opening['unemployment_opening_bps'];$u=$macro['unemployment_scaled'];
        if($u0===null||$u===null||$u0>=10000)return $base+['available'=>false,'reason'=>'opening_or_current_unemployment_unknown','purchasing_power_index'=>null];
        $employment=intdiv((100000000-$u)*self::BASE,100000000-$u0*10000);
        $price=$macro['price_index'];
        $wage=self::mul($macro['indicative_real_wage_index'],$price);
        $labour=self::mul(self::mul(700000000,$wage),$employment);
        $fraction=$primaryDue===0?self::BASE:intdiv(min($primaryDue,$primaryPaid)*self::BASE,$primaryDue);
        $transfers=self::mul(self::mul(200000000,$price),$fraction);
        $capital=self::mul(100000000,self::mul($macro['real_output_index'],$price));
        $tax=intdiv(($labour+$capital)*2000,10000);$rdb=$labour+$transfers+$capital-$tax;
        // Exact fixed-point division via base-1000 long division, no 1e21 product.
        $real=intdiv($rdb,$price);$rem=$rdb%$price;
        for($i=0;$i<3;$i++){$real=$real*1000+intdiv($rem*1000,$price);$rem=($rem*1000)%$price;}
        $real=min(1000000000000,$real);
        return $base+['available'=>true,'nominal_labour_income'=>$labour,'nominal_transfer_proxy'=>$transfers,
            'nominal_capital_income'=>$capital,'direct_tax_contribution_proxy'=>$tax,'nominal_disposable_income'=>$rdb,
            'price_index'=>$price,'employment_ratio_index'=>$employment,'primary_payment_fraction_index'=>$fraction,
            'real_disposable_income'=>$real,'opening_disposable_income'=>840000000,
            'purchasing_power_index'=>self::mul($real,self::BASE,840000000),
            'measurement_warning'=>'fixed consumption units; not survey-based RDB or official score'];
    }
}
