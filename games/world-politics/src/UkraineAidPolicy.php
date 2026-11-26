<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Scenario-level direct budget-cost proxy only. Do not add gross historical
 * aid valuations, EU loans or promises to national cash expenditure. */
final class UkraineAidPolicy
{
    public const VERSION='wp54r-ukraine-aid-v1';
    public const ACTIONS=['ukraine-aid-stop'=>0,'ukraine-aid-reduce'=>5000,'ukraine-aid-maintain'=>10000,'ukraine-aid-increase'=>15000,'ukraine-aid-resume'=>10000];
    public static function validate(array $events,string $start,string $day):void
    {
        if(!array_is_list($events)||count($events)>7305)throw new \DomainException('Invalid aid history');
        $last=null;
        foreach($events as $e){
            if(!is_array($e)||array_keys($e)!==['day','action']||!is_string($e['day'])||!isset(self::ACTIONS[$e['action']])||$e['day']<$start||$e['day']>$day||($last!==null&&$e['day']<=$last))throw new \DomainException('Invalid aid decision');
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$e['day']);if(!$d||$d->format('Y-m-d')!==$e['day'])throw new \DomainException('Invalid aid date');
            $last=$e['day'];
        }
    }
    public static function step(array $baseline,array $events,string $day,?array $prior):array
    {
        $a=$baseline['opening_projection']['source_pack']['assumptions'];$annual=$a['aid_annual_direct_cost_proxy']*100000000;
        $protected=intdiv($annual*(10000-$a['aid_discretionary_share_bps']),10000);
        $discretionary=$annual-$protected;$rate=10000;$action=null;
        foreach($events as $e){if($e['day']>=$day)break;$rate=self::ACTIONS[$e['action']];$action=$e['action'];}
        $ordinal=(int)(new \DateTimeImmutable(InvestitureEconomicProjection::DAY))->diff(new \DateTimeImmutable($day))->days;
        $slice=fn(int $n):int=>intdiv($n*$ordinal,365)-intdiv($n*($ordinal-1),365);
        $base=$slice($discretionary);$fixed=$slice($protected);
        $actual=$slice(intdiv($discretionary*$rate,10000));$delta=$actual-$base;
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'historical_direct_cost_anchor_scenario_allocation','action'=>$action,'discretionary_rate_bps'=>$rate,
            'baseline_discretionary_minor'=>$base,'protected_cost_proxy_minor'=>$fixed,'discretionary_planned_minor'=>$actual,
            'budget_delta_minor'=>$delta,'cumulative_planned_savings_minor'=>($prior['cumulative_planned_savings_minor']??0)-$delta,
            'existing_obligations_cancelled'=>false,'european_obligations_changed'=>false,'unquantified_aid_categories_not_assumed_zero'=>true,
            'actual_contract_inventory_known'=>false,'paid_amount_not_inferred_from_appropriation'=>true];
    }
}
