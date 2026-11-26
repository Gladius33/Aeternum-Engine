<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudget.php';
require_once __DIR__.'/FrancExternalFacility.php';

/** Certified two-sided FX session against the existing official EUR pool.
 * Amounts use currency-specific minor units, not nominal major-unit quotes.
 * Each order has explicit, finite external counterparty capacity. */
final class FrancExternalMarket
{
    public const VERSION='wp54r-external-market-v1';
    public static function validate(array $o,string $day):void
    {
        $keys=array_keys($o);sort($keys);
        if($keys!==['counterparty_eur_minor','counterparty_foreign_minor','currency','effective_on','eur_minor_per_10000_units','instrument_sha','kind','quantity_minor']
            ||!in_array($o['currency'],FrancExternalFacility::CURRENCIES,true)||!in_array($o['kind'],['mark','buy','sell'],true)
            ||!is_string($o['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$o['instrument_sha'])
            ||!is_string($o['effective_on'])||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$o['effective_on']))===false||$d->format('Y-m-d')!==$o['effective_on']||$o['effective_on']<=$day)
            throw new \DomainException('Invalid foreign market order');
        foreach(['quantity_minor','counterparty_eur_minor','counterparty_foreign_minor'] as $k)
            if(!is_int($o[$k])||$o[$k]<0||$o[$k]>10000000000000)throw new \DomainException('Invalid foreign market capacity');
        if(!is_int($o['eur_minor_per_10000_units'])||$o['eur_minor_per_10000_units']<1||$o['eur_minor_per_10000_units']>100000
            ||($o['kind']==='mark'&&($o['quantity_minor']!==0||$o['counterparty_eur_minor']!==0||$o['counterparty_foreign_minor']!==0))
            ||($o['kind']!=='mark'&&$o['quantity_minor']===0))throw new \DomainException('Invalid foreign market quote or quantity');
    }
    public static function trade(array $o,string $day,int $opening,int $floor,int $eur,int $eurFloor):array
    {
        self::validate($o,(new \DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d'));
        if($o['effective_on']!==$day||$opening<$floor||$eur<$eurFloor)throw new \DomainException('Invalid foreign market balances');
        $price=$o['eur_minor_per_10000_units'];$foreign=0;$cost=0;
        if($o['kind']==='buy'){
            $foreign=min($o['quantity_minor'],$o['counterparty_foreign_minor'],FrancNativeBudget::scale($eur-$eurFloor,10000,$price));
            $cost=FrancNativeBudget::scale($foreign,$price,10000,true);
        }elseif($o['kind']==='sell'){
            $foreign=min($o['quantity_minor'],$opening-$floor,FrancNativeBudget::scale($o['counterparty_eur_minor'],10000,$price));
            $cost=FrancNativeBudget::scale($foreign,$price,10000);
            // A sale below one EUR minor unit is not a free transfer.
            if($cost===0)$foreign=0;
        }
        $direction=match($o['kind']){'buy'=>1,'sell'=>-1,default=>0};
        $after=$opening+$direction*$foreign;$afterEur=$eur-$direction*$cost;
        $cpE=$o['counterparty_eur_minor']+$direction*$cost;$cpF=$o['counterparty_foreign_minor']-$direction*$foreign;
        if($after<$floor||$afterEur<$eurFloor||min($cpE,$cpF)<0||$after+$cpF!==$opening+$o['counterparty_foreign_minor']||$afterEur+$cpE!==$eur+$o['counterparty_eur_minor'])throw new \DomainException('Foreign exchange conservation');
        return ['version'=>self::VERSION,'kind'=>$o['kind'],'instrument_sha'=>$o['instrument_sha'],'eur_minor_per_10000_units'=>$price,
            'foreign_traded_minor'=>$foreign,'eur_traded_minor'=>$cost,'unfilled_foreign_minor'=>$o['quantity_minor']-$foreign,
            'opening_foreign_reserve_minor'=>$opening,'closing_foreign_reserve_minor'=>$after,'opening_official_eur_minor'=>$eur,'closing_official_eur_minor'=>$afterEur,
            'opening_counterparty_eur_minor'=>$o['counterparty_eur_minor'],'closing_counterparty_eur_minor'=>$cpE,'opening_counterparty_foreign_minor'=>$o['counterparty_foreign_minor'],'closing_counterparty_foreign_minor'=>$cpF,'base_money_created_frf_minor'=>0];
    }
}
