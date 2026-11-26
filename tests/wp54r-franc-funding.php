<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanWithdrawal.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,FrancProductionRule as F,FrancPublicFunding as P};
function ckPF(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS franc funding: $m\n";}
function noPF(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid order accepted');}
$day='2026-11-25';$sha=str_repeat('a',64);
$e=E::request(E::initial($day,true,true,true,true,true,true),$day);
$e=E::certify($e,$day,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$day,'agreement',['effective_on'=>'2026-11-27','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$day,'currency_terms',['effective_on'=>'2026-11-27','units_per_eur'=>1,'instrument_sha'=>$sha,'contracts'=>[
    ['id'=>'bond-fr','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$day,'franc_rule',['effective_on'=>'2026-11-27','opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$order=fn($d,$s,$r)=>['effective_on'=>$d,'spending_requested_franc_minor'=>$s,'repayment_requested_franc_minor'=>$r,'instrument_sha'=>$sha];
noPF(fn()=>E::certify($e,$day,'franc_funding',$order('2026-11-28',100,100)));
$e=E::certify($e,$day,'franc_funding',$order('2026-11-27',100000,0));
$e=E::certify($e,$day,'franc_funding',$order('2026-11-28',200000,0));
noPF(fn()=>E::certify($e,$day,'franc_funding',$order('2026-11-28',1,0)));
$e=E::certify($e,$day,'franc_funding',$order('2026-11-29',0,60000));
$e=E::certify($e,$day,'franc_funding',$order('2026-11-30',0,100000));
$rows=[];foreach([1000000,1200000,100000,100000,1500000] as $i=>$g)$rows[]=['day'=>sprintf('2026-11-%02d',26+$i),'macro'=>['real_gdp'=>$g]];
$end=E::advance($e,'2026-11-30');$p=F::project($end,['day'=>'2026-11-30','days'=>$rows]);$r=$p['days'];
ckPF($r[0]['issued_franc_minor']===0&&$r[0]['funding']['unfunded_spending_franc_minor']===100000,'no initial spare capacity');
ckPF($r[1]['issued_franc_minor']===100000&&$r[1]['funding']['unfunded_spending_franc_minor']===100000,'partial financing strictly capped');
ckPF($r[2]['funding']['destroyed_franc_minor']===60000&&$r[2]['closing_base_franc_minor']===1040000,'certified levy and repayment destroy matching money');
ckPF($r[3]['funding']['destroyed_franc_minor']===40000&&$r[3]['funding']['unfunded_repayment_franc_minor']===60000,'repayment cannot exceed modeled deposits and debt');
ckPF($r[3]['closing_base_franc_minor']===1000000&&$r[3]['cumulative_issued_franc_minor']===100000&&$r[3]['funding']['cumulative_destroyed_franc_minor']===100000,'gross issuance minus destruction equals stock change');
foreach($r as $v){$f=$v['funding'];ckPF($f['central_bank']['treasury_advance_asset_franc_minor']===$f['treasury']['advance_liability_franc_minor']&&$f['central_bank']['bank_reserve_liability_franc_minor']===$f['commercial_banks']['reserve_asset_franc_minor']&&$f['commercial_banks']['beneficiary_deposit_liability_franc_minor']===$f['domestic_beneficiaries']['deposit_asset_franc_minor'],'all counterparty balances match');}
ckPF(E::restore($end)===$end&&!$p['treasury_spending_booked'],'replay and historical treasury isolation');
$no=$e;$no['franc_funding_orders']=[];$z=F::project(E::advance($no,'2026-11-30'),['day'=>'2026-11-30','days'=>$rows]);
ckPF($z['days'][1]['issued_franc_minor']===0&&$z['days'][1]['funding']['remaining_issuance_capacity_franc_minor']===100000,'no unsolicited issuance');

$first=E::request(E::initial($day,true,true,true,true,true,true),$day);
$first=E::certify($first,$day,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$first=E::certify($first,$day,'agreement',['effective_on'=>'2026-11-26','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$first=E::certify($first,$day,'currency_terms',['effective_on'=>'2026-11-26','units_per_eur'=>1,'instrument_sha'=>$sha,'contracts'=>[
    ['id'=>'bond-fr','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$first=E::certify($first,$day,'franc_rule',['effective_on'=>'2026-11-26','opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$first=E::certify($first,$day,'franc_funding',$order('2026-11-27',300000,0));
$first=E::advance($first,'2026-11-27');
$fp=F::project($first,['day'=>'2026-11-27','macro_baseline'=>['daily_real_gdp'=>1000000],'days'=>[
    ['day'=>'2026-11-26','macro'=>['real_gdp'=>1200000]],['day'=>'2026-11-27','macro'=>['real_gdp'=>9999999]]]]);
ckPF($fp['production_anchor_source']==='pinned_opening_real_gdp'&&$fp['days'][0]['sample_days']===0&&$fp['days'][0]['issued_franc_minor']===0,'first-day transition uses pinned baseline');
ckPF($fp['latest']['issued_franc_minor']===200000&&$fp['latest']['funding']['unfunded_spending_franc_minor']===100000,'next day uses committed production, not current output');
noPF(fn()=>F::project($first,['day'=>'2026-11-27','days'=>[['day'=>'2026-11-26','macro'=>['real_gdp'=>1200000]],['day'=>'2026-11-27','macro'=>['real_gdp'=>9999999]]]]));
