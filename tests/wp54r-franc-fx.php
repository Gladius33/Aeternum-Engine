<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanWithdrawal.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,FrancProductionRule as F,CurrencyTransitionLedger as C,FrancExchangeReserves as X};
function ckFX(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS FX reserves: $m\n";}
function noFX(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid FX scenario accepted');}
$d='2026-11-25';$sha=str_repeat('a',64);$e=E::request(E::initial($d,true,true,true,true,true,true,true,true,true),$d);
$e=E::certify($e,$d,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$d,'agreement',['effective_on'=>'2026-11-27','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$d,'currency_terms',['effective_on'=>'2026-11-27','units_per_eur'=>2,'instrument_sha'=>$sha,'contracts'=>[
    ['id'=>'bond','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$d,'franc_rule',['effective_on'=>'2026-11-27','opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_terms',['effective_on'=>'2026-11-27','opening_reserve_allocation_franc_minor'=>100000,'minimum_capital_bps'=>1000,'minimum_liquidity_bps'=>2000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_crisis_terms',['effective_on'=>'2026-11-27','facility_pool_franc_minor'=>100000,'guarantee_pool_franc_minor'=>100000,'collateral_haircut_bps'=>2000,'instrument_sha'=>$sha]);
$terms=['effective_on'=>'2026-11-27','official_eur_minor'=>100000,'counterparty_eur_minor'=>100000,'official_franc_pool_minor'=>200000,'counterparty_franc_pool_minor'=>100000,'minimum_official_eur_minor'=>20000,'instrument_sha'=>$sha];
noFX(fn()=>E::certify($e,$d,'franc_fx_terms',array_replace($terms,['official_franc_pool_minor'=>700000])));
$e=E::certify($e,$d,'franc_fx_terms',$terms);
$order=fn($day,$kind,$amount)=>['effective_on'=>$day,'kind'=>$kind,'eur_minor'=>$amount,'instrument_sha'=>$sha];
$e=E::certify($e,$d,'franc_fx_order',$order('2026-11-27','sell_eur',90000));
noFX(fn()=>E::certify($e,$d,'franc_fx_order',$order('2026-11-27','buy_eur',1)));
$e=E::certify($e,$d,'franc_fx_order',$order('2026-11-28','buy_eur',100000));
$e=E::certify($e,$d,'franc_fx_order',$order('2026-11-29','sell_eur',150000));
$e=E::advance($e,'2026-11-27');
$e=E::certify($e,'2026-11-27','currency_mark',['effective_on'=>'2026-11-28','value_index_bps'=>8000,'instrument_sha'=>$sha]);
$e=E::advance($e,'2026-11-29');
$rows=[];for($i=26;$i<=29;$i++)$rows[]=['day'=>'2026-11-'.$i,'macro'=>['real_gdp'=>1000000]];
$p=X::project($e,C::project($e),F::project($e,['day'=>$e['day'],'days'=>$rows]));$r=$p['days'];
ckFX($r[0]['settled_eur_minor']===50000&&$r[0]['settled_franc_minor']===100000&&$r[0]['unfilled_eur_demand_minor']===40000,'sale capped by counterpart cash');
ckFX($r[1]['valuation_delta_franc_minor']===25000&&$r[1]['settled_eur_minor']===100000&&$r[1]['settled_franc_minor']===250000,'devaluation revalues existing reserves before transaction');
ckFX($r[2]['settled_eur_minor']===100000&&$r[2]['official_eur_reserves_minor']===50000&&$r[2]['unfilled_eur_demand_minor']===50000,'repeat sale capped by available francs');
foreach($r as $v)ckFX($v['official_eur_reserves_minor']+$v['counterparty_eur_assets_minor']===200000&&$v['official_franc_pool_minor']+$v['counterparty_franc_pool_minor']===300000&&$v['base_money_created_franc_minor']===0,'both currency stocks conserved');
ckFX(E::restore($e)===$e&&!$p['market_price_endogenous'],'replay and exogenous mark explicit');
$floor=E::advance($e,'2026-11-30');
$floor['franc_fx_orders']['2026-11-30']=$order('2026-11-30','sell_eur',100000);
$rows[]=['day'=>'2026-11-30','macro'=>['real_gdp'=>1000000]];
$q=X::project($floor,C::project($floor),F::project($floor,['day'=>$floor['day'],'days'=>$rows]));
ckFX($q['latest']['official_eur_reserves_minor']>=20000,'reserve floor cannot be breached');

// Independent configured market: prospective mode cannot mix with manual marks.
$m=E::request(E::initial($d,true,true,true,true,true,true,true,true,true,true),$d);
foreach($e['history'] as $h){
    if($h['kind']==='intent'||$h['kind']==='currency_mark')continue;
    $m=E::certify($m,$h['day'],$h['kind'],$h['data']);
}
$m=E::certify($m,$d,'franc_fx_market',['effective_on'=>'2026-11-27','market_depth_eur_minor'=>100000,'daily_move_cap_bps'=>500,'instrument_sha'=>$sha]);
$m=E::advance($m,'2026-11-27');
noFX(fn()=>E::certify($m,'2026-11-27','currency_mark',['effective_on'=>'2026-11-28','value_index_bps'=>8000,'instrument_sha'=>$sha]));
$m=E::advance($m,'2026-11-29');
$mc=C::project($m);$mx=X::project($m,$mc,F::project($m,['day'=>$m['day'],'days'=>array_slice($rows,0,4)]));
ckFX($mx['days'][0]['franc_per_eur_rate']['denominator']===10000&&$mx['days'][1]['franc_per_eur_rate']['denominator']===9550,'lagged demand depreciates franc, no same-day lookahead');
ckFX($mx['latest']['franc_per_eur_rate']['denominator']===10050&&$mc['new_currency_value_index_bps']===10050&&$mx['market_price_endogenous'],'lagged supply appreciates franc, shared contract and settlement quote');
ckFX($mc['fx_market']['changes'][0]['source_order_day']==='2026-11-27'&&E::restore($m)===$m,'market replay and quote provenance');
