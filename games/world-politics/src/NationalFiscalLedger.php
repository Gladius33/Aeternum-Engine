<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Deterministic cash-basis accounting kernel. No authorization, calibration,
 * currency conversion, arrears settlement, accruals or implicit borrowing.
 * All inputs are explicit integer minor units in one currency/perimeter.
 * Never use incomplete documentary observations as an opening balance. */
final class NationalFiscalLedger
{
    public const VERSION='wp130-national-fiscal-cash-v1';
    private const MAX=100000000000000000;
    private const STOCK=['cash','gross_debt'];
    private const FLOW=['tax_received','other_received','primary_paid','interest_paid','principal_repaid','borrowing_received'];
    private static function validate(array $a,array $keys):void
    {
        $actual=array_keys($a);sort($actual);sort($keys);
        if($actual!==$keys)throw new \DomainException('Incomplete or unknown fiscal fields');
        foreach($a as $value)if(!is_int($value)||$value<0||$value>self::MAX)
            throw new \DomainException('Invalid fiscal minor-unit amount');
    }
    /** A cash-only subledger; stock-flow adjustments and revaluations require
     * separate explicit models. Scheduled but unpaid amounts are not paid flows. */
    public static function close(array $opening,array $flows):array
    {
        self::validate($opening,self::STOCK);self::validate($flows,self::FLOW);
        if($flows['principal_repaid']>$opening['gross_debt'])
            throw new \DomainException('Repayment exceeds opening gross debt');
        $revenue=$flows['tax_received']+$flows['other_received'];
        $primary=$revenue-$flows['primary_paid'];
        $overall=$primary-$flows['interest_paid'];
        $netBorrowing=$flows['borrowing_received']-$flows['principal_repaid'];
        $cash=$opening['cash']+$overall+$netBorrowing;
        $debt=$opening['gross_debt']+$netBorrowing;
        if($cash<0)throw new \DomainException('Unfunded cash outflow; no implicit borrowing');
        $closing=['cash'=>$cash,'gross_debt'=>$debt];self::validate($closing,self::STOCK);
        return ['version'=>self::VERSION,'classification'=>'conditional_cash_subledger_not_official_accounts',
            'opening'=>$opening,'executed_flows'=>$flows,'revenue_received'=>$revenue,
            'primary_cash_balance'=>$primary,'overall_cash_balance'=>$overall,
            'net_borrowing'=>$netBorrowing,'closing'=>$closing,
            'cash_change'=>$cash-$opening['cash'],'gross_debt_change'=>$debt-$opening['gross_debt'],
            'rankable'=>false];
    }

    /** Conditional settlement plan. Credit actually available is an explicit
     * scenario input, never inferred from debt. Debt service before primary
     * spending is a documented simulation convention, not a legal priority.
     * Unpaid amounts are disclosed, not extinguished or capitalized. */
    public static function plan(array $opening,array $due,array $financing):array
    {
        self::validate($opening,self::STOCK);
        self::validate($due,['tax_received','other_received','primary_due','interest_due','principal_due']);
        self::validate($financing,['committed_credit_available','gross_debt_ceiling']);
        if($due['principal_due']>$opening['gross_debt'])
            throw new \DomainException('Principal due exceeds opening debt');
        $available=$opening['cash']+$due['tax_received']+$due['other_received'];
        $need=max(0,$due['primary_due']+$due['interest_due']+$due['principal_due']-$available);
        // Conservative pre-payment headroom; no circular refinancing.
        $headroom=max(0,$financing['gross_debt_ceiling']-$opening['gross_debt']);
        $draw=min($need,$headroom,$financing['committed_credit_available']);
        $available+=$draw;
        $interest=min($available,$due['interest_due']);$available-=$interest;
        $principal=min($available,$due['principal_due']);$available-=$principal;
        $primary=min($available,$due['primary_due']);
        $settled=self::close($opening,['tax_received'=>$due['tax_received'],'other_received'=>$due['other_received'],
            'primary_paid'=>$primary,'interest_paid'=>$interest,'principal_repaid'=>$principal,'borrowing_received'=>$draw]);
        return ['version'=>'wp130-national-fiscal-settlement-v1','classification'=>'conditional_financing_plan_not_executed',
            'priority'=>'interest_then_principal_then_primary_scenario_convention','due'=>$due,'financing_assumptions'=>$financing,
            'funding_need_before_credit'=>$need,'credit_draw_scenario'=>$draw,
            'unpaid'=>['interest'=>$due['interest_due']-$interest,'principal'=>$due['principal_due']-$principal,'primary'=>$due['primary_due']-$primary],
            'cash_subledger'=>$settled,'rankable'=>false,'persisted'=>false];
    }
}
