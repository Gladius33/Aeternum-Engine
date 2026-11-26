<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** WP52: observed reference aggregates, fictional service demand and
 * operational capacity, incremental fiscal effects only. */
final class FrenchPublicServices
{
    public const VERSION='wp52-services-v1';
    private static function clamp(int $v,int $lo,int $hi):int{return max($lo,min($hi,$v));}
    private static function scale(int $v,int $bps):int{return intdiv($v,10000)*$bps+intdiv(($v%10000)*$bps,10000);}
    private static function ratio(int $n,int $d):int
    {if($d<=0||$n<0)throw new \DomainException('Invalid service ratio');return intdiv($n*10000,$d);}
    private static function demandIndex(int $n,int $base):int{return self::clamp(self::ratio($n,$base),5000,15000);}
    public static function load(string $start,array $population,array $macro):array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-25-fr-services-v1.json'),true,32,JSON_THROW_ON_ERROR);
        return self::build($p,$start,$population,$macro);
    }
    public static function build(array $p,string $start,array $population,array $macro):array
    {
        if(($p['schema']??null)!=='wp52-fr-services-v1'||($p['country']??null)!=='FR'||(($p['as_of']??null)!==$start&&!($start==='2027-05-14'&&($p['as_of']??null)==='2026-11-25'))
            ||!is_array($p['observations']??null)||count($p['observations'])!==33)
            throw new \DomainException('Invalid services source pack');
        $rows=[];
        foreach($p['observations'] as $o){
            $src=$p['sources'][$o['source_id']??'']??null;
            if(!is_array($src)||!is_string($src['url']??null)||!preg_match('~^https://(?:www\.)?(?:drees\.solidarites-sante\.gouv\.fr|education\.gouv\.fr|insee\.fr)/~D',$src['url'])
                ||!is_string($src['published_on']??null)||$src['published_on']>$start||($src['updated_on']??$start)>$start
                ||($o['kind']??null)!=='observed'||!is_int($o['value']??null)||$o['value']<0||$o['value']>1000000000000
                ||!is_string($o['metric']??null)||!is_string($o['period']??null)||!is_string($o['unit']??null))
                throw new \DomainException('Invalid service observation');
            $k=$o['metric'].'/'.$o['period'];if(isset($rows[$k]))throw new \DomainException('Duplicate service observation');
            $rows[$k]=$o;
        }
        $get=function(string $m,string $period,string $unit)use($rows):int{
            $r=$rows[$m.'/'.$period]??throw new \DomainException('Missing service anchor');
            if($r['unit']!==$unit||$r['value']<=0)throw new \DomainException('Wrong service unit or amount');return $r['value'];
        };
        $health=$get('current_health_expenditure','2025','EUR');$hp=$get('health_public_finance_share','2025','basis_points');
        $edu=$get('education_expenditure','2025','EUR');$ep=0;
        foreach(['state','local','other_public'] as $k)$ep+=$get('education_'.$k.'_share','2025','basis_points');
        if($hp>10000||$ep>10000||$ep+$get('education_business_share','2025','basis_points')+$get('education_household_share','2025','basis_points')!==10000)
            throw new \DomainException('Service financing shares');
        $pensions=$get('pension_expenditure','2023','EUR');
        $daily=fn(int $n):int=>intdiv($n*100,365);
        $base=['health'=>$daily(self::scale($health,$hp)),'education'=>$daily(self::scale($edu,$ep)),'pensions'=>$daily($pensions)];
        if(array_sum($base)>$macro['daily_primary_expense'])throw new \DomainException('Services exceed primary budget proxy');
        $i=$population['opening_indicators'];
        return ['version'=>self::VERSION,'classification'=>'fictional_services_observed_anchors','source_pack'=>$p,
            'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'start_day'=>$start,...($start!==$p['as_of']?['opening_anchor_status'=>'historical_observations_carried_forward_as_scenario_assumptions','opening_anchor_vintage'=>$p['as_of']]:[]),'opening_demography'=>$i,'opening_unemployment_bps'=>$macro['initial_unemployment_bps'],
            'baseline_daily_public_minor'=>$base,'health_public_share_bps'=>$hp,'education_public_share_bps'=>$ep,
            'full_beds'=>$get('hospital_full_beds','2024','beds'),'hospital_employees'=>$get('hospital_employees','2024','persons'),
            'partial_places'=>$get('hospital_partial_places','2024','places'),'home_places'=>$get('hospital_home_capacity','2024','places'),
            'annual_emergency_visits'=>$get('emergency_visits','2024','visits'),
            'school_pupils'=>$get('school_pupils','2026-rentree','persons'),'school_personnel'=>$get('school_personnel','2026-rentree','persons'),
            'pension_recipients'=>$get('pension_direct_recipients','2023','persons'),'pension_contributors'=>$get('pension_contributors','2023','persons'),
            'health_older_weight'=>3,'baseline_occupied_bed_bps'=>8500,'baseline_school_capacity_bps'=>10500,
            'funding_operational_floor_bps'=>5000,'capacity_adjustment_days'=>180,'pension_public_proxy_share_bps'=>10000,
            'coefficients'=>'fictional_uncalibrated','no_double_count_baseline'=>true,'currency'=>'EUR','unit'=>'minor'];
    }
    public static function assertValid(array $c,string $start,array $pop,array $macro):void
    {if(!isset($c['source_pack'])||self::build($c['source_pack'],$start,$pop,$macro)!==$c)throw new \DomainException('Services assumptions mismatch');}
    /** Plan from prior population, not today's uncommitted outcomes.
     * Only demographic volume differences, never baseline spending or another
     * price index, flow into WP48's already inclusive primary budget. */
    public static function plan(array $c,?array $previous):array
    {
        $b=$c['opening_demography'];$i=$previous['population']['indicators']??$b;
        $w=$c['health_older_weight']-1;
        $health=self::demandIndex($i['population']+$i['age_65_plus']*$w,$b['population']+$b['age_65_plus']*$w);
        $edu=self::demandIndex($i['education_age_proxy'],$b['education_age_proxy']);
        $pens=self::demandIndex($i['age_65_plus'],$b['age_65_plus']);
        $working=self::demandIndex($i['age_20_64'],$b['age_20_64']);
        $u=self::clamp($previous['macro']['unemployment_bps']??$c['opening_unemployment_bps'],0,3000);
        $contributors=self::scale(self::scale($c['pension_contributors'],$working),self::ratio(10000-$u,10000-$c['opening_unemployment_bps']));
        $idx=['health'=>$health,'education'=>$edu,'pensions'=>$pens];$deltas=[];$cost=[];
        foreach($idx as $k=>$v){
            $base=$c['baseline_daily_public_minor'][$k];$deltas[$k]=self::scale($base,$v-10000);$cost[$k]=$base+$deltas[$k];
        }
        return ['demand_index_bps'=>$idx,'daily_reference_needs_minor'=>$cost,'incremental_expense_minor'=>$deltas,
            'total_incremental_expense_minor'=>array_sum($deltas),'pension_transfer_delta_minor'=>$deltas['pensions'],
            'pension_recipients_proxy'=>self::scale($c['pension_recipients'],$pens),'contributors_proxy'=>$contributors,
            'pupils_proxy'=>self::scale($c['school_pupils'],$edu)];
    }
    /** Current-day paid-primary share is an aggregate proxy, not an earmarked
     * payment. Prior arrears paid first by convention. Funding constrains
     * available capacity; physical beds/headcount aren't magically created. */
    public static function settle(array $c,array $plan,?array $previous,array $treasury,int $expense):array
    {
        if($expense<=0)throw new \DomainException('Invalid service fiscal flow');
        $currentPaid=max(0,min($expense,($treasury['primary_paid']??$expense)-($treasury['opening_primary_arrears']??0)));
        $paid=self::ratio($currentPaid,$expense);
        $operationalTarget=max($c['funding_operational_floor_bps'],$paid);
        $old=$previous['services']['operational_capacity_bps']??10000;
        $gap=$operationalTarget-$old;
        $capacity=$old+($gap===0?0:($gap>0?1:-1)*max(1,intdiv(abs($gap),$c['capacity_adjustment_days'])));
        $healthNeed=self::scale(self::scale($c['full_beds'],$c['baseline_occupied_bed_bps']),$plan['demand_index_bps']['health']);
        $beds=self::scale($c['full_beds'],$capacity);$served=min($healthNeed,$beds);
        $schoolCapacity=self::scale(self::scale($c['school_pupils'],$c['baseline_school_capacity_bps']),$capacity);
        $pupils=$plan['pupils_proxy'];$schoolServed=min($pupils,$schoolCapacity);
        $pensionNeed=$plan['daily_reference_needs_minor']['pensions'];
        $pensionPaid=self::scale($pensionNeed,$paid);
        $pressure=[
            'workers'=>min(250,intdiv(10000-self::ratio($schoolServed,max(1,$pupils)),40)+intdiv(10000-self::ratio($served,max(1,$healthNeed)),80)),
            'retired'=>min(250,intdiv(10000-$paid,80)+intdiv(10000-self::ratio($served,max(1,$healthNeed)),40))];
        return ['version'=>self::VERSION,...$plan,'current_primary_paid_minor'=>$currentPaid,'primary_funding_bps'=>$paid,
            'operational_capacity_bps'=>$capacity,'health'=>['physical_full_beds_reference'=>$c['full_beds'],'operational_beds_proxy'=>$beds,
                'daily_bed_demand_proxy'=>$healthNeed,'served_bed_demand_proxy'=>$served,'unserved_bed_demand_proxy'=>$healthNeed-$served,
                'hospital_employees_reference'=>$c['hospital_employees'],'partial_places_reference'=>$c['partial_places'],'home_places_reference'=>$c['home_places'],
                'daily_emergency_visits_proxy'=>intdiv(self::scale($c['annual_emergency_visits'],$plan['demand_index_bps']['health']),365)],
            'education'=>['pupils_proxy'=>$pupils,'capacity_proxy'=>$schoolCapacity,'served_pupils_proxy'=>$schoolServed,
                'unserved_pupils_proxy'=>$pupils-$schoolServed,'personnel_reference'=>$c['school_personnel']],
            'pensions'=>['recipients_proxy'=>$plan['pension_recipients_proxy'],'contributors_proxy'=>$plan['contributors_proxy'],
                'contributors_per_recipient_bps'=>self::ratio($plan['contributors_proxy'],max(1,$plan['pension_recipients_proxy'])),
                'daily_reference_need_minor'=>$pensionNeed,'daily_paid_proxy_minor'=>$pensionPaid,'daily_unpaid_proxy_minor'=>$pensionNeed-$pensionPaid],
            'next_day_pressure_delta'=>$pressure,'payment_allocation'=>'proportional_proxy_not_earmarked'];
    }
    public static function project(array $dom):array
    {
        $c=$dom['services_model'];$months=[];$years=[];$days=[];
        foreach($dom['days'] as $r){
            $s=$r['services'];$day=$r['day'];$days[]=['day'=>$day,'incremental_expense_minor'=>$s['incremental_expense_minor'],
                'total_incremental_expense_minor'=>$s['total_incremental_expense_minor'],'primary_funding_bps'=>$s['primary_funding_bps'],
                'unserved_bed_demand_proxy'=>$s['health']['unserved_bed_demand_proxy'],'unserved_pupils_proxy'=>$s['education']['unserved_pupils_proxy'],
                'unpaid_pension_proxy_minor'=>$s['pensions']['daily_unpaid_proxy_minor']];
            foreach(['month','year'] as $type){
                $key=substr($day,0,$type==='month'?7:4);$v=$type==='month'?($months[$key]??null):($years[$key]??null);
                $v??=['period'=>$key,'first_day'=>$day,'last_day'=>$day,'days'=>0,'incremental_expense_minor'=>['health'=>0,'education'=>0,'pensions'=>0],
                    'total_incremental_expense_minor'=>0,'unserved_bed_days_proxy'=>0,'unpaid_pension_proxy_minor'=>0];
                $v['last_day']=$day;$v['days']++;
                foreach($s['incremental_expense_minor'] as $k=>$n)$v['incremental_expense_minor'][$k]+=$n;
                $v['total_incremental_expense_minor']+=$s['total_incremental_expense_minor'];
                $v['unserved_bed_days_proxy']+=$s['health']['unserved_bed_demand_proxy'];
                $v['unpaid_pension_proxy_minor']+=$s['pensions']['daily_unpaid_proxy_minor'];
                if($type==='month')$months[$key]=$v;else $years[$key]=$v;
            }
        }
        $last=$dom['days']===[]?null:$dom['days'][array_key_last($dom['days'])]['services'];
        return ['version'=>self::VERSION,'world_id'=>$dom['world_id'],'day'=>$dom['day'],'classification'=>'fictional_services_observed_anchors',
            'currency'=>'EUR','unit'=>'minor','source_sha'=>$c['source_sha'],'reference'=>$c['source_pack'],'assumptions'=>array_diff_key($c,['source_pack'=>true]),
            'initial_plan'=>self::plan($c,null),'latest'=>$last,'days'=>$days,'months'=>array_values($months),'years'=>array_values($years),'not_a_forecast'=>true];
    }
}
