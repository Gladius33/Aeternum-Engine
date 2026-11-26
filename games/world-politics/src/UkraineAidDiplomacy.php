<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

final class UkraineAidDiplomacy
{
    public const VERSION='wp54r-ukraine-aid-diplomacy-v1';
    private const PARTNERS=['state_ua'=>-20,'state_ru'=>10,'state_de'=>-8,'state_gb'=>-8,'state_us'=>-5,'state_pl'=>-8];
    public static function observe(array $d,string $world,string $day):array
    {
        $s=$d['state'];$prior=(new \DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d');
        if($s['world_id']!==$world||$s['day']!==$prior||!isset($s['macro_baseline']['aid_reference_v2']))throw new \DomainException('Invalid aid diplomacy source');
        $last=$s['days']===[]?null:$s['days'][array_key_last($s['days'])];
        if($last!==null&&($last['day']!==$prior||($last['ukraine_aid']['version']??null)!=='wp54r-ukraine-aid-v2'))throw new \DomainException('Missing committed aid day');
        return ['world_id'=>$world,'day'=>$day,'prior_day'=>$prior,'domestic_sha'=>trim($d['etag'],'"'),'rate_bps'=>$last['ukraine_aid']['discretionary_rate_bps']??10000];
    }
    public static function step(array $o,string $world,string $day,?array $prior):array
    {
        $keys=array_keys($o);sort($keys);
        if($keys!==['day','domestic_sha','prior_day','rate_bps','world_id']||$o['world_id']!==$world||$o['day']!==$day||$o['prior_day']!==(new \DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d')||!is_string($o['domestic_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$o['domestic_sha'])||!in_array($o['rate_bps'],[0,5000,10000,15000],true))throw new \DomainException('Invalid aid diplomacy observation');
        $signals=[];foreach(self::PARTNERS as $id=>$w){
            $target=intdiv($w*(10000-$o['rate_bps']),10000);$old=$prior['signals'][$id]??0;$signals[$id]=$old+($target===$old?0:($target>$old?1:-1)*max(1,intdiv(abs($target-$old),4)));
        }
        return ['version'=>self::VERSION,'classification'=>'fictional_bounded_partner_response_sensitivity_not_prediction','source'=>$o,'signals'=>$signals,'automatic_sanctions_or_treaties'=>false];
    }
}
