<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancDebtCalibration.php';
require_once __DIR__.'/FrancObservedFinance.php';
require_once __DIR__.'/FrancCreditorGeography.php';

/** Coverage of the model, not a beneficial-owner registry. The observations
 * are read from the pinned source pack; selected figures have a separate crosscheck. */
final class FrancCreditorCoverage
{
    public const VERSION='wp54r-creditor-coverage-v1';
    public static function project(array $model,array $days):array
    {
        $claims=[];$receipts=['FRF'=>0,'EUR'=>0];$principal=['FRF'=>0,'EUR'=>0];$latest=null;
        foreach($days as $d){
            $s=$d['debt_settlement'];$latest=$d;
            foreach($s['creditor_receipts'] as $id=>$r){
                $cur=$r['currency'];$c=$claims[$id]??['currency'=>$cur,'principal_received_minor'=>0,'interest_received_minor'=>0];
                if($c['currency']!==$cur)throw new \DomainException('Creditor coverage currency changed');
                $c['principal_received_minor']+=$r['principal_paid_minor'];$c['interest_received_minor']+=$r['interest_paid_minor'];
                $c['principal_outstanding_minor']=$d['closing_state']['claims'][$id]['principal_outstanding_minor'];
                $c['principal_arrears_minor']=$d['closing_state']['claims'][$id]['principal_due_minor'];
                $c['interest_arrears_minor']=$d['closing_state']['claims'][$id]['interest_due_minor'];
                $c['classification']='synthetic_aggregate_creditor_cohort';$claims[$id]=$c;
                $receipts[$cur]+=$r['principal_paid_minor']+$r['interest_paid_minor'];
            }
        }
        foreach($claims as $c)$principal[$c['currency']]+=$c['principal_outstanding_minor'];
        if($latest!==null&&$principal!==$latest['closing_principal'])throw new \DomainException('Creditor coverage does not reconcile');
        ksort($claims,SORT_STRING);
        $observations=[];$quarters=[];$shares=[];
        foreach($model['source_pack']['observations']??[] as $o){
            if($o['kind']!=='observed')continue;
            if($o['metric']==='maastricht_gross_debt'&&$o['unit']==='EUR'&&preg_match('/^(\d{4})-Q([1-4])$/D',$o['period'],$m)){
                $date=(new \DateTimeImmutable(sprintf('%04d-%02d-01',(int)$m[1],(int)$m[2]*3)))->modify('last day of this month')->format('Y-m-d');
                $quarters[$date]=['day'=>$date,'debt_eur_minor'=>$o['value']*100,'period'=>$o['period'],'source_id'=>$o['source_id']];
            }
            if(str_starts_with($o['metric'],'state_negotiable_holder_share_')&&!str_contains($o['metric'],'rounded_chart')&&$o['unit']==='basis_points')$shares[$o['metric']]=['basis_points'=>$o['value'],'period'=>$o['period'],'source_id'=>$o['source_id'],'basis'=>'market_value_not_nominal_holder_book'];
        }
        ksort($quarters,SORT_STRING);$last=null;
        foreach($quarters as $q){
            if($last!==null){
                $n=(new \DateTimeImmutable($last['day']))->diff(new \DateTimeImmutable($q['day']))->days;
                $delta=$q['debt_eur_minor']-$last['debt_eur_minor'];
                $observations[]=['from'=>$last['day'],'to'=>$q['day'],'days'=>$n,'observed_stock_change_eur_minor'=>$delta,'mean_daily_change_eur_minor'=>intdiv($delta,$n),'not_daily_observations'=>true];
            }
            $last=$q;
        }
        return ['version'=>self::VERSION,'modelled_principal_by_currency_minor'=>$principal,'modelled_creditor_receipts_by_currency_minor'=>$receipts,'claim_cohorts'=>$claims,'geographic_proxy'=>FrancCreditorGeography::project($claims),
            'source_sha'=>$model['source_sha']??null,'source_classification'=>'pinned_staged_pack_with_partial_independent_crosscheck','observed_holder_share_anchors'=>$shares,
            'historical_stock_intervals'=>$observations,'observed_debt_holder_identity_coverage'=>null,'individual_beneficial_owners_known'=>false,
            'opening_holder_allocation_is_synthetic'=>true,'new_issues_holder_allocation_is_synthetic'=>true,
            'source_coverage_is_not_model_coverage'=>true,'observed_external_coverage'=>array_intersect_key(FrancObservedFinance::report(),array_fill_keys(['source_sha','sovereign_nonresident_foreign_currency_securities_market_value_eur_minor','sovereign_nonresident_securities_market_value_eur_minor','sovereign_nonresident_other_currency_share_bps','sovereign_country_rows_retrieved'],true)),'full_modelled_principal_reconciled'=>true,
            'calibration'=>['observed_anchors'=>['opening_debt','holder_market_value_shares','new_issuance_reference_rate'],'scenario_assumptions'=>['initial_coupon_bps','maturity_month_count','new_issue_maturity_years','normal_daily_base_capacity','stress_daily_base_capacity','normal_refinancing_bps','stress_refinancing_bps','support_limit','restructuring_overdue_reduction_bps'],'empirical_fit_performed'=>false,'historical_debt_stock_benchmark'=>FrancDebtCalibration::report($model)]];
    }
}
