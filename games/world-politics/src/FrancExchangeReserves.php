<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancExchangeMarket.php';

/** Closed EUR/XFR reserve pilot. The two settlement pools are preallocated
 * from the unallocated opening base. No intervention creates base money.
 * Foreign exchange assets and settlement counterpart are fictional, not
 * observations of France's actual reserves. */
final class FrancExchangeReserves
{
    public const VERSION='wp54r-franc-fx-reserves-v1';
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Invalid FX fields');}
    private static function date(mixed $v,string $day):void
    {if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v||$v<=$day)throw new \DomainException('FX evidence must be prospective');}
    private static function sha(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new \InvalidArgumentException('Invalid FX evidence hash');}
    private static function amount(mixed $v):void{if(!is_int($v)||$v<0||$v>10000000000000)throw new \DomainException('Invalid FX amount');}
    private static function scale(int $n,int $r,int $d):int{return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    private static function cost(int $n,int $r,int $d):int
    {return self::scale($n,$r,$d)+((($n%$d)*$r)%$d===0?0:1);}
    public static function validateTerms(array $v,string $day,array $eu):void
    {
        self::fields($v,['effective_on','official_eur_minor','counterparty_eur_minor','official_franc_pool_minor','counterparty_franc_pool_minor','minimum_official_eur_minor','instrument_sha']);
        self::date($v['effective_on'],$day);self::sha($v['instrument_sha']);
        foreach(['official_eur_minor','counterparty_eur_minor','official_franc_pool_minor','counterparty_franc_pool_minor','minimum_official_eur_minor'] as $k)self::amount($v[$k]);
        $bank=$eu['franc_bank_terms'];$crisis=$eu['franc_bank_crisis_terms'];
        $allocated=$bank['opening_reserve_allocation_franc_minor']+$crisis['facility_pool_franc_minor']+$crisis['guarantee_pool_franc_minor'];
        if($v['effective_on']<$bank['effective_on']||$v['minimum_official_eur_minor']>$v['official_eur_minor']
            ||$v['official_franc_pool_minor']+$v['counterparty_franc_pool_minor']>$eu['franc_rule']['opening_base_franc_minor']-$allocated)
            throw new \DomainException('FX pools exceed unallocated opening base or reserve floor');
    }
    public static function validateOrder(array $v,string $day,array $eu):void
    {
        self::fields($v,['effective_on','kind','eur_minor','instrument_sha']);
        self::date($v['effective_on'],$day);self::sha($v['instrument_sha']);self::amount($v['eur_minor']);
        if($v['eur_minor']===0||!in_array($v['kind'],['sell_eur','buy_eur'],true)||$v['effective_on']<$eu['franc_fx_terms']['effective_on']||isset($eu['franc_fx_orders'][$v['effective_on']]))
            throw new \DomainException('Invalid FX order');
    }
    public static function project(array $eu,array $currency,array $monetary):array
    {
        if(($eu['franc_fx_version']??null)!==self::VERSION)throw new \DomainException('Unpinned FX model');
        $terms=$eu['franc_fx_terms'];$start=$terms!==null&&$monetary['effective_from']!==null?max($terms['effective_on'],$monetary['effective_from'],$currency['effective_from']):null;
        $active=$start!==null&&$eu['day']>=$start;$rows=[];
        $official=$terms['official_eur_minor']??0;$counter=$terms['counterparty_eur_minor']??0;
        $pool=$terms['official_franc_pool_minor']??0;$cp=$terms['counterparty_franc_pool_minor']??0;
        $floor=$terms['minimum_official_eur_minor']??0;$ratio=$eu['currency_terms']['units_per_eur']??1;
        $priorValue=null;$mark=10000;$markDay=null;$market=($eu['franc_fx_market_terms']??null)!==null;
        foreach($monetary['days'] as $r){
            $day=$r['day'];if(!$active||$day<$start)continue;
            foreach($eu['currency_marks'] as $m)if($m['effective_on']<=$day&&$m['effective_on']>$currency['effective_from']){
                $mark=$m['value_index_bps'];$markDay=$m['effective_on'];
            }
            if($market){$quote=FrancExchangeMarket::quotes($eu,$day,$currency['effective_from']);$mark=$quote['value_index_bps'];$markDay=$quote['last_change_day'];}
            $numerator=$ratio*10000;$denominator=$mark;
            $beforeValue=self::scale($official,$numerator,$denominator)+$pool;
            $valuation=$priorValue===null?0:$beforeValue-$priorValue;
            $order=$eu['franc_fx_orders'][$day]??null;$kind=$order['kind']??null;$ask=$order['eur_minor']??0;$settled=0;$franc=0;
            if($kind==='sell_eur'){
                $settled=min($ask,max(0,$official-$floor),self::scale($cp,$denominator,$numerator));
                $franc=self::cost($settled,$numerator,$denominator);
                $official-=$settled;$counter+=$settled;$pool+=$franc;$cp-=$franc;
            }elseif($kind==='buy_eur'){
                $settled=min($ask,$counter,self::scale($pool,$denominator,$numerator));
                $franc=self::cost($settled,$numerator,$denominator);
                $official+=$settled;$counter-=$settled;$pool-=$franc;$cp+=$franc;
            }
            $afterValue=self::scale($official,$numerator,$denominator)+$pool;
            $rounding=$afterValue-$beforeValue;
            if($official+$counter!==$terms['official_eur_minor']+$terms['counterparty_eur_minor']
                ||$pool+$cp!==$terms['official_franc_pool_minor']+$terms['counterparty_franc_pool_minor']
                ||min($official,$counter,$pool,$cp)<0||$official<$floor||abs($rounding)>1)
                throw new \DomainException('FX settlement conservation failure');
            $rows[]=['day'=>$day,'order_kind'=>$kind,'instrument_sha'=>$order['instrument_sha']??null,'requested_eur_minor'=>$ask,
                'settled_eur_minor'=>$settled,'unsettled_eur_minor'=>$ask-$settled,'settled_franc_minor'=>$franc,
                'official_eur_reserves_minor'=>$official,'counterparty_eur_assets_minor'=>$counter,
                'official_franc_pool_minor'=>$pool,'counterparty_franc_pool_minor'=>$cp,
                'official_eur_reserve_floor_minor'=>$floor,'official_available_eur_minor'=>$official-$floor,
                'franc_per_eur_rate'=>['numerator'=>$numerator,'denominator'=>$denominator],
                'fx_mark_effective_on'=>$markDay,'official_portfolio_franc_value_minor'=>$afterValue,
                'valuation_delta_franc_minor'=>$valuation,'settlement_rounding_delta_franc_minor'=>$rounding,
                'base_money_created_franc_minor'=>0,'production_base_franc_minor'=>$r['closing_base_franc_minor'],
                'unfilled_eur_demand_minor'=>$kind==='sell_eur'?$ask-$settled:0,
                'unfilled_eur_supply_minor'=>$kind==='buy_eur'?$ask-$settled:0,
                'market_price_endogenous'=>$market];
            $priorValue=$afterValue;
        }
        return ['version'=>self::VERSION,'classification'=>'fictional_closed_fx_settlement_pilot','status'=>$terms===null?'not_configured':($active?'active':'pending_effective_date'),
            'effective_from'=>$start,'currency_pair'=>'EUR/XFR','terms'=>$terms,'latest'=>$rows===[]?null:$rows[array_key_last($rows)],'days'=>$rows,
            'official_actual_reserves_minor'=>null,'national_balance_of_payments_booked'=>false,'market_price_endogenous'=>$market,
            'franc_base_created_by_intervention'=>false,'other_currencies_modelled'=>false,'not_a_forecast'=>true];
    }
}
