<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudget.php';

/** Opt-in v2: aggregate post-transition bond vintages by maturity month.
 * Existing pre-transition new-issue cohorts lack vintage evidence and are
 * explicitly excluded. Overdue scheduled amounts remain in claim arrears. */
final class FrancNativeSectors
{
    public const VERSION='wp54r-frf-native-sectors-v1';
    public static function maturityDay(string $day,int $years):string
    {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        if($d===false||$d->format('Y-m-d')!==$day||$years<1||$years>50)throw new \DomainException('Invalid bond vintage');
        // First day of month following the anniversary month, including leap years.
        return $d->modify('first day of this month')->modify('+'.$years.' years')->modify('+1 month')->format('Y-m-d');
    }
    public static function schedule(array $calendar,array $claims,string $day,array $issues,int $years):array
    {
        $due=[];$opening=$calendar;
        foreach($calendar as $date=>$batch){
            if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-01$/D',$date)||$date<$day||!is_array($batch))throw new \DomainException('Overdue or invalid maturity bucket');
            foreach($batch as $id=>$amount)if(!isset($claims[$id])||!is_int($amount)||$amount<=0||$amount>10000000000000000)throw new \DomainException('Invalid scheduled principal');
            if($date===$day){$due=$batch;unset($calendar[$date]);}
        }
        $date=self::maturityDay($day,$years);
        foreach($issues as $id=>$issue){$v=$issue['amount'];if(!is_int($v)||$v<0||$v>10000000000000000)throw new \DomainException('Invalid scheduled issue');if($v>0)$calendar[$date][$id]=($calendar[$date][$id]??0)+$v;}
        ksort($calendar,SORT_STRING);foreach($calendar as &$batch)ksort($batch,SORT_STRING);unset($batch);ksort($due,SORT_STRING);
        return ['opening'=>$opening,'due'=>$due,'closing'=>$calendar,'new_issue_maturity_on'=>$issues===[]?null:$date];
    }
    public static function checkCalendar(array $calendar,array $claims):void
    {
        $sum=[];foreach($calendar as $batch)foreach($batch as $id=>$v)$sum[$id]=($sum[$id]??0)+$v;
        foreach($sum as $id=>$v)if(!isset($claims[$id])||$v>$claims[$id]['principal_outstanding_minor']-$claims[$id]['principal_due_minor'])throw new \DomainException('Future vintages exceed non-overdue principal');
    }
    /** Bank exposure is only the identified synthetic bank-holder cohort.
     * Not all domestic lenders are banks. Keep legacy bank assets separate
     * from the closed commercial loan/deposit pilot. */
    public static function banking(array $r,array $model,int $ratio,int $mark):array
    {
        $base='legacy-state-french_credit_institutions';$claims=$r['closing_state']['claims'];$value=0;$overdue=0;$receipts=['FRF'=>0,'EUR'=>0];$writeoff=0;
        foreach(['FRF','EUR'] as $cur){
            $id=$base.'-'.$cur;$c=$claims[$id]??null;$receipt=$r['debt_settlement']['creditor_receipts'][$id]??null;
            if($c!==null){$p=$c['principal_outstanding_minor'];$a=$c['principal_due_minor']+$c['interest_due_minor'];$value+=$cur==='FRF'?$p:FrancNativeBudget::scale($p,$ratio*10000,$mark);$overdue+=$cur==='FRF'?$a:FrancNativeBudget::scale($a,$ratio*10000,$mark);}
            if($receipt!==null)$receipts[$cur]=$receipt['principal_paid_minor']+$receipt['interest_paid_minor'];
        }
        $capital=intdiv(($model['opening_holders'][$base]??0)*$ratio,5);
        $paused=$r['debt_settlement']['moratorium'];$discount=$paused?2000:($overdue>0?1000:0);
        $loss=FrancNativeBudget::scale($value,$discount,10000);$equity=$capital-$loss;
        $credit=max(2500,min(10000,10000-2*$discount-($equity<=0&&$capital>0?2000:0)));
        return ['classification'=>'identified_synthetic_bank_cohort_sensitivity','bank_sovereign_claims_at_market_frf_minor'=>$value,'bank_overdue_claims_frf_minor'=>$overdue,'cash_receipts_by_currency_minor'=>$receipts,'valuation_discount_bps'=>$discount,'unrealized_loss_proxy_frf_minor'=>$loss,'opening_capital_proxy_frf_minor'=>$capital,'equity_proxy_frf_minor'=>$equity,'credit_availability_bps'=>$credit,'new_domestic_lender_sector_unallocated'=>true,'commercial_bank_balance_sheet_booked'=>false];
    }
    public static function credit(?array $prior):int
    {return min($prior['treasury']['banking']['credit_availability_bps']??10000,$prior['frf_committed_budget']['risk']['credit_availability_bps']??10000,$prior['frf_committed_budget']['banking']['credit_availability_bps']??10000);}
    public static function services(array $result,?array $prior,int $expense,int $ratio):array
    {
        $opening=$prior['frf_committed_budget']['closing_state']['primary_arrears']??(($prior['treasury']['primary_arrears']??0)*$ratio);
        $paid=max(0,min($expense*$ratio,$result['budget']['ordinary_primary_paid_frf_minor']-$opening));
        return ['current_primary_paid_frf_minor'=>$paid,'current_primary_due_frf_minor'=>$expense*$ratio,'reference_paid_minor'=>intdiv($paid,$ratio),'opening_primary_arrears_frf_minor'=>$opening,'allocation'=>'ordinary_current_spending_after_old_arrears'];
    }
}
