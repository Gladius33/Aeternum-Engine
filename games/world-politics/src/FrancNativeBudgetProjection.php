<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudget.php';

/** Replay committed EUR-reference policy flows in a distinct native treasury.
 * New issue amounts remain pinned to reference financing decisions; they are
 * not automatically increased to cover native liquidity or FX shortfalls. */
final class FrancNativeBudgetProjection
{
    private static function key(string $id,string $cur):string{return $id.'-'.$cur;}
    private static function opening(array $f,?array $prior,array $fx,array $money,int $ratio,int $mark):array
    {
        $claims=[];$p=$prior['treasury']??[];
        foreach($f['creditors'] as $id=>$c){
            $arrears=$p['principal_arrears_by_holder'][$id]??0;
            $frf=$c['opening_frf_principal_minor'];$eur=$c['opening_eur_principal_minor'];
            if($arrears>0&&$frf>0&&$eur>0)throw new \DomainException('Unallocated mixed opening arrears');
            foreach(['FRF'=>$frf,'EUR'=>$eur] as $cur=>$v)if($v>0){$due=$cur==='FRF'?$arrears*$ratio:$arrears;$claims[self::key($id,$cur)]=['currency'=>$cur,'principal_outstanding_minor'=>$v,'principal_due_minor'=>$due,'interest_due_minor'=>0];}
            if($arrears>0&&$frf===0&&$eur===0)throw new \DomainException('Missing opening debt for arrears');
        }
        // Aggregate pre-transition interest has no contractual cohort detail.
        // Retain it in original EUR instead of silently redenominating it.
        if(($p['interest_arrears']??0)>0)$claims['opening-interest-EUR']=['currency'=>'EUR','principal_outstanding_minor'=>0,'principal_due_minor'=>0,'interest_due_minor'=>$p['interest_arrears']];
        $fund=$money['funding'];$advance=$fund['settled_public_spending_franc_minor'];$repay=$fund['settled_levy_and_repayment_franc_minor'];
        return ['accounts'=>['treasury_frf_minor'=>$f['cash']['opening_frf_minor'],'treasury_eur_minor'=>0,'official_eur_reserves_minor'=>$fx['official_eur_reserves_minor'],'official_franc_pool_minor'=>$fx['official_franc_pool_minor'],'minimum_official_eur_minor'=>$fx['official_eur_reserve_floor_minor']],
            'claims'=>$claims,'counter_eur'=>$fx['counterparty_eur_assets_minor'],'counter_frf'=>$fx['counterparty_franc_pool_minor'],'primary_arrears'=>($p['primary_arrears']??0)*$ratio,
            'advance_liability'=>$fund['treasury']['advance_liability_franc_minor']-$advance+$repay,'mark'=>$mark];
    }
    private static function flow(array $r,array $state,array $model,array $money,?array $order,int $ratio):array
    {
        $day=$r['day'];$t=$r['treasury'];$paused=$t['crisis']['moratorium']??false;$action=$t['crisis']['action']??null;$coupons=[];$maturities=[];$haircuts=[];$issues=[];
        $month=(int)substr($day,0,4)*12+(int)substr($day,5,2)-(2026*12+12);$n=$model['maturity_month_count'];
        foreach($state['claims'] as $id=>$claim){
            $base=substr($id,0,-4);$cur=$claim['currency'];$principal=$claim['principal_outstanding_minor'];
            $cut=$action==='request-restructuring'&&!$paused?FrancNativeBudget::scale($claim['principal_due_minor'],$model['restructuring_overdue_reduction_bps']??0,10000):0;
            if($cut>0)$haircuts[$id]=$cut;
            if($principal===0)continue;
            $rate=$base==='new-support-external'?($model['support_coupon_bps']??0):(str_starts_with($base,'legacy-')?$model['initial_coupon_bps']:(str_starts_with($base,'new-stress-')?$model['stress_new_coupon_bps']:$model['normal_new_coupon_bps']));
            $coupons[$id]=FrancNativeBudget::scale($principal-$cut,$rate,3650000);
            if(substr($day,8,2)==='01'&&$month>=0&&$month<$n&&isset($model['opening_holders'][$base])){
                $v=$model['opening_holders'][$base];$due=intdiv($v,$n)+($month<$v%$n?1:0);
                $other=self::key($base,$cur==='EUR'?'FRF':'EUR');if(($state['claims'][$other]['principal_outstanding_minor']??0)>0)throw new \DomainException('Mixed scheduled legacy principal');
                $maturities[$id]=$due*($cur==='FRF'?$ratio:1);
            }
        }
        $support=$t['crisis']['support_disbursed']??0;$market=$t['new_borrowing']-$support;
        if($market<0||$support<0)throw new \DomainException('Invalid reference borrowing');
        $domestic=intdiv($market,2);$external=$market-$domestic;$prefix=$t['market_stress']?'new-stress-':'new-normal-';
        foreach([[$prefix.'domestic','FRF',$domestic*$ratio],[$prefix.'external','EUR',$external],['new-support-external','EUR',$support]] as [$id,$cur,$v])if($v>0)$issues[self::key($id,$cur)]=['currency'=>$cur,'amount'=>$v];
        $f=$money['funding'];
        if($f['central_bank']['treasury_advance_asset_franc_minor']!==$f['treasury']['advance_liability_franc_minor'])throw new \DomainException('Central-bank counterpart mismatch');
        return ['revenue'=>$r['revenue']*$ratio,'primary_expense'=>$r['expense']*$ratio,'advance'=>$f['settled_public_spending_franc_minor'],'repay'=>$f['settled_levy_and_repayment_franc_minor'],'closing_advance'=>$f['treasury']['advance_liability_franc_minor'],'equity'=>($t['equity_acquisition_minor']??0)*$ratio,'coupons'=>$coupons,'maturities'=>$maturities,'haircuts'=>$haircuts,'issues'=>$issues,'fx_order'=>$order];
    }
    public static function aggregate(array $rows,int $length):array
    {
        $out=[];$last=null;
        foreach($rows as $r){
            $day=$r['day'];$v=$r['valuation'];$b=$r['budget'];$key=substr($day,0,$length);
            if($last!==null&&($day!==(new \DateTimeImmutable($last['day']))->modify('+1 day')->format('Y-m-d')||$r['opening_accounts']!==$last['closing_state']['accounts']||$r['opening_principal']!==$last['closing_principal']||$v['opening_gross_debt_frf_minor']!==$last['valuation']['closing_gross_debt_frf_minor']||$b['opening_advance_liability_frf_minor']!==$last['budget']['closing_advance_liability_frf_minor']))throw new \DomainException('Native budget report discontinuity');
            $p=$out[$key]??['period'=>$key,'days'=>0,'opening_gross_debt_frf_minor'=>$v['opening_gross_debt_frf_minor'],'fx_revaluation_frf_minor'=>0,'transaction_delta_frf_minor'=>0,'opening_principal'=>$r['opening_principal'],'issued_principal'=>['FRF'=>0,'EUR'=>0],'paid_principal'=>['FRF'=>0,'EUR'=>0],'written_off_principal'=>['FRF'=>0,'EUR'=>0],'opening_accounts'=>$r['opening_accounts'],'opening_advance_liability_frf_minor'=>$b['opening_advance_liability_frf_minor'],'reserve_eur_used_minor'=>0,'treasury_frf_paid_for_eur_minor'=>0,'interest_paid'=>['FRF'=>0,'EUR'=>0]];
            $p['days']++;$p['closing_gross_debt_frf_minor']=$v['closing_gross_debt_frf_minor'];$p['fx_revaluation_frf_minor']+=$v['fx_revaluation_frf_minor'];$p['transaction_delta_frf_minor']+=$v['transaction_delta_frf_minor'];$p['closing_principal']=$r['closing_principal'];$p['closing_accounts']=$r['closing_state']['accounts'];$p['closing_arrears']=$r['arrears'];$p['closing_primary_arrears_frf_minor']=$b['primary_arrears_frf_minor'];$p['closing_advance_liability_frf_minor']=$b['closing_advance_liability_frf_minor'];
            foreach(['ordinary_revenue_frf_minor','ordinary_primary_paid_frf_minor','equity_paid_frf_minor','advance_received_frf_minor','advance_funded_spending_frf_minor','levy_received_frf_minor','advance_repaid_frf_minor'] as $k)$p[$k]=($p[$k]??0)+$b[$k];
            foreach(['reserve_eur_used_minor','treasury_frf_paid_for_eur_minor'] as $k)$p[$k]+=$r['debt_settlement'][$k];
            foreach(['FRF','EUR'] as $cur){foreach(['issued_principal','paid_principal','written_off_principal'] as $k)$p[$k][$cur]+=$r[$k][$cur];$p['interest_paid'][$cur]+=$r['debt_settlement']['creditor_receipts_by_currency_minor'][$cur]-$r['paid_principal'][$cur];if($p['closing_principal'][$cur]!==$p['opening_principal'][$cur]+$p['issued_principal'][$cur]-$p['paid_principal'][$cur]-$p['written_off_principal'][$cur])throw new \DomainException('Native period debt mismatch');}
            if($p['closing_gross_debt_frf_minor']-$p['opening_gross_debt_frf_minor']!==$p['fx_revaluation_frf_minor']+$p['transaction_delta_frf_minor']||$p['closing_advance_liability_frf_minor']!==$p['opening_advance_liability_frf_minor']+$p['advance_received_frf_minor']-$p['advance_repaid_frf_minor'])throw new \DomainException('Native period valuation mismatch');
            $out[$key]=$p;$last=$r;
        }return array_values($out);
    }
    public static function project(array $eu,array $dom,array $currency,array $money,array $fx):array
    {
        $terms=$eu['franc_native_budget_terms'];$start=$eu['membership']?null:max($terms['effective_on'],$eu['exit_on']);$days=[];$state=null;$prior=null;$previousFx=null;$fxDays=[];$monetaryDays=[];
        foreach($fx['days'] as $r)$fxDays[$r['day']]=$r;
        foreach($money['days'] as $r)$monetaryDays[$r['day']]=$r;
        $ratio=$currency['units_per_eur_at_parity']??1;$lastDay=null;
        foreach($dom['days'] as $r){
            $day=$r['day'];if($lastDay!==null&&$day!==(new \DateTimeImmutable($lastDay))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Missing native budget input day');$lastDay=$day;
            $x=$fxDays[$day]??null;$m=$monetaryDays[$day]??null;
            if($start===null||$day<$start){$prior=$r;if($x!==null)$previousFx=$x;continue;}
            $f=$r['frf_fiscal']??null;if($f===null||$x===null||$m===null)throw new \DomainException('Missing committed native budget prerequisites');
            $mark=$x['franc_per_eur_rate']['denominator'];if($f['value_index_bps']!==$mark||$x['franc_per_eur_rate']['numerator']!==$ratio*10000||$f['parity_units_per_eur']!==$ratio)throw new \DomainException('Native budget quote mismatch');
            if($state===null){
                $t=$eu['franc_fx_terms'];$openingFx=$previousFx??['official_eur_reserves_minor'=>$t['official_eur_minor'],'official_franc_pool_minor'=>$t['official_franc_pool_minor'],'official_eur_reserve_floor_minor'=>$t['minimum_official_eur_minor'],'counterparty_eur_assets_minor'=>$t['counterparty_eur_minor'],'counterparty_franc_pool_minor'=>$t['counterparty_franc_pool_minor']];
                $state=self::opening($f,$prior,$openingFx,$m,$ratio,$prior['frf_fiscal']['value_index_bps']??10000);
            }
            $flow=self::flow($r,$state,$dom['debt_model'],$m,$eu['franc_fx_orders'][$day]??null,$ratio);
            $result=FrancNativeBudget::step($state,$flow,$ratio,$mark,$terms['reserve_purchase_authorized'],$r['treasury']['crisis']['moratorium']??false);$state=$result['closing_state'];$days[]=['day'=>$day,...$result];$prior=$r;
        }
        if($start!==null&&$dom['day']>=$start&&($days===[]||$days[0]['day']!==$start||$days[array_key_last($days)]['day']!==$dom['day']))throw new \DomainException('Incomplete native budget history');
        return ['version'=>FrancNativeBudget::VERSION,'status'=>$days===[]?'pending_effective_date':'active','currency'=>'FRF','effective_from'=>$start,'classification'=>'counterfactual_native_budget_from_committed_reference_flows','instrument_sha'=>$terms['instrument_sha'],'latest'=>$days===[]?null:$days[array_key_last($days)],'days'=>$days,'months'=>self::aggregate($days,7),'years'=>self::aggregate($days,4),'reference_financing_policy_retained'=>true,'shared_reserves_within_projection'=>true,'national_reference_ledger_changed'=>false,'other_currencies_modelled'=>false,'macro_feedback_from_native_arrears_modelled'=>false];
    }
}
