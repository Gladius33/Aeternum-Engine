<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/IndustrialProgrammeCatalog.php';

/** Fictional domestic-capacity agents attached to public identity keys.
 * No real balance sheets, relocation intentions, predictions or asset locations.
 * All quantities are dimensionless scenario indices, not company valuations.
 */
final class FirmEconomy
{
    public const VERSION='wp54r-firm-economy-v1';
    private const MAX_CAPACITY=11000;
    private const MIN_CAPACITY=7000;
    private static function parameter(string $id,string $key,int $min,int $max):int
    {
        return $min+(hexdec(substr(hash('sha256',self::VERSION.'|'.$key.'|'.$id),0,6))%($max-$min+1));
    }
    public static function config():array
    {
        $ids=[];foreach(IndustrialProgrammeCatalog::companies()['companies'] as $r)$ids[]=$r['id'];
        sort($ids,SORT_STRING);
        $out=[];
        foreach($ids as $id)$out[$id]=['weight'=>self::parameter($id,'weight',50,250),
            'resilience'=>self::parameter($id,'resilience',120,450),
            'lead_days'=>self::parameter($id,'lead',90,180),
            'mobility'=>self::parameter($id,'mobility',100,500)];
        return $out;
    }
    public static function initial(string $day,array $config):array
    {
        $firms=[];
        foreach($config as $id=>$spec)$firms[$id]=['capacity_bps'=>10000,'phase'=>'stable',
            'stress_days'=>0,'relief_days'=>0,'project_due'=>null,'project_delta_bps'=>0,'completed_projects'=>0];
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated',
            'firms'=>$firms,'capacity_index_bps'=>10000,'production_delta_bps'=>0,'employment_delta_bps'=>0,
            'relocation_projects'=>0,'investment_projects'=>0,'not_a_forecast'=>true];
    }
    public static function advance(array $s,string $day,array $config,?array $prior):array
    {
        if($s['version']!==self::VERSION||$day!==(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d')
            ||array_keys($s['firms'])!==array_keys($config))throw new \DomainException('Invalid firm transition');
        // Prior committed economic conditions. No political affiliation or
        // government-targeted company ranking determines an agent response.
        $macro=$prior['macro']??[];
        $growth=max(-2500,min(2500,($macro['real_output_index_bps']??10000)-10000));
        $credit=max(0,min(10000,$prior['treasury']['banking']['credit_availability_bps']??10000));
        $trade=max(0,min(2000,$macro['trade_exposure_bps']??0));
        $cost=max(0,min(3000,$macro['corporation_payroll']['labour_cost_gap_bps']??0));
        $stress=max(0,-$growth)+intdiv(10000-$credit,3)+intdiv($trade,2)+intdiv($cost,2);
        $relocation=0;$investment=0;$sum=0;$weights=0;
        foreach($config as $id=>$spec){
            $r=$s['firms'][$id];
            $bad=$stress>$spec['resilience'];
            $good=$stress<intdiv($spec['resilience'],2)&&$growth>=0&&$credit>=9000;
            $r['stress_days']=$bad?min(3650,$r['stress_days']+1):0;
            $r['relief_days']=$good?min(3650,$r['relief_days']+1):0;
            if($r['project_due']!==null){
                // A planned project is not a capacity change. Cancel a
                // relocation after sustained relief; investment can be postponed.
                if(($r['phase']==='relocation_planned'&&$r['relief_days']>=30)
                    ||($r['phase']==='investment_planned'&&$r['stress_days']>=30)){
                    $r['phase']='stable';$r['project_due']=null;$r['project_delta_bps']=0;
                }elseif($r['project_due']===$day){
                    $r['capacity_bps']=max(self::MIN_CAPACITY,min(self::MAX_CAPACITY,$r['capacity_bps']+$r['project_delta_bps']));
                    $r['completed_projects']++;$r['project_due']=null;$r['project_delta_bps']=0;$r['phase']='stable';
                    $r['stress_days']=0;$r['relief_days']=0;
                }
            }elseif($r['stress_days']>=60&&$r['capacity_bps']>self::MIN_CAPACITY){
                $r['phase']='relocation_planned';$r['project_delta_bps']=-min($spec['mobility'],$r['capacity_bps']-self::MIN_CAPACITY);
                $r['project_due']=(new \DateTimeImmutable($day))->modify('+'.$spec['lead_days'].' days')->format('Y-m-d');
            }elseif($r['relief_days']>=180&&$growth>=100&&$r['capacity_bps']<self::MAX_CAPACITY){
                $r['phase']='investment_planned';$r['project_delta_bps']=min(200,self::MAX_CAPACITY-$r['capacity_bps']);
                $r['project_due']=(new \DateTimeImmutable($day))->modify('+'.$spec['lead_days'].' days')->format('Y-m-d');
            }
            if($r['phase']==='relocation_planned')$relocation++;
            if($r['phase']==='investment_planned')$investment++;
            $sum+=$r['capacity_bps']*$spec['weight'];$weights+=$spec['weight'];$s['firms'][$id]=$r;
        }
        $idx=intdiv($sum,$weights);
        $s['day']=$day;$s['capacity_index_bps']=$idx;
        // The macro economy already contains these firms. Transmit only the
        // change in installed capacity, never add the firm's entire output.
        $s['production_delta_bps']=intdiv($idx-10000,5);
        $s['employment_delta_bps']=intdiv($idx-10000,8);
        $s['relocation_projects']=$relocation;$s['investment_projects']=$investment;
        return $s;
    }
}
