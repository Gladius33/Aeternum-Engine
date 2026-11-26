<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Committed dual accounting, not yet an FX settlement engine. Original
 * reference cash/debt calculations stay in EUR reference units; FRF cash
 * uses opening parity while retained EUR liabilities use current FX. */
final class FrancFiscalLedger
{
    public const VERSION='wp54r-frf-fiscal-ledger-v1';
    public const CONVERTIBLE=['legacy-state-other_french','legacy-state-french_credit_institutions',
        'legacy-state-french_insurers','legacy-state-french_funds','legacy-other-government-unknown',
        'legacy-state-nonresident','new-normal-domestic','new-stress-domestic','new-normal-external','new-stress-external','new-support-external'];
    private static function scale(int $n,int $r,int $d):int
    {return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    public static function validateTerms(array $v,string $day,array $eu):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['converted_opening_cohorts','effective_on','instrument_sha']
            ||!is_string($v['effective_on'])||($dt=\DateTimeImmutable::createFromFormat('!Y-m-d',$v['effective_on']))===false
            ||$dt->format('Y-m-d')!==$v['effective_on']||$v['effective_on']<=$day
            ||$v['effective_on']<$eu['currency_terms']['effective_on']
            ||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha'])
            ||!is_array($v['converted_opening_cohorts'])||!array_is_list($v['converted_opening_cohorts'])
            ||count($v['converted_opening_cohorts'])>count(self::CONVERTIBLE))
            throw new \DomainException('Invalid fiscal conversion scenario');
        $seen=[];
        foreach($v['converted_opening_cohorts'] as $id){
            if(!is_string($id)||!in_array($id,self::CONVERTIBLE,true)||isset($seen[$id]))throw new \DomainException('Invalid certified debt cohort');
            $seen[$id]=true;
        }
    }
    public static function step(array $row,?array $previous,array $debtModel,array $terms,int $ratio,int $mark):array
    {
        if($ratio<1||$ratio>10||$mark<5000||$mark>15000)throw new \DomainException('Invalid fiscal parity or mark');
        $t=$row['treasury'];$prior=$previous['frf_fiscal']??null;
        $opening=$previous['treasury']['creditors']??($debtModel['opening_holders']+array_fill_keys($debtModel['new_holder_keys'],0)+(!empty($debtModel['crisis_enabled'])?['new-support-external'=>0]:[]));
        if(array_keys($opening)!==array_keys($t['creditors'])||array_sum($opening)!==$t['opening_debt'])
            throw new \DomainException('Fiscal debt opening mismatch');
        if($prior!==null&&($prior['parity_units_per_eur']!==$ratio||$prior['instrument_sha']!==$terms['instrument_sha']))
            throw new \DomainException('Fiscal conversion terms changed');
        $openEur=0;$openFrf=0;$closeEur=0;$closeFrf=0;$cohorts=[];
        foreach($opening as $id=>$amount){
            $old=$prior['creditors'][$id]??null;
            $eur=$old['closing_eur_principal_minor']??(in_array($id,$terms['converted_opening_cohorts'],true)?0:$amount);
            $frf=$old['closing_frf_principal_minor']??(($amount-$eur)*$ratio);
            if($frf%$ratio!==0||$eur+intdiv($frf,$ratio)!==$amount)throw new \DomainException('Fiscal debt cohort opening mismatch');
            $openEur+=$eur;$openFrf+=$frf;
            $delta=$t['creditors'][$id]-$amount;
            $ce=$eur;$cf=$frf;
            if($delta>=0){
                // New domestic market issues are FRF; external/support
                // issues remain EUR. No change to the reference debt stock.
                if(in_array($id,['new-normal-domestic','new-stress-domestic'],true))$cf+=$delta*$ratio;
                else $ce+=$delta;
            }else{
                // The current reference ledger amortizes legacy cohorts only.
                // A mixed cohort needs an explicit payment allocation first.
                if($eur>0&&$frf>0)throw new \DomainException('Mixed cohort reduction needs payment allocation');
                if($eur>0)$ce+=$delta;else $cf+=$delta*$ratio;
            }
            if(min($ce,$cf)<0||$ce+intdiv($cf,$ratio)!==$t['creditors'][$id])
                throw new \DomainException('Fiscal debt principal conservation failure');
            $closeEur+=$ce;$closeFrf+=$cf;
            $cohorts[$id]=['opening_eur_principal_minor'=>$eur,'opening_frf_principal_minor'=>$frf,
                'closing_eur_principal_minor'=>$ce,'closing_frf_principal_minor'=>$cf,
                'closing_frf_valuation_minor'=>$cf+self::scale($ce,$ratio*10000,$mark)];
        }
        $priorMark=$prior['value_index_bps']??10000;
        $openValue=$openFrf+self::scale($openEur,$ratio*10000,$priorMark);
        $markedOpen=$openFrf+self::scale($openEur,$ratio*10000,$mark);
        $closeValue=$closeFrf+self::scale($closeEur,$ratio*10000,$mark);
        $cashOpen=$t['opening_cash']*$ratio;$revenue=$row['revenue']*$ratio;$primary=$t['primary_paid']*$ratio;
        $interest=$t['interest']*$ratio;$principal=$t['principal_paid']*$ratio;$borrow=$t['new_borrowing']*$ratio;
        $equity=($t['equity_acquisition_minor']??0)*$ratio;$cashClose=$t['cash']*$ratio;
        if($cashOpen+$revenue+$borrow-$primary-$interest-$principal-$equity!==$cashClose
            ||($prior!==null&&$prior['cash']['closing_frf_minor']!==$cashOpen)
            ||$closeEur+intdiv($closeFrf,$ratio)!==$t['debt'])
            throw new \DomainException('FRF fiscal reconciliation failed');
        return ['version'=>self::VERSION,'currency'=>'FRF','instrument_sha'=>$terms['instrument_sha'],'parity_units_per_eur'=>$ratio,
            'value_index_bps'=>$mark,'source_reference_currency'=>'EUR','classification'=>'committed_dual_accounting_at_fixed_parity',
            'cash'=>['opening_frf_minor'=>$cashOpen,'revenue_frf_minor'=>$revenue,'primary_expense_due_frf_minor'=>$row['expense']*$ratio,
                'primary_paid_frf_minor'=>$primary,'interest_paid_reference_frf_minor'=>$interest,'principal_paid_reference_frf_minor'=>$principal,
                'new_borrowing_reference_frf_minor'=>$borrow,'equity_acquisition_frf_minor'=>$equity,'closing_frf_minor'=>$cashClose],
            'debt'=>['opening_eur_principal_minor'=>$openEur,'opening_frf_principal_minor'=>$openFrf,
                'closing_eur_principal_minor'=>$closeEur,'closing_frf_principal_minor'=>$closeFrf,
                'opening_frf_valuation_minor'=>$openValue,'closing_frf_valuation_minor'=>$closeValue,
                'fx_revaluation_frf_minor'=>$markedOpen-$openValue,'transaction_stock_delta_frf_minor'=>$closeValue-$markedOpen,
                'total_stock_delta_frf_minor'=>$closeValue-$openValue,'reference_debt_eur_minor'=>$t['debt'],
                'cohort_valuation_rounding_frf_minor'=>$closeValue-array_sum(array_column($cohorts,'closing_frf_valuation_minor'))],
            'creditors'=>$cohorts,'cash_settlement_at_market_fx_modelled'=>false,'interest_currency_allocation_modelled'=>false,
            'central_bank_advances_integrated'=>false,'separate_contract_book_added_to_debt'=>false,'national_reference_ledger_changed'=>false];
    }
}
