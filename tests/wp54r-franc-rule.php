<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanWithdrawal.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,FrancProductionRule as F};
function ckFR(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS franc rule: $m\n";}
$day='2026-11-25';$sha=str_repeat('a',64);
$e=E::request(E::initial($day,true,true,true,true,true),$day);
$e=E::certify($e,$day,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$day,'agreement',['effective_on'=>'2026-11-27','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$day,'currency_terms',['effective_on'=>'2026-11-27','units_per_eur'=>1,'instrument_sha'=>$sha,'contracts'=>[
    ['id'=>'bond-fr','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$day,'franc_rule',['effective_on'=>'2026-11-27','opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$rows=[];foreach([1000000,1200000,100000,100000,1500000] as $i=>$g)$rows[]=['day'=>sprintf('2026-11-%02d',26+$i),'macro'=>['real_gdp'=>$g,'nominal_gdp'=>$g*2]];
$project=fn($s,$n)=>F::project($s,['day'=>$s['day'],'days'=>array_slice($rows,0,$n)]);
$p=$project(E::advance($e,'2026-11-26'),1);ckFR($p['latest']===null,'no issuance before conversion');
$p=$project(E::advance($e,'2026-11-30'),5);$r=$p['days'];
ckFR($r[0]['issued_franc_minor']===0&&$r[0]['target_base_franc_minor']===1000000,'opening parity uses preceding output');
ckFR($r[1]['target_base_franc_minor']===1100000&&$r[1]['issued_franc_minor']===100000,'higher real production allows proportional issuance');
ckFR($r[2]['issued_franc_minor']===0&&$r[2]['excess_base_franc_minor']>0&&$r[2]['closing_base_franc_minor']===1100000,'recession freezes issuance without inventing repayment');
ckFR($r[3]['issued_franc_minor']===0,'no same-day financing circularity');
ckFR($p['latest']['central_bank_incremental_claim_franc_minor']===$p['latest']['central_bank_incremental_liability_franc_minor']&&!$p['treasury_spending_booked'],'balanced pilot accounts');
$rows2=$rows;foreach($rows2 as &$row)$row['macro']['nominal_gdp']*=4;unset($row);
ckFR(F::project(E::advance($e,'2026-11-30'),['day'=>'2026-11-30','days'=>$rows2])===$p,'nominal inflation cannot increase issuance');
ckFR(E::restore(E::advance($e,'2026-11-30'))===E::advance($e,'2026-11-30'),'exact legal replay');
