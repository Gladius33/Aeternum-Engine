<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Immutable dated projection, not future observations. Monetary amounts in
 * the source are million EUR; all booked amounts are integer EUR minor units. */
final class InvestitureEconomicProjection
{
    public const DAY='2027-05-14';
    public const VERSION='wp54r-investiture-projection-v1';
    public static function load():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-fr-investiture-projection-v1.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!==self::VERSION||$p['start_day']!==self::DAY)throw new \DomainException('Invalid opening pack');
        return $p;
    }
    private static function scale(int $n,int $a,int $d):int
    {
        if($d<=0||$n===PHP_INT_MIN||$a===PHP_INT_MIN)throw new \DomainException('Invalid projection ratio');
        $sign=($n<0 xor $a<0)?-1:1;$n=abs($n);$a=abs($a);
        // Exact floor of absolute product / denominator without constructing
        // a national-scale product. Signed result truncates toward zero.
        $q=intdiv($n,$d);$r=$n%$d;$acc=0;$rem=0;$whole=0;
        if($q!==0&&$a>intdiv(PHP_INT_MAX,$q))throw new \DomainException('Projection overflow');
        $base=$q*$a;
        while($a>0){
            if($a%2===1){
                if($whole>PHP_INT_MAX-$acc)throw new \DomainException('Projection overflow');
                $acc+=$whole;
                if($rem>=$d-$r){$rem-=$d-$r;$acc++;}else $rem+=$r;
            }
            $a=intdiv($a,2);if($a===0)break;
            if($whole>intdiv(PHP_INT_MAX-1,2))throw new \DomainException('Projection overflow');
            $whole*=2;
            if($r>=$d-$r){$r-=$d-$r;$whole++;}else $r*=2;
        }
        if($acc>PHP_INT_MAX-$base)throw new \DomainException('Projection overflow');
        return $sign*($base+$acc);
    }
    public static function project():array
    {
        $p=self::load();$o=$p['observed'];$f=$p['forecast'];$a=$p['assumptions'];$u=100000000;
        $days=(int)(new \DateTimeImmutable('2026-12-31'))->diff(new \DateTimeImmutable(self::DAY))->days;
        $wealthDays=(int)(new \DateTimeImmutable('2025-12-31'))->diff(new \DateTimeImmutable(self::DAY))->days;
        $end26=self::scale($f['2026']['gdp']*$u,$f['2026']['debt_bps'],10000);
        $end27=self::scale($f['2027']['gdp']*$u,$f['2027']['debt_bps'],10000);
        $deficit27=self::scale($f['2027']['gdp']*$u,$f['2027']['deficit_bps'],10000);
        $deficitToDay=self::scale($deficit27,$days,365);
        $adjust=self::scale($end27-$end26-$deficit27,$days,365);
        $debt=$end26+$deficitToDay+$adjust;
        $range=self::scale($a['debt_sensitivity_annual_flow_million']*$u,$days,365);
        $wealth=[];
        foreach(['low'=>$a['wealth_low_bps'],'central'=>$a['wealth_annual_bps'],'high'=>$a['wealth_high_bps']] as $case=>$rate){
            foreach($o['wealth_2025'] as $k=>$v){
                if($v['nonfinancial']+$v['net_financial']!==$v['net'])throw new \DomainException('Observed wealth identity');
                $nf=$v['nonfinancial']*$u+self::scale($v['nonfinancial']*$u,$rate*$wealthDays,3650000);
                $fin=$v['net_financial']*$u+self::scale($v['net_financial']*$u,$rate*$wealthDays,3650000);
                $wealth[$case][$k]=['nonfinancial_minor'=>$nf,'net_financial_minor'=>$fin,'net_minor'=>$nf+$fin];
            }
            $sum=0;foreach(['households','nonfinancial_corporations','financial_corporations','public_administrations'] as $k)$sum+=$wealth[$case][$k]['net_minor'];
            $wealth[$case]['sector_rounding_residual_minor']=$wealth[$case]['national']['net_minor']-$sum;
        }
        return ['version'=>self::VERSION,'classification'=>'scenario_projection_not_observation','source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'source_pack'=>$p,'opening_day'=>self::DAY,'opening_debt_minor'=>$debt,'debt_bridge'=>['observed_q2_2026_minor'=>$o['debt_q2_2026']*$u,'projected_end_2026_minor'=>$end26,'projected_end_2027_minor'=>$end27,'days_since_end_2026'=>$days,'deficit_since_end_2026_minor'=>$deficitToDay,'stock_flow_adjustment_since_end_2026_minor'=>$adjust,'low_minor'=>$debt-$range,'high_minor'=>$debt+$range],
            'wealth_scenarios'=>$wealth,'wealth_method'=>'linear historical nominal sensitivity; saving and revaluation not separately identified; no liquid assets created',
            'annual_gdp_minor'=>$f['2027']['gdp']*$u,'annual_primary_expense_minor'=>($f['2027']['spending_ex_tax_credits']-$f['2027']['interest'])*$u,
            'annual_revenue_minor'=>$f['2027']['spending_ex_tax_credits']*$u-$deficit27,'annual_interest_minor'=>$f['2027']['interest']*$u,
            'annual_tax_minor'=>self::scale($f['2027']['gdp']*$u,4420,10000),'inflation_bps'=>$f['2027']['inflation_bps'],
            'real_growth_bps'=>$f['2027']['real_growth_bps'],'historical_net_debt_not_projected'=>true,'pre_investiture_score_days'=>0];
    }
    public static function apply(array $b):array
    {
        $p=self::project();$old=$b['daily_real_gdp'];$new=intdiv($p['annual_gdp_minor'],365);
        foreach(['daily_consumption','daily_npish_consumption','daily_investment','daily_government_consumption','daily_exports','daily_imports','daily_social_benefits'] as $k)$b[$k]=self::scale($b[$k],$new,$old);
        $b['daily_real_gdp']=$new;
        $b['daily_statistical_discrepancy']=$new-($b['daily_consumption']+$b['daily_npish_consumption']+$b['daily_investment']+$b['daily_government_consumption']+$b['daily_exports']-$b['daily_imports']);
        $b['daily_revenue']=intdiv($p['annual_revenue_minor'],365);$b['daily_primary_expense']=intdiv($p['annual_primary_expense_minor'],365);
        $b['daily_tax_base']=intdiv($p['annual_tax_minor'],365);
        $b['opening_debt']=$p['opening_debt_minor'];$b['opening_debt_period']=self::DAY;
        $b['initial_annual_inflation_bps']=$p['inflation_bps'];
        $b['assumed_annual_interest_bps']=self::scale($p['annual_interest_minor'],10000,$b['opening_debt']);
        $b['classification']='projected_investiture_anchors_with_fictional_initialization';
        $b['opening_projection']=$p;return $b;
    }
}
