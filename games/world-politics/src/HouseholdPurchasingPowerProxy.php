<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/HouseholdIncomeReference.php';

/** Transparent EXPERIMENTAL income proxy, not the final household accounts.
 * The alternative residual assumptions are a sensitivity, not a forecast.
 * No ranking, mutation, fiscal booking or household tax attribution. */
final class HouseholdPurchasingPowerProxy
{
    public const VERSION='wp76-household-purchasing-power-proxy-v1';
    private static function index(int $n,int $d):int
    {
        if($n<0||$d<=0||$n>intdiv(PHP_INT_MAX,10000))throw new \DomainException('Invalid household proxy ratio');
        return intdiv($n*10000,$d);
    }
    public static function day(array $anchor,array $base,array $row):array
    {
        $m=$row['macro'];$population=$row['population']['indicators']['population'];
        $price=self::index($m['price_index_scaled'],100000000);
        $wage=self::index($m['wage_index_scaled'],100000000);
        $u=$m['unemployment_bps'];$u0=$base['unemployment_bps'];
        if($price<1||$wage<1||!is_int($u)||$u<0||$u>=10000||$u0<0||$u0>=10000)
            throw new \DomainException('Invalid wage/price/employment proxy');
        // Constant labour-force participation and household composition are
        // explicit approximations, not actual employment or UC observations.
        $employment=self::index(10000-$u,10000-$u0);
        $populationIndex=self::index($population,$base['population']);
        if($populationIndex<1)throw new \DomainException('Nonpositive population proxy');
        $wages=intdiv($wage*$employment,10000);
        $benefits=self::index(max(0,$base['daily_social_benefits']+$m['social_expense_feedback']),$base['daily_social_benefits']);
        // The wage index is interpreted as nominal per-worker; population
        // scaling then supplies an aggregate wage mass proxy. Benefit
        // feedback is already aggregate and must not be scaled again.
        $wages=intdiv($wages*$populationIndex,10000);
        $a=$anchor['rdb_tenth_billion_eur'];$w=$anchor['net_wages_tenth_billion_eur'];$b=$anchor['cash_benefits_tenth_billion_eur'];
        $other=$a-$w-$b;
        if($other<0||$a<=0)throw new \DomainException('Invalid reference income decomposition');
        $cases=[];
        foreach(['constant_nominal_residual'=>10000,'price_indexed_residual'=>$price] as $name=>$r){
            $nom=intdiv($w*$wages+$b*$benefits+$other*$r,$a);
            $real=self::index($nom,$price);
            $cases[$name]=['nominal_aggregate_index_bps'=>$nom,'real_per_population_proxy_index_bps'=>self::index($real,$populationIndex)];
        }
        return ['day'=>$row['day'],'price_index_bps'=>$price,'wage_mass_proxy_index_bps'=>$wages,
            'cash_benefits_proxy_index_bps'=>$benefits,'population_proxy_index_bps'=>$populationIndex,'cases'=>$cases];
    }
    public static function report(array $domestic,array $diplomatic):array
    {
        $d=$domestic['state'];$p=$diplomatic['state'];
        if($d['world_id']!==$p['world_id']||$d['day']!==$p['day']||!isset($d['population_model'],$d['macro_baseline'],$p['household_reference']))
            throw new \DomainException('Incoherent household proxy inputs');
        $ref=HouseholdIncomeReference::report($p['household_reference']);$anchor=$ref['reference']['annual'][5];
        $base=['unemployment_bps'=>$d['macro_baseline']['initial_unemployment_bps'],'daily_social_benefits'=>$d['macro_baseline']['daily_social_benefits'],
            'population'=>$d['population_model']['opening_indicators']['population']];
        $rows=[];$prev=null;$sums=['constant_nominal_residual'=>0,'price_indexed_residual'=>0];
        foreach(array_slice($d['days'],-365) as $r){
            if($prev!==null&&$r['day']!==(new \DateTimeImmutable($prev))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Noncontiguous household history');
            $row=self::day($anchor,$base,$r);$rows[]=$row;$prev=$r['day'];
            foreach($sums as $k=>$v)$sums[$k]+=$row['cases'][$k]['real_per_population_proxy_index_bps'];
        }
        if($prev!==null&&$prev!==$d['day'])throw new \DomainException('Household history not committed');
        $n=count($rows);$means=null;
        if($n>0){$means=[];foreach($sums as $k=>$v)$means[$k]=intdiv($v,$n);}
        $hashes=[];foreach(['domestic'=>$domestic,'diplomatic'=>$diplomatic] as $k=>$v){
            $h=trim($v['etag'],'"');if(!preg_match('/^[a-f0-9]{64}$/D',$h))throw new \DomainException('Missing source digest');$hashes[$k]=$h;
        }
        return ['version'=>self::VERSION,'world_id'=>$d['world_id'],'day'=>$d['day'],'reference_sha'=>$ref['reference_sha'],'source_hashes'=>$hashes,
            'classification'=>'experimental_uncalibrated_proxy_not_real_rdb_per_uc','opening_index_bps'=>10000,
            'baseline_reference_year'=>2025,'baseline_is_observed_2027'=>false,'window_days'=>$n,'complete_365_day_window'=>$n===365,
            'latest'=>$n===0?null:$rows[$n-1],'rolling_means_bps'=>$means,'actual_real_rdb_per_uc_index_bps'=>null,
            'score_eligible'=>false,'household_specific_direct_taxes_modelled'=>false,'actual_uc_modelled'=>false,
            'currency_scope'=>'eur_reference_macro_not_nominal_frf_conversion','additional_budget_delta_minor'=>null,
            'assumptions'=>['fixed participation and UC per person','aggregate social expense feedback used as benefit proxy','residual income held nominally constant or price indexed; no direct household tax/asset income ledger','macro price index substitutes consumption deflator']];
    }
}
