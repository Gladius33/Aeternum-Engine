<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanWithdrawal.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,FrancProductionRule as F};
function ckBC(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS bank crisis: $m\n";}
function noBC(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid bank crisis accepted');}
$day='2026-11-25';$sha=str_repeat('a',64);
$e=E::request(E::initial($day,true,true,true,true,true,true,true,true),$day);
$e=E::certify($e,$day,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$day,'agreement',['effective_on'=>'2026-11-27','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$day,'currency_terms',['effective_on'=>'2026-11-27','units_per_eur'=>1,'instrument_sha'=>$sha,'contracts'=>[
    ['id'=>'bond-fr','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$day,'franc_rule',['effective_on'=>'2026-11-27','opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$e=E::certify($e,$day,'franc_bank_terms',['effective_on'=>'2026-11-27','opening_reserve_allocation_franc_minor'=>100000,'minimum_capital_bps'=>1000,'minimum_liquidity_bps'=>2000,'instrument_sha'=>$sha]);
$terms=['effective_on'=>'2026-11-27','facility_pool_franc_minor'=>200000,'guarantee_pool_franc_minor'=>50000,'collateral_haircut_bps'=>2000,'instrument_sha'=>$sha];
noBC(fn()=>E::certify($e,$day,'franc_bank_crisis_terms',array_replace($terms,['facility_pool_franc_minor'=>900000])));
$e=E::certify($e,$day,'franc_bank_crisis_terms',$terms);
$order=fn($d,$k,$v)=>['effective_on'=>$d,'kind'=>$k,'amount_franc_minor'=>$v,'instrument_sha'=>$sha];
$orders=[['2026-11-27','credit',500000],['2026-11-28','cash_withdrawal',200000],['2026-11-29','liquidity_support',300000],
    ['2026-11-30','cash_withdrawal',150000],['2026-12-01','cash_redeposit',50000],['2026-12-02','liquidity_repayment',100000],
    ['2026-12-03','certified_writeoff',200000],['2026-12-04','guarantee_payout',100000],['2026-12-05','liquidity_support',100000],['2026-12-06','credit',100000]];
foreach($orders as [$d,$k,$v])$e=E::certify($e,$day,'franc_bank_order',$order($d,$k,$v));
$rows=[];$dt=new DateTimeImmutable('2026-11-26');
for($i=0;$i<11;$i++){$rows[]=['day'=>$dt->format('Y-m-d'),'macro'=>['real_gdp'=>1000000]];$dt=$dt->modify('+1 day');}
$end=E::advance($e,'2026-12-06');$p=F::project($end,['day'=>'2026-12-06','days'=>$rows]);$r=$p['commercial_banking']['days'];
ckBC($r[1]['crisis']['cash_withdrawn_franc_minor']===100000&&$r[1]['crisis']['unserved_eligible_withdrawal_franc_minor']===100000&&$r[1]['bank_reserves_franc_minor']===0,'run is capped by cash reserves and unserved deposit remains');
ckBC($r[2]['crisis']['facility_advance_franc_minor']===200000&&$r[2]['crisis']['bank_facility_liability_franc_minor']===200000,'support capped by prefunded pool and collateral');
ckBC($r[3]['crisis']['cash_withdrawn_franc_minor']===150000&&$r[3]['crisis']['household_cash_franc_minor']===250000,'support enables withdrawals');
ckBC($r[4]['crisis']['cash_redeposited_franc_minor']===50000&&$r[5]['crisis']['facility_repayment_franc_minor']===100000,'redeposit and facility repayment conserve base');
ckBC($r[6]['insolvent']&&$r[7]['crisis']['guarantee_paid_franc_minor']===50000&&$r[7]['crisis']['bank_subrogated_guarantee_liability_franc_minor']===50000,'prefunded guarantee transfers creditor claim');
ckBC($r[8]['crisis']['facility_advance_franc_minor']===0&&$r[9]['new_credit_franc_minor']===0,'insolvent bank cannot receive ordinary support or originate credit');
foreach($r as $v){$c=$v['crisis'];ckBC($v['bank_balance_difference_franc_minor']===0&&$v['monetary_base_franc_minor']===1000000&&$c['base_money_created_by_crisis_franc_minor']===0&&$v['bank_reserves_franc_minor']+$c['household_cash_franc_minor']+$c['facility_available_franc_minor']+$c['guarantee_available_franc_minor']===350000,'daily balance and preallocated base conserved');}
ckBC(E::restore($end)===$end,'deterministic evidence replay');
