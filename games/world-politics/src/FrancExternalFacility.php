<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudget.php';

/** New fictional foreign-currency facility, disbursed directly against
 * an earmarked external purchase. No overlap with the opening EUR debt.
 * Dedicated pre-existing foreign reserves only; no FRF issuance. */
final class FrancExternalFacility
{
    public const VERSION='wp54r-frf-external-facility-v1';
    public const CURRENCIES=['USD','GBP','CHF','JPY','CNY','CAD','AUD'];
    public static function validate(array $v,string $day):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['coupon_bps','currency','effective_on','eur_minor_per_10000_units','instrument_sha','maturity_on','minimum_reserve_minor','opening_reserve_minor','principal_minor']||
            !in_array($v['currency'],self::CURRENCIES,true)||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha']))throw new \DomainException('Invalid external facility');
        foreach(['effective_on','maturity_on'] as $k)if(!is_string($v[$k])||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v[$k]))===false||$d->format('Y-m-d')!==$v[$k])throw new \DomainException('Invalid facility date');
        if($v['effective_on']<=$day||$v['maturity_on']<=$v['effective_on']||$v['maturity_on']>(new \DateTimeImmutable($v['effective_on']))->modify('+50 years')->format('Y-m-d'))throw new \DomainException('Invalid facility maturity');
        foreach(['principal_minor','opening_reserve_minor','minimum_reserve_minor'] as $k)if(!is_int($v[$k])||$v[$k]<0||$v[$k]>10000000000000)throw new \DomainException('Invalid facility amount');
        if($v['principal_minor']===0||$v['minimum_reserve_minor']>$v['opening_reserve_minor']||!is_int($v['coupon_bps'])||$v['coupon_bps']<0||$v['coupon_bps']>3000||!is_int($v['eur_minor_per_10000_units'])||$v['eur_minor_per_10000_units']<1||$v['eur_minor_per_10000_units']>100000)throw new \DomainException('Invalid facility pricing');
    }
    private static function value(int $n,array $v,int $ratio,int $mark):int
    {return FrancNativeBudget::scale(FrancNativeBudget::scale($n,$v['eur_minor_per_10000_units'],10000),$ratio*10000,$mark);}
    public static function step(array $v,string $day,int $ratio,int $mark,bool $paused,?array $prior,?array $market=null):array
    {
        self::validate($v,(new \DateTimeImmutable($v['effective_on']))->modify('-1 day')->format('Y-m-d'));
        if($day<$v['effective_on']||($prior===null&&$day!==$v['effective_on'])||($prior!==null&&($prior['terms']!==$v||$day!==(new \DateTimeImmutable($prior['day']))->modify('+1 day')->format('Y-m-d'))))throw new \DomainException('External facility continuity');
        $opening=$prior['principal_outstanding_minor']??0;$reserve=$prior['reserve_minor']??$v['opening_reserve_minor'];
        $rate=$prior['current_cross_rate']??$v['eur_minor_per_10000_units'];$previousRate=$rate;
        $marketEnabled=$market!==null||isset($prior['current_cross_rate']);$openingReserve=$reserve;
        if($market!==null){if($market['opening_foreign_reserve_minor']!==$reserve)throw new \DomainException('Market reserve mismatch');$reserve=$market['closing_foreign_reserve_minor'];$rate=$market['eur_minor_per_10000_units'];}
        $priced=array_replace($v,['eur_minor_per_10000_units'=>$rate]);$previousPriced=array_replace($v,['eur_minor_per_10000_units'=>$previousRate]);
        $issue=$prior===null&&!$paused?$v['principal_minor']:0;
        $interest=FrancNativeBudget::scale($opening,$v['coupon_bps'],3650000);
        $interestDue=($prior['interest_arrears_minor']??0)+$interest;
        $due=$day>=$v['maturity_on']?$opening:0;
        $available=$paused?0:$reserve-$v['minimum_reserve_minor'];
        $ip=min($interestDue,$available);$pp=min($due,$available-$ip);
        $close=$opening+$issue-$pp;$res=$reserve-$ip-$pp;
        $oldMark=$prior['mark']??$mark;
        $oldValue=self::value($opening,$previousPriced,$ratio,$oldMark);$marked=self::value($opening,$priced,$ratio,$mark);$value=self::value($close,$priced,$ratio,$mark);
        if($res<$v['minimum_reserve_minor']||$close<0||$close!==$opening+$issue-$pp||$res+$ip+$pp!==$reserve)throw new \DomainException('External facility stock mismatch');
        return ['version'=>self::VERSION,'day'=>$day,'terms'=>$v,'currency'=>$v['currency'],'mark'=>$mark,'opening_principal_minor'=>$opening,'issued_minor'=>$issue,'earmarked_external_purchase_minor'=>$issue,'disbursement_status'=>$prior['disbursement_status']??($paused?'cancelled_by_moratorium':'disbursed'),'principal_paid_minor'=>$pp,'interest_paid_minor'=>$ip,'principal_outstanding_minor'=>$close,'principal_arrears_minor'=>$due-$pp,'interest_arrears_minor'=>$interestDue-$ip,'opening_reserve_minor'=>$openingReserve,'reserve_minor'=>$res,'opening_debt_frf_minor'=>$oldValue,'closing_debt_frf_minor'=>$value,'fx_revaluation_frf_minor'=>$marked-$oldValue,'transaction_delta_frf_minor'=>$value-$marked,'reserve_valuation_frf_minor'=>self::value($res,$priced,$ratio,$mark),'reference_eur_debt_included'=>false,'domestic_money_created_minor'=>0,'cross_rate_fixed_scenario'=>!$marketEnabled,...($marketEnabled?['current_cross_rate'=>$rate,'opening_cross_rate'=>$previousRate,'market_trade'=>$market,'net_market_reserve_flow_minor'=>$reserve-$openingReserve]:[])];
    }
}
