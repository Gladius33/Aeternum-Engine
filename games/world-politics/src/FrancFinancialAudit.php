<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeSectors.php';

/** Independent report-level checks on fully integrated daily records.
 * This is an accounting acceptance check, not economic calibration. */
final class FrancFinancialAudit
{
    public const VERSION='wp54r-frf-financial-audit-v1';
    private static function check(bool $ok,string $reason):void
    {if(!$ok)throw new \DomainException('Native financial audit: '.$reason);}
    public static function verify(array $days):array
    {
        $last=null;$count=0;$checks=0;$totals=['FRF'=>0,'EUR'=>0];
        foreach($days as $r){
            if(!isset($r['financial_version']))continue;
            $a=$r['opening_accounts'];$z=$r['closing_state']['accounts'];$b=$r['budget'];$s=$r['debt_settlement'];$f=$r['fx_intervention'];$k=$r['capital_settlement'];$bank=$r['bank_balance'];
            self::check($z['official_eur_reserves_minor']>=$z['minimum_official_eur_minor'],'reserve floor');
            $direction=match($f['kind']){'sell_eur'=>-1,'buy_eur'=>1,null=>0,default=>throw new \DomainException('Unknown FX direction')};
            $extraEur=0;$currentEur=$a['official_eur_reserves_minor']+$direction*$f['eur']-$s['reserve_eur_used_minor'];
            foreach($r['external_finance']['facilities']??[] as $p){$m=$p['market_trade']??null;if($m===null)continue;
                $sign=match($m['kind']){'buy'=>1,'sell'=>-1,'mark'=>0};
                self::check($m['opening_official_eur_minor']===$currentEur&&$m['closing_official_eur_minor']===$currentEur-$sign*$m['eur_traded_minor']&&$m['closing_official_eur_minor']>=$z['minimum_official_eur_minor'],'market official EUR flow');
                self::check($m['opening_foreign_reserve_minor']===$p['opening_reserve_minor']&&$m['closing_foreign_reserve_minor']-$m['opening_foreign_reserve_minor']===$sign*$m['foreign_traded_minor']&&$m['closing_foreign_reserve_minor']+$m['closing_counterparty_foreign_minor']===$m['opening_foreign_reserve_minor']+$m['opening_counterparty_foreign_minor']&&$m['closing_official_eur_minor']+$m['closing_counterparty_eur_minor']===$m['opening_official_eur_minor']+$m['opening_counterparty_eur_minor'],'market counterpart conservation');
                $extraEur-=$sign*$m['eur_traded_minor'];$currentEur=$m['closing_official_eur_minor'];$checks+=2;
            }
            self::check($z['official_eur_reserves_minor']===$a['official_eur_reserves_minor']+$direction*$f['eur']-$s['reserve_eur_used_minor']+$extraEur,'reserve flows');
            self::check($z['official_franc_pool_minor']===$a['official_franc_pool_minor']-$direction*$f['frf']+$s['treasury_frf_paid_for_eur_minor'],'official FRF flows');
            self::check($z['treasury_eur_minor']===$a['treasury_eur_minor']+$r['issued_principal']['EUR']-$s['creditor_receipts_by_currency_minor']['EUR']+$s['reserve_eur_used_minor'],'Treasury EUR flows');
            self::check($z['treasury_frf_minor']===$a['treasury_frf_minor']+$b['ordinary_revenue_frf_minor']+$r['issued_principal']['FRF']-$b['ordinary_primary_paid_frf_minor']-$s['creditor_receipts_by_currency_minor']['FRF']-$s['treasury_frf_paid_for_eur_minor']-$k['industrial_paid_frf_minor']-$k['equity_paid_frf_minor'],'Treasury FRF flows');
            self::check($b['advance_received_frf_minor']===$b['advance_funded_spending_frf_minor']&&$b['levy_received_frf_minor']===$b['advance_repaid_frf_minor']&&$b['closing_advance_liability_frf_minor']===$b['opening_advance_liability_frf_minor']+$b['advance_received_frf_minor']-$b['advance_repaid_frf_minor'],'advance counterpart');
            self::check($k['opening_discretionary_cash_frf_minor']-$k['industrial_paid_frf_minor']-$k['equity_paid_frf_minor']===$k['closing_discretionary_cash_frf_minor']&&$k['closing_discretionary_cash_frf_minor']===$z['treasury_frf_minor'],'capital cash');
            self::check($bank['assets_frf_minor']===$bank['unallocated_opening_funding_liability_frf_minor']+$bank['equity_frf_minor'],'bank asset balance');
            if(isset($bank['combined_modelled_slices'])){
                $c=$bank['combined_modelled_slices'];
                self::check($c['assets_frf_minor']===$c['deposit_liabilities_frf_minor']+$c['unallocated_funding_liability_frf_minor']+$c['other_liabilities_frf_minor']+$c['equity_frf_minor'],'combined bank balance');
                $checks++;
            }
            FrancNativeSectors::checkCalendar($r['bond_maturities']['closing'],$r['closing_state']['claims']);
            foreach(['FRF','EUR'] as $cur){
                self::check($r['closing_principal'][$cur]===$r['opening_principal'][$cur]+$r['issued_principal'][$cur]-$r['paid_principal'][$cur]-$r['written_off_principal'][$cur],'principal stock '.$cur);
                $totals[$cur]+=$s['creditor_receipts_by_currency_minor'][$cur];
            }
            if(isset($r['external_finance'])){
                $x=$r['external_finance'];$sum=0;$opening=0;$delta=0;$fx=0;
                foreach($x['facilities'] as $cur=>$p){
                    self::check($p['currency']===$cur&&$p['principal_outstanding_minor']===$p['opening_principal_minor']+$p['issued_minor']-$p['principal_paid_minor'],'foreign principal');
                    self::check($p['reserve_minor']===$p['opening_reserve_minor']+($p['net_market_reserve_flow_minor']??0)-$p['principal_paid_minor']-$p['interest_paid_minor']&&$p['reserve_minor']>=$p['terms']['minimum_reserve_minor'],'foreign reserves');
                    self::check($p['issued_minor']===$p['earmarked_external_purchase_minor']&&$p['domestic_money_created_minor']===0,'foreign issuance counterpart');
                    if(isset($last['external_finance']['facilities'][$cur])){ $q=$last['external_finance']['facilities'][$cur];self::check($p['opening_principal_minor']===$q['principal_outstanding_minor']&&$p['opening_reserve_minor']===$q['reserve_minor'],'foreign continuity');$checks++;}
                    $sum+=$p['closing_debt_frf_minor'];$opening+=$p['opening_debt_frf_minor'];$delta+=$p['transaction_delta_frf_minor'];$fx+=$p['fx_revaluation_frf_minor'];$checks+=3;
                }
                self::check($sum===$x['closing_debt_frf_minor']&&$opening===$x['opening_debt_frf_minor']&&$sum-$opening===$fx+$delta&&$x['consolidated_gross_debt_frf_minor']===$r['valuation']['closing_gross_debt_frf_minor']+$sum,'external consolidation');$checks++;
            }
            if(isset($r['property_settlement'])){ $p=$r['property_settlement'];self::check($p['compensation_paid_frf_minor']<=$k['equity_paid_frf_minor']&&$p['title_transferred']===($p['status']==='settled')&&($p['title_transferred']?($p['compensation_paid_frf_minor']>0):($p['compensation_paid_frf_minor']===0)),'property consideration');$checks++;}
            $v=$r['valuation'];self::check($v['closing_gross_debt_frf_minor']-$v['opening_gross_debt_frf_minor']===$v['fx_revaluation_frf_minor']+$v['transaction_delta_frf_minor'],'valuation stock');
            if($last!==null){
                self::check($r['day']===(new \DateTimeImmutable($last['day']))->modify('+1 day')->format('Y-m-d')&&$a===$last['closing_state']['accounts']&&$r['opening_principal']===$last['closing_principal'],'daily continuity');
                self::check($r['bond_maturities']['opening']===$last['bond_maturities']['closing'],'maturity continuity');
                self::check($k['cumulative_industrial_paid_frf_minor']===$last['capital_settlement']['cumulative_industrial_paid_frf_minor']+$k['industrial_paid_frf_minor']&&$k['cumulative_equity_acquired_at_cost_frf_minor']===$last['capital_settlement']['cumulative_equity_acquired_at_cost_frf_minor']+$k['equity_paid_frf_minor'],'capital cumulative continuity');
                $checks+=3;
            }
            $checks+=12;$count++;$last=$r;
        }
        return ['version'=>self::VERSION,'status'=>$count>0?'accounting_checks_passed':'not_applicable','audited_days'=>$count,'checks_executed'=>$checks,'creditor_receipts_by_currency_minor'=>$totals,'empirical_calibration_validated'=>false,'full_national_balance_sheet_validated'=>false];
    }
}
