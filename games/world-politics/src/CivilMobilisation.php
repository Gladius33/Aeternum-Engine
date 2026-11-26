<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Fictional attendance estimates, not forecasts of real protest or security deployments. */
final class CivilMobilisation
{
    public const VERSION='wp54-civil-mobilisation-v1';
    private const WEIGHTS=['Paris'=>1800,'Marseille'=>600,'Lyon'=>600,'Toulouse'=>450,'Lille'=>450,
        'Bordeaux'=>400,'Nantes'=>350,'Strasbourg'=>300,'Rennes'=>250,'Nice'=>250,'other'=>4550];
    public static function initial(string $day):array
    {
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated',
            'participation_estimate'=>0,'estimate_low'=>0,'estimate_high'=>0,'cities'=>array_fill_keys(array_keys(self::WEIGHTS),0),
            'fatigue_index'=>0,'civic_service_continuity_index'=>1000,'not_a_forecast'=>true];
    }
    public static function observe(array $r):array
    {
        $n=$r['population']['indicators']['population']??null;
        $funding=$r['services']['primary_funding_bps']??null;
        $e=['population'=>$n,'service_funding_bps'=>$funding];self::check($e);return $e;
    }
    public static function check(array $e):void
    {
        if(count($e)!==2||!is_int($e['population']??null)||$e['population']<1||$e['population']>200000000
            ||!is_int($e['service_funding_bps']??null)||$e['service_funding_bps']<0||$e['service_funding_bps']>10000)
            throw new \InvalidArgumentException('Invalid civic observation');
    }
    private static function toward(int $a,int $b,int $n):int
    {$d=$b-$a;return $a+($d===0?0:($d>0?1:-1)*max(1,intdiv(abs($d),$n)));}
    public static function advance(array $s,string $day,array $e,int $excess):array
    {
        self::check($e);
        if($s['version']!==self::VERSION||$day!==(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d'))
            throw new \DomainException('Nonconsecutive mobilisation day');
        // Scenario-only attendance, not cumulative unique people. No crowd/security
        // confrontation ratios, geographic force dispositions or tactical inference.
        $excess=max(0,min(1000,$excess));$pop=$e['population'];
        $target=intdiv($pop*$excess,30000);
        $target=intdiv($target*(1000-intdiv($s['fatigue_index'],2)),1000);
        $n=min($pop,self::toward($s['participation_estimate'],$target,4));
        $fatigue=self::toward($s['fatigue_index'],$excess>0?min(800,$excess):0,20);
        $counts=[];$allocated=0;
        foreach(self::WEIGHTS as $id=>$weight){$counts[$id]=intdiv($n*$weight,10000);$allocated+=$counts[$id];}
        $counts['other']+=$n-$allocated;
        // Civil services depend on funding, not a model of coercive capacity.
        $continuity=self::toward($s['civic_service_continuity_index'],intdiv($e['service_funding_bps'],10),15);
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated',
            'participation_estimate'=>$n,'estimate_low'=>intdiv($n*7,10),'estimate_high'=>min($pop,intdiv($n*13,10)),
            'cities'=>$counts,'fatigue_index'=>$fatigue,'civic_service_continuity_index'=>$continuity,'not_a_forecast'=>true];
    }
}
