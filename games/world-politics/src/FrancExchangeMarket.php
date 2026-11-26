<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Optional, bounded, fictional quote formation from prior-day certified
 * gross order pressure. Not an equilibrium model or an observed FX rate. */
final class FrancExchangeMarket
{
    public const VERSION='wp54r-franc-fx-market-v1';
    public static function validate(array $v,string $day,array $eu):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['daily_move_cap_bps','effective_on','instrument_sha','market_depth_eur_minor']
            ||!is_string($v['effective_on'])||$v['effective_on']!==$eu['franc_fx_terms']['effective_on']||$v['effective_on']<=$day
            ||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha'])
            ||!is_int($v['market_depth_eur_minor'])||$v['market_depth_eur_minor']<1||$v['market_depth_eur_minor']>10000000000000
            ||!is_int($v['daily_move_cap_bps'])||$v['daily_move_cap_bps']<1||$v['daily_move_cap_bps']>500)
            throw new \DomainException('Invalid prospective market scenario');
    }
    // A market order on d can move only the quote on d+1. No same-day
    // circular pricing, no automatic price drift in the absence of orders.
    public static function quotes(array $eu,string $day,string $currencyStart):array
    {
        $terms=$eu['franc_fx_market_terms']??null;
        $start=$terms===null?null:max($terms['effective_on'],$currencyStart,$eu['franc_rule']['effective_on']);
        $mark=10000;$last=null;$lastMove=0;$changes=[];
        if($start!==null&&$day>=$start)foreach($eu['franc_fx_orders'] as $d=>$order){
            if($d<$start||$d>=$day)continue;
            $effective=(new \DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d');
            $n=$order['eur_minor'];$depth=$terms['market_depth_eur_minor'];$cap=$terms['daily_move_cap_bps'];
            $magnitude=$n>=$depth?$cap:intdiv($n*$cap,$depth);
            // A sale of EUR by the reserve authority represents demand for
            // EUR from the counterparty, hence downward pressure on the Franc.
            $move=$order['kind']==='sell_eur'?-$magnitude:$magnitude;
            $next=max(5000,min(15000,$mark+$move));
            $lastMove=$next-$mark;$mark=$next;$last=$effective;
            $changes[]=['effective_on'=>$effective,'source_order_day'=>$d,'requested_eur_minor'=>$n,
                'requested_move_index_bps'=>$move,'actual_move_index_bps'=>$lastMove,'value_index_bps'=>$mark];
        }
        return ['version'=>self::VERSION,'effective_from'=>$start,'value_index_bps'=>$mark,'last_change_day'=>$last,
            'today_move_index_bps'=>$last===$day?$lastMove:0,'changes'=>$changes,
            'price_rule'=>'lagged_gross_certified_order_pressure','autonomous_market_orders'=>false,
            'market_clearing_equilibrium'=>false,'not_a_forecast'=>true];
    }
}
