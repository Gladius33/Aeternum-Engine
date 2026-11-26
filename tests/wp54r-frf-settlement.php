<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrancDebtSettlement.php';
use Aeternum\WorldPolitics\FrancDebtSettlement as S;
function ckSet(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS FRF settlement: $m\n";}
function noSet(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Invalid settlement accepted');}
$a=['treasury_frf_minor'=>300,'treasury_eur_minor'=>10,'official_eur_reserves_minor'=>200,'official_franc_pool_minor'=>500,'minimum_official_eur_minor'=>50];
$c=['foreign'=>['currency'=>'EUR','principal_outstanding_minor'=>500,'principal_due_minor'=>100,'interest_due_minor'=>20],
    'domestic'=>['currency'=>'FRF','principal_outstanding_minor'=>500,'principal_due_minor'=>40,'interest_due_minor'=>10]];
$p=S::step($a,$c,2,10000,true);$q=S::step($a,$c,2,8000,true);
ckSet($p['reserve_eur_used_minor']===110&&$p['treasury_frf_paid_for_eur_minor']===220&&$p['closing_accounts']['treasury_frf_minor']===30,'parity pays native principal and interest');
ckSet($q['reserve_eur_used_minor']===100&&$q['treasury_frf_paid_for_eur_minor']===250&&$q['closing_claims']['foreign']['principal_due_minor']===10&&$q['closing_claims']['foreign']['principal_outstanding_minor']===410,'depreciation consumes cash and leaves actual arrears');
ckSet($q['closing_accounts']['official_eur_reserves_minor']===100&&$q['closing_accounts']['official_franc_pool_minor']===750&&$q['creditor_receipts_by_currency_minor']===['FRF'=>50,'EUR'=>110],'reserve draw and both currencies conserve');
$r=S::step($a,$c,2,8000);ckSet($r['reserve_eur_used_minor']===0&&$r['closing_claims']['foreign']['interest_due_minor']===10&&$r['closing_claims']['foreign']['principal_due_minor']===100,'no unauthorized reserves, interest before principal');
$f=S::step(array_replace($a,['minimum_official_eur_minor'=>195]),$c,2,10000,true);ckSet($f['reserve_eur_used_minor']===5&&$f['closing_accounts']['official_eur_reserves_minor']===195,'reserve floor binding');
$m=S::step($a,$c,2,8000,true,true);ckSet($m['closing_accounts']===$a&&$m['closing_claims']===$m['opening_claims'],'moratorium preserves liabilities');
$n=S::step(array_replace($q['closing_accounts'],['treasury_frf_minor'=>25]),$q['closing_claims'],2,8000,true);ckSet($n['closing_claims']['foreign']['principal_due_minor']===0&&$n['closing_claims']['foreign']['principal_outstanding_minor']===400,'next day pays only carried arrears');
$one=['x'=>['currency'=>'EUR','principal_outstanding_minor'=>1,'principal_due_minor'=>1,'interest_due_minor'=>0]];
$z=S::step(array_replace($a,['treasury_frf_minor'=>2,'treasury_eur_minor'=>0]),$one,2,8000,true);ckSet($z['reserve_eur_used_minor']===0,'fractional quote cannot overspend');
$z=S::step(array_replace($a,['treasury_frf_minor'=>3,'treasury_eur_minor'=>0]),$one,2,8000,true);ckSet($z['treasury_frf_paid_for_eur_minor']===3,'FX payment rounds up to minor unit');
ckSet(S::step($a,array_reverse($c,true),2,8000,true)===$q,'deterministic canonical claim order');
noSet(fn()=>S::step($a,['x'=>array_replace($one['x'],['currency'=>'USD'])],2,8000));
noSet(fn()=>S::step($a,['x'=>array_replace($one['x'],['principal_due_minor'=>2])],2,8000));
noSet(fn()=>S::step(array_replace($a,['minimum_official_eur_minor'=>201]),$c,2,8000));
noSet(fn()=>S::step($a,$c,2,0));
noSet(fn()=>S::step(array_replace($a,['treasury_frf_minor'=>-1]),$c,2,8000));
ckSet(true,'invalid inputs rejected');
