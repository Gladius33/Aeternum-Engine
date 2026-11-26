<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeSectors.php';

/** Fictional cash-limited public capital pilot, no automatic legal authorization. */
final class IndustrialCapital
{
    public const VERSION='wp54r-industrial-capital-v1';
    private const PROJECTS=[
        'manufacturing'=>['budget'=>36000000000,'daily'=>200000000,'lead'=>60,'capacity'=>120],
        'energy-efficiency'=>['budget'=>24000000000,'daily'=>150000000,'lead'=>45,'capacity'=>80],
        'industrial-training'=>['budget'=>12000000000,'daily'=>100000000,'lead'=>90,'capacity'=>60]
    ];
    public static function initial(string $day):array
    {
        $projects=[];foreach(self::PROJECTS as $id=>$p)$projects[$id]=['status'=>'not_started','paid_minor'=>0,'commission_on'=>null,'capacity_scaled'=>0,'budget_minor'=>$p['budget']];
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated','mode'=>'not_started',
            'projects'=>$projects,'paid_today_minor'=>0,'total_paid_minor'=>0,'capacity_delta_bps'=>0,
            'production_delta_bps'=>0,'employment_delta_bps'=>0];
    }
    public static function isAction(?string $action):bool{return in_array($action,['start-industrial-pilot','pause-industrial-pilot','resume-industrial-pilot'],true);}
    public static function validate(array $s,string $action):void
    {
        $expected=match($action){'start-industrial-pilot'=>'not_started','pause-industrial-pilot'=>'active','resume-industrial-pilot'=>'paused',default=>null};
        if($expected===null||$s['mode']!==$expected)throw new \DomainException('Invalid industrial programme transition');
    }
    public static function advance(array $s,string $day,?string $action,int $cash,bool $current,?array $prior):array
    {
        if($s['version']!==self::VERSION||$day!==(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d')||$cash<0)throw new \DomainException('Invalid industrial day');
        $s['day']=$day;$s['paid_today_minor']=0;
        if(self::isAction($action)){self::validate($s,$action);$s['mode']=$action==='pause-industrial-pilot'?'paused':'active';}
        $credit=isset($prior['frf_committed_budget']['sector_version'])?FrancNativeSectors::credit($prior):($prior['treasury']['banking']['credit_availability_bps']??10000);
        $trade=$prior['macro']['trade_exposure_bps']??0;
        $unemployment=$prior['macro']['unemployment_bps']??800;
        // Bounded fictional input, skills and finance constraints; never spend
        // cash needed for existing obligations. Unpaid work is not capital.
        $factor=max(2000,min(10000,10000-intdiv(max(0,$trade),2)-max(0,800-$unemployment)*5-intdiv(max(0,10000-$credit),2)));
        $available=$current&&$s['mode']==='active'?intdiv($cash,10):0;
        $capacity=0;$completed=true;
        foreach(self::PROJECTS as $id=>$p){
            $r=$s['projects'][$id];
            if($r['status']==='operational'){
                // Depreciation in sub-basis-point units, 2% approximate per year.
                $r['capacity_scaled']=max(0,$r['capacity_scaled']-max(1,intdiv($r['capacity_scaled'],18250)));
            }elseif($r['status']==='commissioning'&&$day>=$r['commission_on']){
                $r['status']='operational';$r['capacity_scaled']=$p['capacity']*10000;
            }elseif(in_array($r['status'],['not_started','building','funding_paused'],true)&&$s['mode']!=='not_started'){
                $pay=min($available,$p['budget']-$r['paid_minor'],intdiv($p['daily']*$factor,10000));
                $r['paid_minor']+=$pay;$available-=$pay;$s['paid_today_minor']+=$pay;
                $r['status']=$pay>0?'building':'funding_paused';
                if($r['paid_minor']===$p['budget']){
                    $r['status']='commissioning';$r['commission_on']=(new \DateTimeImmutable($day))->modify('+'.$p['lead'].' days')->format('Y-m-d');
                }
            }
            $capacity+=$r['capacity_scaled'];$s['projects'][$id]=$r;
            if($r['status']!=='operational')$completed=false;
        }
        if($completed)$s['mode']='completed';
        $s['total_paid_minor']+=$s['paid_today_minor'];
        if($s['total_paid_minor']!==array_sum(array_column($s['projects'],'paid_minor'))||$s['paid_today_minor']>$cash)throw new \DomainException('Industrial capital cash identity');
        $s['capacity_delta_bps']=intdiv($capacity,10000);
        $s['production_delta_bps']=$s['capacity_delta_bps'];$s['employment_delta_bps']=intdiv($s['capacity_delta_bps'],2);
        return $s;
    }
}
