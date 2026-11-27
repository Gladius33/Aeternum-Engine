<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/HouseholdIncomeReference.php';

/** Experimental distributional accounting bridge, no fiscal mutation or score. */
final class HouseholdAccount
{
    public const VERSION='wp86-household-account-v2';
    private const ANCHOR=['net_wages'=>10189,'other_net_activity'=>1081,'net_property_income'=>1403,
        'pure_household_surplus'=>2618,'cash_benefits'=>6470,'current_direct_taxes'=>-2919,
        'transfers_to_npish'=>-176,'other_net_transfers'=>103];
    private const CENSUS=['population'=>68094280,'household_population'=>66681812,'households'=>31274741];
    public static function reference():array
    {
        return ['year'=>2025,'unit'=>'tenth_billion_eur','components'=>self::ANCHOR,'rdb'=>array_sum(self::ANCHOR),
            'status'=>'provisional_rounded','sources'=>[
                'accounts'=>'https://www.insee.fr/fr/statistiques/fichier/8988934/Note_revisions_2023a2025.pdf',
                'wages'=>'https://www.insee.fr/fr/statistiques/2830244',
                'households'=>'https://www.insee.fr/fr/statistiques/1405599?geo=FRANCE-1'],
            'derived_other_activity'=>'net_activity_minus_net_wages',
            'census_2023_france_excluding_mayotte'=>self::CENSUS];
    }
    private static function ratio(int $n,int $d):int
    {
        if($n<0||$d<=0||$n>intdiv(PHP_INT_MAX,10000))throw new \DomainException('Household ratio bounds');
        $r=intdiv($n*10000,$d);
        if($r>1000000)throw new \DomainException('Household ratio out of model scope');
        return $r;
    }
    public static function units(array $cohorts,array $indicators,int $days,int $fragmentationBps):array
    {
        $pop=$indicators['population'];
        $c=$cohorts['female/00-14']+$cohorts['male/00-14'];
        if($pop<=0||$pop>100000000||$c<0||$c>$pop||$days<0||$days>8000||!in_array($fragmentationBps,[0,50],true))
            throw new \DomainException('Invalid household population');
        $hp=intdiv($pop*self::CENSUS['household_population'],self::CENSUS['population']);
        $children=intdiv(intdiv($c*14,15)*$hp,$pop);
        $h=intdiv($pop*self::CENSUS['households'],self::CENSUS['population']);
        $h+=intdiv($h*$fragmentationBps*$days,3650000);
        if($h<=0||$h>$hp-$children)throw new \DomainException('Invalid projected household structure');
        // One adult at 1 UC, each additional adult at 0.5, children under 14 at 0.3.
        return ['households_proxy'=>$h,'household_population_proxy'=>$hp,'under_14_proxy'=>$children,
            'consumption_units_tenths'=>5*($hp+$h)-2*$children];
    }
    public static function day(array $base,array $population,array $row,int $days):array
    {
        $m=$row['macro'];$p=$row['population'];$opening=$population['opening_indicators'];
        $price=self::ratio($m['price_index_scaled'],100000000);
        $wage=self::ratio($m['wage_index_scaled'],100000000);
        $gdp=self::ratio($m['nominal_gdp'],$base['daily_real_gdp']);
        $u=$m['unemployment_bps'];$u0=$base['initial_unemployment_bps'];
        if($price<=0||$wage<=0||$u<0||$u>=10000||$u0<0||$u0>=10000)throw new \DomainException('Invalid household macro inputs');
        $workers=self::ratio($p['indicators']['age_20_64'],$opening['age_20_64']);
        $employment=self::ratio(10000-$u,10000-$u0);
        $wages=intdiv(intdiv($wage*$workers,10000)*$employment,10000);
        $pop=self::ratio($p['indicators']['population'],$opening['population']);
        $benefits=self::ratio(max(0,$base['daily_social_benefits']+$m['social_expense_feedback']),$base['daily_social_benefits']);
        $transfer=intdiv($price*$pop,10000);
        $openingUnits=self::units($population['opening_cohorts'],$opening,0,0);
        $cases=[];
        foreach(['fixed_household_structure'=>0,'fragmentation_sensitivity'=>50] as $key=>$trend){
            $units=self::units($p['cohorts'],$p['indicators'],$days,$trend);
            $rent=intdiv($price*self::ratio($units['households_proxy'],$openingUnits['households_proxy']),10000);
            $factors=['net_wages'=>$wages,'other_net_activity'=>$gdp,'net_property_income'=>$gdp,
                'pure_household_surplus'=>$rent,'cash_benefits'=>$benefits,'transfers_to_npish'=>$transfer,'other_net_transfers'=>$transfer];
            $flows=[];$preTax=0;$preTaxAnchor=0;
            foreach(['net_wages','other_net_activity','net_property_income','pure_household_surplus','cash_benefits'] as $k){
                $preTax+=self::ANCHOR[$k]*$factors[$k];$preTaxAnchor+=self::ANCHOR[$k];
            }
            // Constant effective direct-tax share of the five gross income components.
            $factors['current_direct_taxes']=intdiv($preTax,$preTaxAnchor);
            foreach(self::ANCHOR as $k=>$v)$flows[$k]=$v*$factors[$k];
            $total=array_sum($flows);
            if($total<=0)throw new \DomainException('Nonpositive household disposable income');
            $nom=intdiv($total,array_sum(self::ANCHOR));
            $real=self::ratio($nom,$price);
            $uc=self::ratio($units['consumption_units_tenths'],$openingUnits['consumption_units_tenths']);
            // Counterfactual direct-tax rate shifts in percentage points of
            // the same pre-tax income proxy. No allocation of public revenue,
            // behavioural response, budget write or change to campaign score.
            $taxSensitivity=[];
            foreach([-100,0,100] as $shift){
                $delta=intdiv($preTax*$shift,10000);
                $tax=$flows['current_direct_taxes']-$delta;
                $counterfactual=$total-$delta;
                if($tax>0||$counterfactual<=0)throw new \DomainException('Invalid tax sensitivity');
                $nominal=self::ratio($counterfactual,array_sum(self::ANCHOR)*10000);
                $taxSensitivity[]=['direct_tax_rate_shift_bps'=>$shift,
                    'direct_tax_reference_scaled'=>$tax,'rdb_reference_scaled'=>$counterfactual,
                    'real_rdb_per_uc_proxy_index_bps'=>self::ratio(self::ratio($nominal,$price),$uc)];
            }
            $cases[$key]=['structure'=>$units,'direct_tax_sensitivity'=>$taxSensitivity,'components_reference_scaled'=>$flows,
                'rdb_reference_scaled'=>$total,'nominal_rdb_index_bps'=>$nom,'real_rdb_index_bps'=>$real,
                'uc_index_bps'=>$uc,'real_rdb_per_uc_proxy_index_bps'=>self::ratio($real,$uc)];
        }
        return ['day'=>$row['day'],'price_index_bps'=>$price,'working_age_index_bps'=>$workers,
            'employment_rate_index_bps'=>$employment,'cash_benefit_proxy_index_bps'=>$benefits,'cases'=>$cases];
    }
    public static function report(array $domestic,array $diplomatic):array
    {
        $d=$domestic['state'];$p=$diplomatic['state'];
        if($d['world_id']!==$p['world_id']||$d['day']!==$p['day']||!isset($d['population_model'],$d['macro_baseline'],$p['household_reference']))
            throw new \DomainException('Incoherent household account sources');
        $ref=HouseholdIncomeReference::report($p['household_reference']);
        $a=$ref['reference']['annual'][5];
        if($a['rdb_tenth_billion_eur']!==array_sum(self::ANCHOR)||$a['net_wages_tenth_billion_eur']!==self::ANCHOR['net_wages']||
            $a['cash_benefits_tenth_billion_eur']!==self::ANCHOR['cash_benefits'])throw new \DomainException('Household account reference mismatch');
        $population=$d['population_model'];$start=new \DateTimeImmutable($population['start_day']);
        $rows=[];$prev=null;$sum=['fixed_household_structure'=>0,'fragmentation_sensitivity'=>0];
        $taxSums=[];$ranges=[];
        foreach(array_slice($d['days'],-365) as $r){
            $day=new \DateTimeImmutable($r['day']);
            if($day<=$start||($prev!==null&&$r['day']!==$prev->modify('+1 day')->format('Y-m-d')))throw new \DomainException('Noncontiguous household account');
            $x=self::day($d['macro_baseline'],$population,$r,(int)$start->diff($day)->format('%a'));
            foreach($x['cases'] as $key=>$v){
                $value=$v['real_rdb_per_uc_proxy_index_bps'];$sum[$key]+=$value;
                $ranges[$key]=isset($ranges[$key])?['min_bps'=>min($ranges[$key]['min_bps'],$value),'max_bps'=>max($ranges[$key]['max_bps'],$value)]:['min_bps'=>$value,'max_bps'=>$value];
                foreach($v['direct_tax_sensitivity'] as $tax){
                    $shift=$tax['direct_tax_rate_shift_bps'];
                    $taxSums[$key][$shift]=($taxSums[$key][$shift]??0)+$tax['real_rdb_per_uc_proxy_index_bps'];
                }
            }
            $rows[]=$x;$prev=$day;
        }
        if($prev!==null&&$prev->format('Y-m-d')!==$d['day'])throw new \DomainException('Uncommitted household account');
        $committed=new \DateTimeImmutable($d['day']);
        if($committed<$start||$committed->format('Y-m-d')!==$d['day'])
            throw new \DomainException('Invalid household account committed date');
        $n=count($rows);
        if($n!==min(365,(int)$start->diff($committed)->format('%a')))
            throw new \DomainException('Incomplete household account window');
        $hashes=[];
        foreach(['domestic'=>$domestic,'diplomatic'=>$diplomatic] as $key=>$v){
            $h=trim($v['etag'],'"');if(!preg_match('/^[a-f0-9]{64}$/D',$h))throw new \DomainException('Invalid household account digest');$hashes[$key]=$h;
        }
        $taxMeans=[];
        foreach($taxSums as $key=>$byShift)foreach($byShift as $shift=>$total)
            $taxMeans[$key][]=['direct_tax_rate_shift_bps'=>$shift,'rolling_mean_index_bps'=>intdiv($total,$n)];
        $reference=self::reference();
        return ['version'=>self::VERSION,'world_id'=>$d['world_id'],'day'=>$d['day'],'reference'=>$reference,
            'account_reference_sha'=>hash('sha256',json_encode($reference,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'income_reference_sha'=>$ref['reference_sha'],'source_hashes'=>$hashes,'opening_index_bps'=>10000,
            'opening_is_observed_2027'=>false,'window_days'=>$n,'complete_365_day_window'=>$n===365,
            'latest'=>$n?$rows[$n-1]:null,'rolling_means_bps'=>$n?array_map(fn($v)=>intdiv($v,$n),$sum):null,
            'rolling_direct_tax_means'=>$n?$taxMeans:null,'observed_simulation_ranges'=>$n?$ranges:null,'ranges_are_not_confidence_intervals'=>true,
            'score_eligible'=>false,'score_rules_changed'=>false,'actual_rdb_per_uc_index_bps'=>null,
            'additional_budget_delta_minor'=>null,'direct_tax_sensitivity_is_counterfactual'=>true,'component_unit'=>'2025_reference_tenth_billion_eur_times_index_bps',
            'classification'=>'experimental_household_accounting_not_calibrated',
            'assumptions'=>['constant working-age participation','under 14 approximated by fourteen fifteenths of 0–14 cohort',
                'constant census household coverage and one first adult per household','0 or 50 bps annual linear household fragmentation sensitivity',
                'other activity and net property income follow nominal GDP','pure surplus follows prices and households',
                'cash benefits use macro public-benefit feedback as proxy','constant effective direct-tax share; no allocation of aggregate public tax feedback',
                'direct tax sensitivity holds all other flows and macro variables fixed; rate shifts are not enacted policies',
                'prices proxy household consumption deflator','reference amounts not a 2027 euro forecast']];
    }
}
