<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrancFiscalLedger.php';
use Aeternum\WorldPolitics\FrancFiscalLedger as F;
function ckFL(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS FRF fiscal: $m\n";}
$terms=['effective_on'=>'2026-11-27','converted_opening_cohorts'=>['legacy-state-french_credit_institutions'],'instrument_sha'=>str_repeat('a',64)];
F::validateTerms($terms,'2026-11-26',['currency_terms'=>['effective_on'=>'2026-11-27']]);
$c=['opening_holders'=>['legacy-state-french_credit_institutions'=>1000,'legacy-state-nonresident'=>2000],'new_holder_keys'=>['new-normal-domestic','new-normal-external']];
$row=['revenue'=>100,'expense'=>150,'treasury'=>['opening_cash'=>100,'opening_debt'=>3000,'primary_paid'=>150,'interest'=>10,'principal_paid'=>0,'new_borrowing'=>100,'cash'=>40,'debt'=>3100,
    'creditors'=>['legacy-state-french_credit_institutions'=>1000,'legacy-state-nonresident'=>2000,'new-normal-domestic'=>50,'new-normal-external'=>50]]];
$f=F::step($row,null,$c,$terms,2,10000);
ckFL($f['cash']['closing_frf_minor']===80&&$f['debt']['closing_frf_valuation_minor']===6200,'opening and cash identity at parity');
ckFL($f['debt']['closing_frf_principal_minor']===2100&&$f['debt']['closing_eur_principal_minor']===2050,'certified opening and new domestic FRF, external EUR');
$prev=$row+['frf_fiscal'=>$f];$next=['revenue'=>200,'expense'=>100,'treasury'=>['opening_cash'=>40,'opening_debt'=>3100,'primary_paid'=>100,'interest'=>10,'principal_paid'=>100,'new_borrowing'=>0,'cash'=>30,'debt'=>3000,
    'creditors'=>['legacy-state-french_credit_institutions'=>960,'legacy-state-nonresident'=>1940,'new-normal-domestic'=>50,'new-normal-external'=>50]]];
$g=F::step($next,$prev,$c,$terms,2,8000);$d=$g['debt'];
ckFL($g['cash']['closing_frf_minor']===60&&$g['cash']['opening_frf_minor']===80,'cash continuity, no revaluation of domestic cash');
ckFL($d['closing_frf_principal_minor']===2020&&$d['closing_eur_principal_minor']===1990,'principal reduction conserved by currency');
ckFL($d['fx_revaluation_frf_minor']===1025&&$d['transaction_stock_delta_frf_minor']===-230&&$d['total_stock_delta_frf_minor']===795,'separate valuation and transaction deltas');
ckFL(!$g['cash_settlement_at_market_fx_modelled']&&!$g['central_bank_advances_integrated']&&!$g['separate_contract_book_added_to_debt'],'limits explicit and no duplicate debt');
try{F::validateTerms(array_replace($terms,['converted_opening_cohorts'=>['legacy-state-french_credit_institutions','legacy-state-french_credit_institutions']]),'2026-11-26',['currency_terms'=>['effective_on'=>'2026-11-27']]);throw new RuntimeException('duplicate accepted');}catch(DomainException $e){}
