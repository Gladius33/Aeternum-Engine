<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Single consolidated fictional commercial-bank pilot. Opening reserves
 * are allocated from the existing monetary base, not newly issued. Public
 * payment deposits and private-credit deposits are disjoint subaccounts.
 * No real bank regulation, interbank settlement or full national M2. */
final class FrancCommercialBank
{
    public const VERSION='wp54r-franc-bank-v1';
    public const CRISIS_VERSION='wp54r-franc-bank-crisis-v1';
    private const CRISIS_KINDS=['cash_withdrawal','cash_redeposit','liquidity_support','liquidity_repayment','guarantee_payout'];
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Invalid bank scenario fields');}
    private static function date(mixed $v,string $day):void{
        if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v||$v<=$day)
            throw new \DomainException('Bank evidence must be prospective');
    }
    private static function sha(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new \InvalidArgumentException('Invalid bank evidence hash');}
    private static function amount(mixed $v):void{if(!is_int($v)||$v<0||$v>100000000000000)throw new \DomainException('Invalid bank amount');}
    private static function scale(int $n,int $r,int $d):int{return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    public static function validateTerms(array $v,string $day,array $eu):void
    {
        self::fields($v,['effective_on','opening_reserve_allocation_franc_minor','minimum_capital_bps','minimum_liquidity_bps','instrument_sha']);
        self::date($v['effective_on'],$day);self::sha($v['instrument_sha']);self::amount($v['opening_reserve_allocation_franc_minor']);
        if($v['effective_on']<$eu['franc_rule']['effective_on']||$v['opening_reserve_allocation_franc_minor']===0
            ||$v['opening_reserve_allocation_franc_minor']>$eu['franc_rule']['opening_base_franc_minor'])
            throw new \DomainException('Bank reserves must come from existing opening base');
        foreach(['minimum_capital_bps','minimum_liquidity_bps'] as $k)
            if(!is_int($v[$k])||$v[$k]<500||$v[$k]>10000)throw new \DomainException('Invalid illustrative prudential ratio');
    }
    public static function validateCrisisTerms(array $v,string $day,array $eu):void
    {
        self::fields($v,['effective_on','facility_pool_franc_minor','guarantee_pool_franc_minor','collateral_haircut_bps','instrument_sha']);
        self::date($v['effective_on'],$day);self::sha($v['instrument_sha']);
        foreach(['facility_pool_franc_minor','guarantee_pool_franc_minor'] as $k)self::amount($v[$k]);
        if($v['effective_on']!==$eu['franc_bank_terms']['effective_on']
            ||$v['facility_pool_franc_minor']+$v['guarantee_pool_franc_minor']>$eu['franc_rule']['opening_base_franc_minor']-$eu['franc_bank_terms']['opening_reserve_allocation_franc_minor']
            ||!is_int($v['collateral_haircut_bps'])||$v['collateral_haircut_bps']<0||$v['collateral_haircut_bps']>10000)
            throw new \DomainException('Crisis pools must be preallocated from opening base');
    }
    public static function validateOrder(array $v,string $day,array $eu):void
    {
        self::fields($v,['effective_on','kind','amount_franc_minor','instrument_sha']);
        self::date($v['effective_on'],$day);self::sha($v['instrument_sha']);self::amount($v['amount_franc_minor']);
        $kinds=['credit','principal_repayment','interest_payment','certified_writeoff'];
        if(isset($eu['franc_bank_crisis_version'])&&$eu['franc_bank_crisis_terms']!==null)$kinds=[...$kinds,...self::CRISIS_KINDS];
        if($v['amount_franc_minor']===0||!in_array($v['kind'],$kinds,true)
            ||$v['effective_on']<$eu['franc_bank_terms']['effective_on']||isset($eu['franc_bank_orders'][$v['effective_on']]))
            throw new \DomainException('Invalid bank order');
    }
    private static function ratio(int $a,int $b):?int
    {return $b===0?null:self::scale(max(0,$a),10000,$b);}
    public static function project(array $eu,array $funding):array
    {
        if(($eu['franc_bank_version']??null)!==self::VERSION)throw new \DomainException('Unpinned bank model');
        $terms=$eu['franc_bank_terms'];$start=$terms!==null&&$funding['effective_from']!==null?max($terms['effective_on'],$funding['effective_from']):null;
        $active=$start!==null&&$eu['day']>=$start;$rows=[];$loan=0;$deposit=0;$gross=0;$repaid=0;$written=0;$interest=0;
        $seed=$terms['opening_reserve_allocation_franc_minor']??0;$equity=$seed;
        $crisis=$eu['franc_bank_crisis_terms']??null;$facility=$crisis['facility_pool_franc_minor']??0;$guarantee=$crisis['guarantee_pool_franc_minor']??0;
        $facilityDebt=0;$guaranteeClaim=0;$cash=0;$withdrawn=0;$redeposited=0;$guaranteed=0;
        foreach($funding['days'] as $row){
            $day=$row['day'];if(!$active||$day<$start)continue;
            $p=$row['funding'];
            $publicReserve=$p['commercial_banks']['reserve_asset_franc_minor'];
            $publicDeposit=$p['commercial_banks']['beneficiary_deposit_liability_franc_minor'];
            if($publicReserve!==$publicDeposit)throw new \DomainException('Invalid public funding counterpart');
            $reserve=$seed+$publicReserve+$facilityDebt-$withdrawn+$redeposited;
            $order=$eu['franc_bank_orders'][$day]??null;$ask=$order['amount_franc_minor']??0;$kind=$order['kind']??null;
            $capMax=self::scale(max(0,$equity),10000,$terms['minimum_capital_bps']);
            $liqMax=self::scale($reserve,10000,$terms['minimum_liquidity_bps']);
            $capacity=max(0,min($capMax-$loan,$liqMax-$publicDeposit-$deposit));
            if($equity<=0||$row['excess_base_franc_minor']>0||$facilityDebt>0||$guaranteeClaim>0)$capacity=0;
            $paid=0;$new=0;$principal=0;$coupon=0;$loss=0;$withdrawal=0;$redeposit=0;$support=0;$supportRepay=0;$payout=0;$eligibleWithdrawal=0;
            switch($kind){
                case 'credit':
                    $paid=$new=min($ask,$capacity);
                    $loan+=$new;$deposit+=$new;$gross+=$new;break;
                case 'principal_repayment':
                    $paid=$principal=min($ask,$loan,$deposit);
                    $loan-=$principal;$deposit-=$principal;$repaid+=$principal;break;
                case 'interest_payment':
                    // Paid from existing deposits; income is retained in equity.
                    $paid=$coupon=min($ask,$deposit);
                    $deposit-=$coupon;$equity+=$coupon;$interest+=$coupon;break;
                case 'certified_writeoff':
                    // Debt relief only with separate evidence, never a deposit seizure.
                    $paid=$loss=min($ask,$loan);
                    $loan-=$loss;$equity-=$loss;$written+=$loss;break;
                case 'cash_withdrawal':
                    // Only private-credit deposits are in this closed cash pilot.
                    $eligibleWithdrawal=min($ask,$deposit);
                    $paid=$withdrawal=min($eligibleWithdrawal,$reserve);
                    $reserve-=$withdrawal;$deposit-=$withdrawal;$cash+=$withdrawal;$withdrawn+=$withdrawal;break;
                case 'cash_redeposit':
                    $paid=$redeposit=min($ask,$cash);
                    $reserve+=$redeposit;$deposit+=$redeposit;$cash-=$redeposit;$redeposited+=$redeposit;break;
                case 'liquidity_support':
                    // Transfer existing base from a prefunded facility. No
                    // automatic monetisation or lending to an insolvent bank.
                    $collateral=self::scale($loan,10000-$crisis['collateral_haircut_bps'],10000);
                    $paid=$support=$equity>0?min($ask,$facility,max(0,$collateral-$facilityDebt)):0;
                    $facility-=$support;$facilityDebt+=$support;$reserve+=$support;break;
                case 'liquidity_repayment':
                    $paid=$supportRepay=min($ask,$facilityDebt,$reserve);
                    $facility+=$supportRepay;$facilityDebt-=$supportRepay;$reserve-=$supportRepay;break;
                case 'guarantee_payout':
                    // Aggregate prefunded guarantee, not a per-person statutory
                    // coverage claim. Depositor claim transfers to the fund.
                    $paid=$payout=$equity<0?min($ask,$guarantee,$deposit):0;
                    $guarantee-=$payout;$guaranteeClaim+=$payout;$deposit-=$payout;$cash+=$payout;$guaranteed+=$payout;break;
            }
            $assets=$reserve+$loan;$deposits=$publicDeposit+$deposit;
            if($assets!==$deposits+$equity+$facilityDebt+$guaranteeClaim||$loan!==$gross-$repaid-$written||$deposit!==$gross-$repaid-$interest-$withdrawn+$redeposited-$guaranteed
                ||$equity!==$seed+$interest-$written||min($loan,$deposit,$reserve)<0||$new>$capacity)
                throw new \DomainException('Unbalanced commercial bank');
            if($crisis!==null&&($cash!==$withdrawn-$redeposited+$guaranteed
                ||$facility+$facilityDebt!==$crisis['facility_pool_franc_minor']
                ||$guarantee+$guaranteeClaim!==$crisis['guarantee_pool_franc_minor']
                ||$reserve+$cash+$facility+$guarantee!==$seed+$publicReserve+$crisis['facility_pool_franc_minor']+$crisis['guarantee_pool_franc_minor']
                ||min($cash,$facility,$guarantee,$facilityDebt,$guaranteeClaim)<0))
                throw new \DomainException('Crisis funding does not conserve base');
            $capital=self::ratio($equity,$loan);$liquidity=self::ratio($reserve,$deposits);
            $capitalOk=$equity>0&&($capital===null||$capital>=$terms['minimum_capital_bps']);
            $liquidityOk=$liquidity===null||$liquidity>=$terms['minimum_liquidity_bps'];
            $rows[]=['day'=>$day,'order_kind'=>$kind,'instrument_sha'=>$order['instrument_sha']??null,
                'requested_franc_minor'=>$ask,'settled_franc_minor'=>$paid,'unsettled_franc_minor'=>$ask-$paid,
                'opening_credit_capacity_franc_minor'=>$capacity,'new_credit_franc_minor'=>$new,'principal_repaid_franc_minor'=>$principal,
                'interest_paid_franc_minor'=>$coupon,'loan_written_off_franc_minor'=>$loss,
                'bank_assets_franc_minor'=>$assets,'bank_reserves_franc_minor'=>$reserve,'bank_loan_assets_franc_minor'=>$loan,
                'public_payment_deposits_franc_minor'=>$publicDeposit,'private_credit_deposits_franc_minor'=>$deposit,
                'bank_deposit_liabilities_franc_minor'=>$deposits,'bank_equity_franc_minor'=>$equity,
                'borrower_loan_liabilities_franc_minor'=>$loan,'borrower_deposit_assets_franc_minor'=>$deposit,
                'opening_equity_funded_from_existing_reserves_franc_minor'=>$seed,
                'cumulative_credit_created_franc_minor'=>$gross,'cumulative_principal_repaid_franc_minor'=>$repaid,
                'cumulative_interest_paid_franc_minor'=>$interest,'cumulative_writeoffs_franc_minor'=>$written,
                'capital_to_loan_bps'=>$capital,'reserve_to_deposit_bps'=>$liquidity,
                'capital_requirement_met'=>$capitalOk,'liquidity_requirement_met'=>$liquidityOk,
                'insolvent'=>$equity<0,'credit_suspended_by_base_excess'=>$row['excess_base_franc_minor']>0,
                'monetary_base_franc_minor'=>$row['closing_base_franc_minor'],
                'commercial_credit_base_creation_franc_minor'=>0,'covered_bank_deposits_franc_minor'=>$deposits,
                'bank_balance_difference_franc_minor'=>$assets-$deposits-$equity-$facilityDebt-$guaranteeClaim,
                ...($crisis===null?[]:['crisis'=>['requested_eligible_withdrawal_franc_minor'=>$eligibleWithdrawal,
                    'cash_withdrawn_franc_minor'=>$withdrawal,'unserved_eligible_withdrawal_franc_minor'=>$eligibleWithdrawal-$withdrawal,
                    'cash_redeposited_franc_minor'=>$redeposit,'household_cash_franc_minor'=>$cash,
                    'facility_advance_franc_minor'=>$support,'facility_repayment_franc_minor'=>$supportRepay,
                    'facility_available_franc_minor'=>$facility,'bank_facility_liability_franc_minor'=>$facilityDebt,
                    'facility_claim_asset_franc_minor'=>$facilityDebt,
                    'guarantee_paid_franc_minor'=>$payout,'guarantee_available_franc_minor'=>$guarantee,
                    'bank_subrogated_guarantee_liability_franc_minor'=>$guaranteeClaim,'guarantee_claim_asset_franc_minor'=>$guaranteeClaim,
                    'base_money_created_by_crisis_franc_minor'=>0,'covered_deposits_plus_cash_franc_minor'=>$deposits+$cash,
                    'liquidity_shortfall'=>$eligibleWithdrawal>$withdrawal,'new_credit_suspended_for_support'=>$facilityDebt>0||$guaranteeClaim>0,
                    'public_deposit_withdrawals_modelled'=>false,'per_depositor_guarantee_modelled'=>false,
                    'automatic_resolution'=>false]])];
        }
        return ['version'=>self::VERSION,'classification'=>'fictional_consolidated_bank_pilot','status'=>$terms===null?'not_configured':($active?'active':'pending_effective_date'),
            'currency_name'=>'Franc','effective_from'=>$start,'terms'=>$terms,'latest'=>$rows===[]?null:$rows[array_key_last($rows)],
            'days'=>$rows,'opening_reserves_reallocated_not_issued'=>true,'public_deposits_included_once'=>true,
            'full_national_broad_money_franc_minor'=>null,'actual_eur_ledger_booked'=>false,'interbank_payments_modelled'=>false,
            'actual_regulatory_ratios'=>false,...(isset($eu['franc_bank_crisis_version'])?['crisis_version'=>self::CRISIS_VERSION,'crisis_terms'=>$crisis,
                'crisis_status'=>$crisis===null?'not_configured':($active?'active':'pending_effective_date'),
                'prefunded_opening_base_only'=>true,'unserved_withdrawals_not_automatically_carried'=>true]:[]),'not_a_forecast'=>true];
    }
}
