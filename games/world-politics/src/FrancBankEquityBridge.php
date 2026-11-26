<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudget.php';

/** Equity bridge independent of the recorded balancing residual.
 * Scope: identified sovereign bank slice; the commercial pilot is
 * separately balanced. No national balance sheet is inferred. */
final class FrancBankEquityBridge
{
    public const VERSION='wp54r-bank-equity-bridge-v1';
    private static function value(array $x,int $ratio,int $mark):int
    {return $x['FRF']+FrancNativeBudget::scale($x['EUR'],$ratio*10000,$mark);}
    public static function project(array $days):array
    {
        $last=null;$out=[];$totals=['fx_revaluation_frf_minor'=>0,'coupon_income_frf_minor'=>0,'principal_writeoff_frf_minor'=>0,'provision_change_frf_minor'=>0,'rounding_residual_frf_minor'=>0];$months=[];
        foreach($days as $r){
            if(!isset($r['bank_balance']))continue;
            $b=$r['bank_balance'];$ratio=$r['ratio'];$mark=$r['closing_state']['mark'];$oldMark=$last['closing_state']['mark']??10000;
            $old=$last['bank_balance']??null;
            $principal=$b['sovereign_principal_by_currency_minor'];$cash=$b['retained_creditor_cash_by_currency_minor'];
            $openingPrincipal=[];$openingCash=[];
            foreach(['FRF','EUR'] as $cur){
                $openingPrincipal[$cur]=$principal[$cur]+$b['principal_received_by_currency_minor'][$cur]+$b['writeoff_by_currency_minor'][$cur];
                $openingCash[$cur]=$cash[$cur]-$b['principal_received_by_currency_minor'][$cur]-$b['interest_received_by_currency_minor'][$cur];
                if($openingCash[$cur]<0||($old!==null&&($old['sovereign_principal_by_currency_minor'][$cur]!==$openingPrincipal[$cur]||$old['retained_creditor_cash_by_currency_minor'][$cur]!==$openingCash[$cur])))throw new \DomainException('Bank bridge opening mismatch');
            }
            $oldAssets=self::value($openingPrincipal,$ratio,$oldMark)+self::value($openingCash,$ratio,$oldMark);
            $markedAssets=self::value($openingPrincipal,$ratio,$mark)+self::value($openingCash,$ratio,$mark);
            $oldProvision=$old['valuation_provision_frf_minor']??0;$newProvision=$b['valuation_provision_frf_minor'];
            $liability=$b['unallocated_opening_funding_liability_frf_minor'];
            if($old!==null&&$old['unallocated_opening_funding_liability_frf_minor']!==$liability)throw new \DomainException('Bank bridge liability changed');
            $openingEquity=$oldAssets-$oldProvision-$liability;
            if($openingEquity!==($old['equity_frf_minor']??$b['opening_capital_proxy_frf_minor']))throw new \DomainException('Bank bridge opening equity');
            $closing=self::value($principal,$ratio,$mark)+self::value($cash,$ratio,$mark)-$newProvision-$liability;
            if($closing!==$b['equity_frf_minor'])throw new \DomainException('Bank bridge closing equity');
            $fx=$markedAssets-$oldAssets;$coupon=self::value($b['interest_received_by_currency_minor'],$ratio,$mark);
            $writeoff=self::value($b['writeoff_by_currency_minor'],$ratio,$mark);$provision=$newProvision-$oldProvision;
            $rounding=$closing-$openingEquity-$fx-$coupon+$writeoff+$provision;
            if(abs($rounding)>4)throw new \DomainException('Bank equity movement not explained by transactions and rounding');
            $row=['day'=>$r['day'],'opening_equity_frf_minor'=>$openingEquity,'closing_equity_frf_minor'=>$closing,'fx_revaluation_frf_minor'=>$fx,'coupon_income_frf_minor'=>$coupon,'principal_writeoff_frf_minor'=>$writeoff,'provision_change_frf_minor'=>$provision,'rounding_residual_frf_minor'=>$rounding,'principal_receipt_is_income'=>false];
            foreach($totals as $k=>$v)$totals[$k]+=$row[$k];
            $key=substr($r['day'],0,7);$m=$months[$key]??['period'=>$key,'opening_equity_frf_minor'=>$openingEquity,...array_fill_keys(array_keys($totals),0)];
            foreach($totals as $k=>$v)$m[$k]+=$row[$k];$m['closing_equity_frf_minor']=$closing;$months[$key]=$m;
            $out[]=$row;$last=$r;
        }
        if($out!==[]){$first=$out[0];$final=$out[array_key_last($out)];if($final['closing_equity_frf_minor']-$first['opening_equity_frf_minor']!==$totals['fx_revaluation_frf_minor']+$totals['coupon_income_frf_minor']-$totals['principal_writeoff_frf_minor']-$totals['provision_change_frf_minor']+$totals['rounding_residual_frf_minor'])throw new \DomainException('Bank cumulative equity bridge');}
        return ['version'=>self::VERSION,'classification'=>'identified_sovereign_bank_slice_only','days'=>count($out),'latest'=>$out===[]?null:$out[array_key_last($out)],'totals'=>$totals,'months'=>array_values($months),'full_national_bank_balance_sheet'=>false];
    }
}
