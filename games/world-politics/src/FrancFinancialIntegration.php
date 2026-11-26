<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudget.php';

/** Native post-service discretionary cash settlement; the old project prices
 * remain fixed parity-reference units. No new borrowing for these purchases. */
final class FrancFinancialIntegration
{
    public const VERSION='wp54r-frf-financial-integration-v1';
    public static function capital(array $r,int $ratio,int $industrial,int $equity,?array $prior):array
    {
        if($ratio<1||$ratio>10||min($industrial,$equity)<0||max($industrial,$equity)>100000000000000)throw new \DomainException('Invalid capital settlement');
        $i=$industrial*$ratio;$e=$equity*$ratio;$cash=$r['closing_state']['accounts']['treasury_frf_minor'];
        if(($i+$e>0&&!$r['debt_service_current'])||$i+$e>$cash||$r['budget']['equity_paid_frf_minor']!==0)throw new \DomainException('Unfunded native capital');
        $old=$prior['capital_settlement']??[];
        $r['closing_state']['accounts']['treasury_frf_minor']=$cash-$i-$e;
        $r['budget']['equity_paid_frf_minor']=$e;
        $r['budget']['industrial_paid_frf_minor']=$i;
        $r['capital_settlement']=['opening_discretionary_cash_frf_minor'=>$cash,'industrial_paid_frf_minor'=>$i,'equity_paid_frf_minor'=>$e,
            'cumulative_industrial_paid_frf_minor'=>($old['cumulative_industrial_paid_frf_minor']??0)+$i,
            'cumulative_equity_acquired_at_cost_frf_minor'=>($old['cumulative_equity_acquired_at_cost_frf_minor']??0)+$e,
            'closing_discretionary_cash_frf_minor'=>$cash-$i-$e,'reference_price_currency'=>'EUR','native_payment_currency'=>'FRF','debt_created_by_purchase_frf_minor'=>0];
        return $r;
    }
    private static function value(array $v,int $ratio,int $mark):int
    {return $v['FRF']+FrancNativeBudget::scale($v['EUR'],$ratio*10000,$mark);}
    /** Identified bank-holder slice, not the complete national banking sector.
     * The unknown opening funding is explicitly isolated; retained receipts
     * remain in creditor cash, not central-bank reserves or new deposits. */
    public static function bank(array $r,int $ratio,int $mark,?array $prior,?array $pilot):array
    {
        $base='legacy-state-french_credit_institutions-';$opening=['FRF'=>0,'EUR'=>0];$closing=$opening;$paid=$opening;$coupon=$opening;$writeoff=$opening;
        foreach(['FRF','EUR'] as $cur){
            $id=$base.$cur;$o=$r['debt_settlement']['opening_claims'][$id]['principal_outstanding_minor']??0;
            $p=$r['debt_settlement']['creditor_receipts'][$id]['principal_paid_minor']??0;
            $c=$r['closing_state']['claims'][$id]['principal_outstanding_minor']??0;
            $w=$r['bank_haircuts_by_claim'][$id]??0;
            $opening[$cur]=$o+$w;$closing[$cur]=$c;$paid[$cur]=$p;$writeoff[$cur]=$w;
            $coupon[$cur]=$r['debt_settlement']['creditor_receipts'][$id]['interest_paid_minor']??0;
            if($opening[$cur]-$p-$w!==$c)throw new \DomainException('Bank sovereign asset does not reconcile');
        }
        $old=$prior['bank_balance']??null;$cash=$old['retained_creditor_cash_by_currency_minor']??['FRF'=>0,'EUR'=>0];
        if($old!==null&&$old['sovereign_principal_by_currency_minor']!==$opening)throw new \DomainException('Bank opening stock discontinuity');
        $oldMark=$prior['closing_state']['mark']??10000;
        $openingAssets=self::value($opening,$ratio,$oldMark)+self::value($cash,$ratio,$oldMark);
        $capital=$old['opening_capital_proxy_frf_minor']??intdiv($openingAssets,5);
        $funding=$old['unallocated_opening_funding_liability_frf_minor']??($openingAssets-$capital);
        foreach(['FRF','EUR'] as $cur)$cash[$cur]+=$paid[$cur]+$coupon[$cur];
        $marked=self::value($closing,$ratio,$mark);$discount=$r['banking']['valuation_discount_bps']??0;$provision=FrancNativeBudget::scale($marked,$discount,10000);
        $assets=$marked-$provision+self::value($cash,$ratio,$mark);$equity=$assets-$funding;
        $result=['classification'=>'synthetic_identified_bank_slice_with_explicit_unallocated_funding','sovereign_principal_by_currency_minor'=>$closing,
            'principal_received_by_currency_minor'=>$paid,'interest_received_by_currency_minor'=>$coupon,'writeoff_by_currency_minor'=>$writeoff,'retained_creditor_cash_by_currency_minor'=>$cash,
            'sovereign_assets_before_discount_frf_minor'=>$marked,'valuation_provision_frf_minor'=>$provision,'assets_frf_minor'=>$assets,
            'opening_capital_proxy_frf_minor'=>$capital,'unallocated_opening_funding_liability_frf_minor'=>$funding,'equity_frf_minor'=>$equity,'balance_difference_frf_minor'=>$assets-$funding-$equity,
            'retained_cash_is_not_central_bank_reserves'=>true,'new_domestic_bond_holder_allocation_known'=>false];
        if($pilot!==null){
            $a=$pilot['bank_assets_franc_minor'];$d=$pilot['bank_deposit_liabilities_franc_minor'];$k=$pilot['bank_equity_franc_minor'];
            $other=$pilot['crisis']['bank_facility_liability_franc_minor']??0;$other+=$pilot['crisis']['bank_subrogated_guarantee_liability_franc_minor']??0;
            if($a!==$d+$k+$other||$pilot['bank_balance_difference_franc_minor']!==0)throw new \DomainException('Unbalanced private-credit pilot');
            $result['combined_modelled_slices']=['assets_frf_minor'=>$assets+$a,'deposit_liabilities_frf_minor'=>$d,'unallocated_funding_liability_frf_minor'=>$funding,
                'other_liabilities_frf_minor'=>$other,'equity_frf_minor'=>$equity+$k,'reserves_frf_minor'=>$pilot['bank_reserves_franc_minor'],
                'balance_difference_frf_minor'=>$assets+$a-$d-$funding-$other-$equity-$k,'full_national_balance_sheet'=>false];
        }
        return $result;
    }
}
