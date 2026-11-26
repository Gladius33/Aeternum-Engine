<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/StrategicDefensePlanning.php';

/** Civilian service continuity, based on committed simulation observations.
 * No tactical capacity, force loyalty, repression or conflict prediction. */
final class CivilContinuityReport
{
    public const VERSION='wp72-civil-continuity-v1';
    private static function unsigned(mixed $v):int
    {if(!is_int($v)||$v<0)throw new \DomainException('Invalid continuity observation');return $v;}
    private static function coverage(int $served,int $need):?int
    {if($served>$need)throw new \DomainException('Served exceeds demand');if($need===0)return null;if($need>intdiv(PHP_INT_MAX,10000))throw new \DomainException('Continuity ratio exceeds safe integer domain');return intdiv($served*10000,$need);}
    public static function report(array $domestic,array $diplomatic):array
    {
        $d=$domestic['state'];$p=$diplomatic['state'];
        if($d['world_id']!==$p['world_id']||$d['day']!==$p['day']||!isset($d['services_model'],$p['defense_reference']))
            throw new \DomainException('Incoherent civilian continuity sources');
        $ref=StrategicDefensePlanning::report($p['defense_reference'],$d['world_id'],$d['day']);
        $rows=array_slice($d['days'],-30);$previous=null;$daily=[];
        $tot=['health_demand_proxy'=>0,'health_served_proxy'=>0,'school_demand_proxy'=>0,'school_served_proxy'=>0,
            'pension_due_proxy_minor'=>0,'pension_paid_proxy_minor'=>0,'primary_funding_bps_sum'=>0];
        foreach($rows as $row){
            $day=$row['day'];
            if(($previous!==null&&$day!==(new \DateTimeImmutable($previous))->modify('+1 day')->format('Y-m-d'))||$day>$d['day'])
                throw new \DomainException('Continuity history not contiguous');
            $s=$row['services'];$h=$s['health'];$e=$s['education'];$r=$s['pensions'];$t=$row['treasury'];
            $v=['day'=>$day,'health_demand_proxy'=>self::unsigned($h['daily_bed_demand_proxy']),'health_served_proxy'=>self::unsigned($h['served_bed_demand_proxy']),
                'school_demand_proxy'=>self::unsigned($e['pupils_proxy']),'school_served_proxy'=>self::unsigned($e['served_pupils_proxy']),
                'pension_due_proxy_minor'=>self::unsigned($r['daily_reference_need_minor']),'pension_paid_proxy_minor'=>self::unsigned($r['daily_paid_proxy_minor']),
                'primary_funding_bps'=>self::unsigned($s['primary_funding_bps']),'primary_arrears_minor'=>self::unsigned($t['primary_arrears'])];
            if($v['primary_funding_bps']>10000)throw new \DomainException('Invalid primary funding ratio');
            self::coverage($v['health_served_proxy'],$v['health_demand_proxy']);self::coverage($v['school_served_proxy'],$v['school_demand_proxy']);self::coverage($v['pension_paid_proxy_minor'],$v['pension_due_proxy_minor']);
            foreach($tot as $k=>$unused)$tot[$k]+=$k==='primary_funding_bps_sum'?$v['primary_funding_bps']:$v[$k];
            $daily[]=$v;$previous=$day;
        }
        if($previous!==null&&$previous!==$d['day'])throw new \DomainException('Continuity history does not reach current day');
        $n=count($daily);$latest=$n===0?null:$daily[$n-1];$window=null;
        if($n>0)$window=['days'=>$n,'first_day'=>$daily[0]['day'],'last_day'=>$latest['day'],'complete_30_day_window'=>$n===30,
            'health_coverage_bps'=>self::coverage($tot['health_served_proxy'],$tot['health_demand_proxy']),
            'school_coverage_bps'=>self::coverage($tot['school_served_proxy'],$tot['school_demand_proxy']),
            'pension_payment_coverage_bps'=>self::coverage($tot['pension_paid_proxy_minor'],$tot['pension_due_proxy_minor']),
            'mean_primary_funding_bps'=>intdiv($tot['primary_funding_bps_sum'],$n),
            'unserved_bed_days_proxy'=>$tot['health_demand_proxy']-$tot['health_served_proxy'],
            'unserved_pupil_days_proxy'=>$tot['school_demand_proxy']-$tot['school_served_proxy'],
            'unpaid_pension_flow_proxy_minor'=>$tot['pension_due_proxy_minor']-$tot['pension_paid_proxy_minor'],
            'closing_primary_arrears_minor'=>$latest['primary_arrears_minor']];
        $hashes=[];foreach(['domestic'=>$domestic,'diplomatic'=>$diplomatic] as $k=>$v){
            $hash=trim($v['etag'],'"');if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new \DomainException('Missing continuity source digest');$hashes[$k]=$hash;
        }
        return ['version'=>self::VERSION,'world_id'=>$d['world_id'],'day'=>$d['day'],'classification'=>'fictional_committed_civil_service_proxies',
            'reference_sha'=>$ref['reference_sha'],'source_hashes'=>$hashes,'window'=>$window,'latest'=>$latest,'daily'=>$daily,
            'currency'=>'EUR','currency_scope'=>'eur_reference_ledger_not_native_frf','national_service'=>$ref['national_service'],
            'actual_civil_emergency_capacity'=>null,'additional_budget_delta_minor'=>null,'automatic_security_action'=>false,'not_a_forecast'=>true];
    }
}
