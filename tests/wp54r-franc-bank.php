<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanWithdrawal.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,FrancProductionRule as F};
function ckFB(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS franc bank: $m\n";}
function noFB(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid bank evidence accepted');}
$day='2026-11-25';$sha=str_repeat('a',64);
$e=E::request(E::initial($day,true,true,true,true,true,true,true),$day);
$e=E::certify($e,$day,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$day,'agreement',['effective_on'=>'2026-11-27','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$day,'currency_terms',['effective_on'=>'2026-11-27','units_per_eur'=>1,'instrument_sha'=>$sha,'contracts'=>[
    ['id'=>'bond-fr','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$day,'franc_rule',['effective_on'=>'2026-11-27','opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$terms=['effective_on'=>'2026-11-27','opening_reserve_allocation_franc_minor'=>100000,'minimum_capital_bps'=>1000,'minimum_liquidity_bps'=>2000,'instrument_sha'=>$sha];
noFB(fn()=>E::certify($e,$day,'franc_bank_terms',array_replace($terms,['opening_reserve_allocation_franc_minor'=>1000001])));
$e=E::certify($e,$day,'franc_bank_terms',$terms);
$order=fn($d,$k,$v)=>['effective_on'=>$d,'kind'=>$k,'amount_franc_minor'=>$v,'instrument_sha'=>$sha];
$e=E::certify($e,$day,'franc_bank_order',$order('2026-11-27','credit',1000000));
noFB(fn()=>E::certify($e,$day,'franc_bank_order',$order('2026-11-27','credit',1)));
$e=E::certify($e,$day,'franc_funding',['effective_on'=>'2026-11-28','spending_requested_franc_minor'=>100000,'repayment_requested_franc_minor'=>0,'instrument_sha'=>$sha]);
$e=E::certify($e,$day,'franc_bank_order',$order('2026-11-28','principal_repayment',100000));
$e=E::certify($e,$day,'franc_bank_order',$order('2026-11-29','interest_payment',10000));
$e=E::certify($e,$day,'franc_bank_order',$order('2026-11-30','certified_writeoff',200000));
$e=E::certify($e,$day,'franc_bank_order',$order('2026-12-01','credit',100000));
$rows=[];$dt=new DateTimeImmutable('2026-11-26');
foreach([1000000,1200000,1200000,1000000,1000000,1000000] as $g){$rows[]=['day'=>$dt->format('Y-m-d'),'macro'=>['real_gdp'=>$g]];$dt=$dt->modify('+1 day');}
$end=E::advance($e,'2026-12-01');$p=F::project($end,['day'=>'2026-12-01','days'=>$rows]);$b=$p['commercial_banking'];$r=$b['days'];
ckFB($r[0]['new_credit_franc_minor']===500000&&$r[0]['unsettled_franc_minor']===500000&&$r[0]['reserve_to_deposit_bps']===2000,'credit capped by liquidity');
ckFB($r[0]['monetary_base_franc_minor']===1000000&&$r[0]['private_credit_deposits_franc_minor']===500000&&$r[0]['commercial_credit_base_creation_franc_minor']===0,'credit creates deposits not central bank base');
ckFB($r[1]['principal_repaid_franc_minor']===100000&&$r[1]['bank_loan_assets_franc_minor']===400000&&$r[1]['private_credit_deposits_franc_minor']===400000,'principal repayment destroys deposits');
ckFB($r[1]['public_payment_deposits_franc_minor']===100000&&$r[1]['bank_deposit_liabilities_franc_minor']===500000&&$r[1]['bank_reserves_franc_minor']===200000,'public funding included once');
ckFB($r[2]['interest_paid_franc_minor']===10000&&$r[2]['bank_equity_franc_minor']===110000&&$r[2]['private_credit_deposits_franc_minor']===390000,'interest moves deposits to retained equity');
ckFB($r[3]['loan_written_off_franc_minor']===200000&&$r[3]['bank_equity_franc_minor']===-90000&&$r[3]['private_credit_deposits_franc_minor']===390000&&$r[3]['insolvent'],'writeoff absorbs equity, leaves deposits intact');
ckFB($r[4]['new_credit_franc_minor']===0&&!$r[4]['capital_requirement_met'],'insolvent bank cannot originate');
foreach($r as $v)ckFB($v['bank_assets_franc_minor']===$v['bank_deposit_liabilities_franc_minor']+$v['bank_equity_franc_minor']&&$v['bank_loan_assets_franc_minor']===$v['borrower_loan_liabilities_franc_minor'],'daily balance and borrower counterpart');
ckFB(E::restore($end)===$end&&$b['full_national_broad_money_franc_minor']===null,'replay and incomplete coverage explicit');
