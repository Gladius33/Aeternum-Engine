<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrancNativeBudgetProjection.php';
use Aeternum\WorldPolitics\{FrancNativeBudget as B,FrancNativeBudgetProjection as P};
function checkNB(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS native budget: $m\n";}
function rejectNB(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Invalid native budget accepted');}
$a=['treasury_frf_minor'=>300,'treasury_eur_minor'=>10,'official_eur_reserves_minor'=>200,'official_franc_pool_minor'=>500,'minimum_official_eur_minor'=>50];
$c=['foreign'=>['currency'=>'EUR','principal_outstanding_minor'=>500,'principal_due_minor'=>100,'interest_due_minor'=>20], 'domestic'=>['currency'=>'FRF','principal_outstanding_minor'=>500,'principal_due_minor'=>40,'interest_due_minor'=>10]];
$s=['accounts'=>$a,'claims'=>$c,'counter_eur'=>100,'counter_frf'=>200,'primary_arrears'=>0,'advance_liability'=>0,'mark'=>10000];
$flow=['revenue'=>50,'primary_expense'=>50,'advance'=>40,'repay'=>0,'closing_advance'=>40,'equity'=>20,'coupons'=>[],'maturities'=>[],'haircuts'=>[],'issues'=>[],'fx_order'=>['kind'=>'sell_eur','eur_minor'=>80]];
$r=B::step($s,$flow,2,8000,true,false);$z=$r['closing_state'];
checkNB($r['fx_intervention']['eur']===80&&$r['debt_settlement']['reserve_eur_used_minor']===70&&$z['accounts']['official_eur_reserves_minor']===50,'market and debt share reserve floor');
checkNB($r['arrears']['EUR']['principal']===40&&$r['paid_principal']===['FRF'=>40,'EUR'=>60]&&$z['accounts']['treasury_frf_minor']===75,'native liquidity and unpaid debt retained');
checkNB($r['budget']['advance_received_frf_minor']===40&&$r['budget']['advance_funded_spending_frf_minor']===40&&$z['advance_liability']===40&&$r['budget']['equity_paid_frf_minor']===0,'earmarked advance is not spendable twice; equity blocked');
checkNB($r['valuation']===['opening_gross_debt_frf_minor'=>1500,'closing_gross_debt_frf_minor'=>1600,'fx_revaluation_frf_minor'=>250,'transaction_delta_frf_minor'=>-150],'gross debt reconciles revaluation, payments and advance');
$f2=array_replace($flow,['revenue'=>0,'primary_expense'=>0,'advance'=>0,'repay'=>20,'closing_advance'=>20,'issues'=>['foreign'=>['currency'=>'EUR','amount'=>40]],'fx_order'=>null]);
$r2=B::step($z,$f2,2,8000,true,false);
checkNB($r2['debt_service_current']&&$r2['paid_principal']['EUR']===40&&$r2['debt_settlement']['reserve_eur_used_minor']===0&&$r2['budget']['equity_paid_frf_minor']===20,'foreign borrowing settles carried arrears; current equity allowed');
checkNB($r2['closing_state']['advance_liability']===20&&$r2['budget']['levy_received_frf_minor']===20&&$r2['budget']['advance_repaid_frf_minor']===20,'levy-funded repayment recorded without extra Treasury cash');
$month=P::aggregate([['day'=>'2026-12-01',...$r],['day'=>'2026-12-02',...$r2]],7)[0];
checkNB($month['days']===2&&$month['reserve_eur_used_minor']===70&&$month['interest_paid']===['FRF'=>10,'EUR'=>20]&&$month['closing_advance_liability_frf_minor']===20,'monthly native stock-flow aggregation');
checkNB(count(P::aggregate([['day'=>'2026-12-31',...$r],['day'=>'2027-01-01',...$r2]],4))===2,'year boundary separates buckets');
rejectNB(fn()=>P::aggregate([['day'=>'2026-12-01',...$r],['day'=>'2026-12-03',...$r2]],7));
rejectNB(fn()=>B::step($s,array_replace($flow,['closing_advance'=>41]),2,8000,true,false));
rejectNB(fn()=>B::step($s,array_replace($flow,['maturities'=>['foreign'=>401]]),2,8000,true,false));
$no=B::step($s,$flow,2,8000,false,false);checkNB($no['debt_settlement']['reserve_eur_used_minor']===0&&$no['arrears']['EUR']['interest']===10,'no implicit reserve authority');
$pause=B::step($s,$flow,2,8000,true,true);checkNB($pause['paid_principal']===['FRF'=>0,'EUR'=>0]&&$pause['arrears']['EUR']['principal']===100,'moratorium keeps obligations despite market intervention');
$cut=B::step($s,array_replace($flow,['haircuts'=>['foreign'=>25],'fx_order'=>null]),2,8000,true,false);checkNB($cut['written_off_principal']['EUR']===25&&$cut['closing_principal']['EUR']===400,'explicit haircut distinguished from payment');
// Committed-source adapter, including maturity, coupons, opening conversion,
// FX order, monetary funding and carryover. All fixtures are fictional.
$sha=str_repeat('a',64);$terms=['effective_on'=>'2026-12-01','instrument_sha'=>$sha,'reserve_purchase_authorized'=>true];
$eu=['membership'=>false,'exit_on'=>'2026-12-01','franc_native_budget_terms'=>$terms,'franc_fiscal_terms'=>['effective_on'=>'2026-12-01'],'franc_rule'=>['effective_on'=>'2026-12-01'],'currency_terms'=>['effective_on'=>'2026-12-01'],'franc_funding_version'=>'v1','franc_fx_terms'=>['effective_on'=>'2026-12-01','official_eur_minor'=>100,'official_franc_pool_minor'=>500,'minimum_official_eur_minor'=>50,'counterparty_eur_minor'=>100,'counterparty_franc_pool_minor'=>500],'franc_fx_orders'=>['2026-12-01'=>['kind'=>'sell_eur','eur_minor'=>20]]];
B::validateTerms($terms,'2026-11-30',$eu);rejectNB(fn()=>B::validateTerms($terms,'2026-12-01',$eu));
$cohorts=['legacy-state-french_credit_institutions'=>['opening_frf_principal_minor'=>2000,'opening_eur_principal_minor'=>0],'legacy-state-nonresident'=>['opening_frf_principal_minor'=>0,'opening_eur_principal_minor'=>1000]];
$mk=fn($day,$borrow)=>['day'=>$day,'revenue'=>0,'expense'=>0,'frf_fiscal'=>['value_index_bps'=>8000,'parity_units_per_eur'=>2,'creditors'=>$cohorts,'cash'=>['opening_frf_minor'=>400]],'treasury'=>['new_borrowing'=>$borrow,'market_stress'=>false]];
$mon=fn($day,$advance,$repay,$closing)=>['day'=>$day,'funding'=>['settled_public_spending_franc_minor'=>$advance,'settled_levy_and_repayment_franc_minor'=>$repay,'treasury'=>['advance_liability_franc_minor'=>$closing],'central_bank'=>['treasury_advance_asset_franc_minor'=>$closing]]];
$quote=fn($day)=>['day'=>$day,'franc_per_eur_rate'=>['numerator'=>20000,'denominator'=>8000]];
$dom=['day'=>'2026-12-02','days'=>[$mk('2026-12-01',0),$mk('2026-12-02',142)],'debt_model'=>['opening_holders'=>['legacy-state-french_credit_institutions'=>1000,'legacy-state-nonresident'=>1000],'maturity_month_count'=>10,'initial_coupon_bps'=>3650,'normal_new_coupon_bps'=>3650,'stress_new_coupon_bps'=>5000]];
$money=['days'=>[$mon('2026-12-01',40,0,40),$mon('2026-12-02',0,10,30)]];$fx=['days'=>[$quote('2026-12-01'),$quote('2026-12-02')]];$currency=['units_per_eur_at_parity'=>2];
$p=P::project($eu,$dom,$currency,$money,$fx);$d1=$p['days'][0];$d2=$p['days'][1];
checkNB($d1['paid_principal']===['FRF'=>200,'EUR'=>29]&&$d1['arrears']['EUR']['principal']===71&&$d1['closing_state']['accounts']['official_eur_reserves_minor']===50,'adapter computes contractual maturity and native coupons');
checkNB($d2['paid_principal']['EUR']===71&&$d2['closing_state']['advance_liability']===30&&$d2['issued_principal']===['FRF'=>142,'EUR'=>71]&&$d2['debt_service_current'],'adapter carries debt and separates native new issues');
checkNB($p===P::project($eu,$dom,$currency,$money,$fx)&&$p['months'][0]['days']===2,'deterministic projection and reports');
$bad=$fx;$bad['days'][1]['franc_per_eur_rate']['denominator']=9000;rejectNB(fn()=>P::project($eu,$dom,$currency,$money,$bad));
$bad=$money;$bad['days'][1]['funding']['central_bank']['treasury_advance_asset_franc_minor']=29;rejectNB(fn()=>P::project($eu,$dom,$currency,$bad,$fx));
checkNB(true,'rejects stale quotes, broken monetary counterpart, dates and maturity');
// Long deterministic run: mixed loans, reserve orders, cash flows, advances,
// repayments and moratoria; daily invariants are enforced by step itself.
$state=$s;$rows=[];$date=new DateTimeImmutable('2027-01-01');
for($i=0;$i<400;$i++){
    $advance=$i%5===0?10:0;$repay=$i%5===1?min(5,$state['advance_liability']):0;
    $f=array_replace($flow,['revenue'=>40,'primary_expense'=>25,'advance'=>$advance,'repay'=>$repay,'closing_advance'=>$state['advance_liability']+$advance-$repay,'equity'=>0,'coupons'=>['foreign'=>1,'domestic'=>1],'issues'=>[],'fx_order'=>$i%3===0?['kind'=>'buy_eur','eur_minor'=>2]:null]);
    $q=B::step($state,$f,2,7000+($i%21)*200,true,$i%13===0);$state=$q['closing_state'];$rows[]=['day'=>$date->modify('+'.$i.' days')->format('Y-m-d'),...$q];
}
checkNB(count(P::aggregate($rows,4))===2&&count(P::aggregate($rows,7))===14,'400-day replay across two calendar years');
