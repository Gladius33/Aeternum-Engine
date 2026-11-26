<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanSpillovers.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,EuropeanSpillovers as S};
function ckNI(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS native integration: $m\n";}
function noNI(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Unauthorized native terms accepted');}
$d='2026-11-29';$start='2026-12-01';$sha=str_repeat('a',64);
$e=E::request(E::initial($d,true,true,true,true,true,true,true,true,true,false,false,true),$d);
$terms=['effective_on'=>$start,'instrument_sha'=>$sha,'reserve_purchase_authorized'=>true];
noNI(fn()=>E::certify($e,$d,'franc_native_budget',$terms));
$e=E::certify($e,$d,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$d,'agreement',['effective_on'=>$start,'parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$d,'currency_terms',['effective_on'=>$start,'units_per_eur'=>2,'instrument_sha'=>$sha,'contracts'=>[['id'=>'bond','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$d,'franc_rule',['effective_on'=>$start,'opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_terms',['effective_on'=>$start,'opening_reserve_allocation_franc_minor'=>100000,'minimum_capital_bps'=>1000,'minimum_liquidity_bps'=>2000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_crisis_terms',['effective_on'=>$start,'facility_pool_franc_minor'=>100000,'guarantee_pool_franc_minor'=>100000,'collateral_haircut_bps'=>2000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fx_terms',['effective_on'=>$start,'official_eur_minor'=>100,'counterparty_eur_minor'=>100,'official_franc_pool_minor'=>500,'counterparty_franc_pool_minor'=>500,'minimum_official_eur_minor'=>50,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fiscal',['effective_on'=>$start,'converted_opening_cohorts'=>['legacy-state-french_credit_institutions'],'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fx_order',['effective_on'=>$start,'kind'=>'sell_eur','eur_minor'=>20,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_funding',['effective_on'=>'2026-12-02','spending_requested_franc_minor'=>40,'repayment_requested_franc_minor'=>0,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_native_budget',$terms);
noNI(fn()=>E::certify($e,$d,'franc_native_budget',$terms));
$rev=E::certify($e,$d,'revocation',['national_decision_sha'=>$sha,'notice_sha'=>$sha,'unequivocal_unconditional'=>true]);
ckNI(!isset($rev['franc_native_budget_terms'])&&E::restore($rev)===$rev,'revocation clears terms and replay is exact');
$cohorts=['legacy-state-french_credit_institutions'=>['opening_frf_principal_minor'=>2000,'opening_eur_principal_minor'=>0],'legacy-state-nonresident'=>['opening_frf_principal_minor'=>0,'opening_eur_principal_minor'=>1000]];
$macro=fn($g)=>['real_gdp'=>$g];
$row=fn($day,$g,$mark,$borrow)=>['day'=>$day,'macro'=>$macro($g),'revenue'=>0,'expense'=>0,'frf_fiscal'=>['value_index_bps'=>$mark,'parity_units_per_eur'=>2,'creditors'=>$cohorts,'cash'=>['opening_frf_minor'=>400]],'treasury'=>['new_borrowing'=>$borrow,'market_stress'=>false]];
$prior=['day'=>'2026-11-30','macro'=>$macro(1000000)];
$project=fn($s,$days)=>S::project(['state'=>['world_id'=>'world-test','day'=>$s['day'],'eu_withdrawal'=>$s],'etag'=>'"'.$sha.'"'],['state'=>['world_id'=>'world-test','day'=>$s['day'],'eu_trade_version'=>'wp54r-eu-trade-v4-frf-fiscal','days'=>$days,'debt_model'=>['opening_holders'=>['legacy-state-french_credit_institutions'=>1000,'legacy-state-nonresident'=>1000],'maturity_month_count'=>10,'initial_coupon_bps'=>3650,'normal_new_coupon_bps'=>3650,'stress_new_coupon_bps'=>5000]],'etag'=>'"'.$sha.'"']);
$pending=$project(E::advance($e,'2026-11-30'),[$prior]);ckNI($pending['franc_native_budget']['status']==='pending_effective_date'&&!$pending['franc_exchange_reserves_reference_only'],'no early native settlement');
$active=E::advance($e,$start);$first=$row($start,1200000,10000,0);
$p=$project($active,[$prior,$first]);
ckNI($p['franc_native_budget']['latest']['closing_state']['accounts']['official_eur_reserves_minor']===50&&$p['franc_exchange_reserves']['latest']['official_eur_reserves_minor']===80&&$p['franc_exchange_reserves_reference_only'],'API projection explicitly separates combined and isolated reserve views');
$active=E::certify($active,$start,'currency_mark',['effective_on'=>'2026-12-02','value_index_bps'=>8000,'instrument_sha'=>$sha]);
$active=E::advance($active,'2026-12-02');$days=[$prior,$first,$row('2026-12-02',1200000,8000,142)];$p=$project($active,$days);$q=$p['franc_native_budget']['latest'];
ckNI($q['budget']['advance_received_frf_minor']===40&&$q['closing_state']['advance_liability']===40&&$q['valuation']['fx_revaluation_frf_minor']>0,'committed production and FX mark reach integrated budget');
ckNI(E::restore($active)===$active&&$p===$project($active,$days),'legal replay and coherent projection deterministic');
