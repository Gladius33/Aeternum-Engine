<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Optional, bounded, fictional quote formation from prior-day certified
 * gross order pressure. Not an equilibrium model or an observed FX rate. */
final class FrancExchangeMarket
{
    public const VERSION='wp54r-franc-fx-market-v1';
    public const FLOW_VERSION='wp54r-franc-fx-flow-v1';
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
    public static function validateFlow(array $v,string $day,array $eu):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['effective_on','export_eur_share_bps','import_eur_share_bps','instrument_sha']
            ||$v['effective_on']!==$eu['franc_fx_market_terms']['effective_on']||$v['effective_on']<=$day
            ||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha']))
            throw new \DomainException('Invalid prospective trade FX flow scenario');
        foreach(['export_eur_share_bps','import_eur_share_bps'] as $k)
            if(!is_int($v[$k])||$v[$k]<0||$v[$k]>10000)throw new \DomainException('Invalid EUR settlement share');
    }
    public static function validateCapital(array $v,string $day,array $eu):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['effective_on','instrument_sha','net_private_eur_inflow_minor']
            ||!is_string($v['effective_on'])||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v['effective_on']))===false
            ||$d->format('Y-m-d')!==$v['effective_on']||$v['effective_on']<=$day||$v['effective_on']<$eu['franc_fx_flow_terms']['effective_on']
            ||isset($eu['franc_fx_capital_orders'][$v['effective_on']])
            ||!is_int($v['net_private_eur_inflow_minor'])||$v['net_private_eur_inflow_minor'] < -10000000000000||$v['net_private_eur_inflow_minor']>10000000000000
            ||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha']))
            throw new \DomainException('Invalid prospective private capital flow');
    }
    private static function scale(int $a,int $n,int $d):int
    {return intdiv($a,$d)*$n+intdiv(($a%$d)*$n,$d);}
    private static function flowPressure(array $eu,array $rows,string $start,string $day):array
    {
        $flow=$eu['franc_fx_flow_terms'];$result=[];$expected=$start;
        foreach($rows as $row){
            $d=$row['day'];if($d<$start||$d>=$day)continue;
            if($d!==$expected)throw new \DomainException('Incomplete committed FX trade history');
            $expected=(new \DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d');
            $m=$row['macro'];$i=$m['imports']??null;$x=$m['exports']??null;$p=$m['price_index_scaled']??null;
            foreach([$i,$x] as $n)if(!is_int($n)||$n<0||$n>10000000000000)throw new \DomainException('Invalid FX trade value');
            if(!is_int($p)||$p<1||$p>1000000000)throw new \DomainException('Invalid FX price reference');
            // A common domestic deflator is a nominal trade proxy, not
            // actual import/export unit prices or a balance of payments.
            $imports=self::scale(self::scale($i,$p,100000000),$flow['import_eur_share_bps'],10000);
            $exports=self::scale(self::scale($x,$p,100000000),$flow['export_eur_share_bps'],10000);
            $capital=$eu['franc_fx_capital_orders'][$d]['net_private_eur_inflow_minor']??null;
            $debtPressure=$row['frf_committed_budget']['risk']['new_unpaid_eur_demand_proxy_minor']??0;
            if(!is_int($debtPressure)||$debtPressure<0||$debtPressure>10000000000000000)throw new \DomainException('Invalid native debt FX pressure');
            $result[$d]=['eur_import_demand_proxy_minor'=>$imports,'eur_export_supply_proxy_minor'=>$exports,
                'certified_net_private_eur_inflow_minor'=>$capital,'net_eur_supply_proxy_minor'=>$exports-$imports+($capital??0)-$debtPressure,...(isset($row['frf_committed_budget'])?['new_unpaid_eur_debt_pressure_proxy_minor'=>$debtPressure]:[]),
                'official_intervention_excluded_from_pressure'=>true,'capital_coverage'=>$capital===null?'missing_assumed_zero_for_proxy':'certified_scenario'];
        }
        if($expected!==$day)throw new \DomainException('Missing preceding FX trade day');
        return $result;
    }
    // A market order on d can move only the quote on d+1. No same-day
    // circular pricing, no automatic price drift in the absence of orders.
    public static function quotes(array $eu,string $day,string $currencyStart,?array $domRows=null):array
    {
        $terms=$eu['franc_fx_market_terms']??null;
        $start=$terms===null?null:max($terms['effective_on'],$currencyStart,$eu['franc_rule']['effective_on']);
        $mark=10000;$last=null;$lastMove=0;$changes=[];
        $flow=($eu['franc_fx_flow_terms']??null)!==null;
        $pressure=$eu['franc_fx_orders'];
        if($flow&&$start!==null&&$day>=$start){
            if($domRows===null)throw new \DomainException('Committed FX trade data required');
            $pressure=self::flowPressure($eu,$domRows,$start,$day);
        }
        if($start!==null&&$day>=$start)foreach($pressure as $d=>$order){
            if($d<$start||$d>=$day)continue;
            $effective=(new \DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d');
            $n=$flow?abs($order['net_eur_supply_proxy_minor']):$order['eur_minor'];$depth=$terms['market_depth_eur_minor'];$cap=$terms['daily_move_cap_bps'];
            $magnitude=$n>=$depth?$cap:intdiv($n*$cap,$depth);
            // A sale of EUR by the reserve authority represents demand for
            // EUR from the counterparty, hence downward pressure on the Franc.
            $move=($flow?$order['net_eur_supply_proxy_minor']<0:$order['kind']==='sell_eur')?-$magnitude:$magnitude;
            $next=max(5000,min(15000,$mark+$move));
            $lastMove=$next-$mark;$mark=$next;$last=$effective;
            $changes[]=['effective_on'=>$effective,'source_order_day'=>$d,'requested_eur_minor'=>$n,
                'requested_move_index_bps'=>$move,'actual_move_index_bps'=>$lastMove,'value_index_bps'=>$mark,...($flow?['flow_pressure'=>$order]:[])];
        }
        return ['version'=>self::VERSION,'effective_from'=>$start,'value_index_bps'=>$mark,'last_change_day'=>$last,
            'today_move_index_bps'=>$last===$day?$lastMove:0,'changes'=>$changes,
            'price_rule'=>$flow?'lagged_trade_and_certified_private_capital_pressure':'lagged_gross_certified_order_pressure','autonomous_market_orders'=>false,
            ...($flow?['flow_version'=>self::FLOW_VERSION,'autonomous_trade_pressure'=>true,'actual_balance_of_payments'=>false]:[]),
            'market_clearing_equilibrium'=>false,'not_a_forecast'=>true];
    }
}
