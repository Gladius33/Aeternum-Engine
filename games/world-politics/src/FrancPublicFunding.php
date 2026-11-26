<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Incremental four-sector franc pilot, separate from the historical EUR ledger.
 * Opening legacy base has no invented allocation or assets. Certified advances
 * pay domestic beneficiaries through banks; certified levy-funded repayments
 * extinguish their deposits, reserves and the matching treasury debt. */
final class FrancPublicFunding
{
    public const VERSION='wp54r-franc-public-funding-v1';
    public static function validate(array $v,string $day,array $eu):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['effective_on','instrument_sha','repayment_requested_franc_minor','spending_requested_franc_minor']
            ||!is_string($v['effective_on'])||($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$v['effective_on']))===false
            ||$date->format('Y-m-d')!==$v['effective_on']||$v['effective_on']<=$day
            ||$v['effective_on']<$eu['franc_rule']['effective_on']||isset($eu['franc_funding_orders'][$v['effective_on']])
            ||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha']))
            throw new \DomainException('Invalid prospective Franc funding order');
        foreach(['spending_requested_franc_minor','repayment_requested_franc_minor'] as $k)
            if(!is_int($v[$k])||$v[$k]<0||$v[$k]>100000000000000)throw new \DomainException('Invalid funding amount');
        if(($v['spending_requested_franc_minor']===0)===($v['repayment_requested_franc_minor']===0))
            throw new \DomainException('Choose one positive spending or repayment order');
    }
    public static function settle(array $targets,?int $opening,array $orders):array
    {
        $stock=$opening;$claim=0;$reserve=0;$deposit=0;$spending=0;$levy=0;$issued=0;$destroyed=0;$out=[];$seen=[];
        foreach($targets as $row){
            $day=$row['day'];$target=$row['target_base_franc_minor'];$order=$orders[$day]??null;
            $ask=$order['spending_requested_franc_minor']??0;$pay=$order['repayment_requested_franc_minor']??0;
            if($order!==null)$seen[$day]=true;
            $before=$stock;
            $capacity=max(0,$target-$stock);
            $advance=min($capacity,$ask);
            // No seizure or automatic repayment from a falling production target.
            // Only a separately authorized levy on this pilot's own deposits.
            $repay=min($pay,$claim,$reserve,$deposit);
            $stock+=$advance-$repay;$claim+=$advance-$repay;
            $reserve+=$advance-$repay;$deposit+=$advance-$repay;
            $issued+=$advance;$destroyed+=$repay;$spending+=$advance;$levy+=$repay;
            if($stock!==$opening+$issued-$destroyed||$claim!==$issued-$destroyed
                ||$reserve!==$claim||$deposit!==$reserve||$spending-$levy!==$claim
                ||$advance>$capacity||min($claim,$reserve,$deposit)<0)throw new \DomainException('Unbalanced Franc funding ledger');
            $row['opening_base_franc_minor']=$before;$row['issued_franc_minor']=$advance;
            $row['closing_base_franc_minor']=$stock;$row['excess_base_franc_minor']=max(0,$stock-$target);
            $row['cumulative_issued_franc_minor']=$issued;
            $row['central_bank_incremental_liability_franc_minor']=$reserve;$row['central_bank_incremental_claim_franc_minor']=$claim;
            $row['funding']=['instrument_sha'=>$order['instrument_sha']??null,'opening_issuance_capacity_franc_minor'=>$capacity,
                'requested_spending_franc_minor'=>$ask,'settled_public_spending_franc_minor'=>$advance,
                'unfunded_spending_franc_minor'=>$ask-$advance,'requested_repayment_franc_minor'=>$pay,
                'settled_levy_and_repayment_franc_minor'=>$repay,'unfunded_repayment_franc_minor'=>$pay-$repay,
                'destroyed_franc_minor'=>$repay,'cumulative_destroyed_franc_minor'=>$destroyed,
                'remaining_issuance_capacity_franc_minor'=>max(0,$target-$stock),
                'central_bank'=>['treasury_advance_asset_franc_minor'=>$claim,'bank_reserve_liability_franc_minor'=>$reserve],
                'treasury'=>['advance_liability_franc_minor'=>$claim,'cumulative_public_spending_franc_minor'=>$spending,
                    'cumulative_levy_receipts_franc_minor'=>$levy,'cash_balance_franc_minor'=>0],
                'commercial_banks'=>['reserve_asset_franc_minor'=>$reserve,'beneficiary_deposit_liability_franc_minor'=>$deposit],
                'domestic_beneficiaries'=>['deposit_asset_franc_minor'=>$deposit],
                'incremental_consolidated_net_financial_assets_franc_minor'=>0];
            $out[]=$row;
        }
        return ['rows'=>$out,'executed_order_days'=>array_keys($seen)];
    }
}
