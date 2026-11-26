<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,EuropeanTradeFeedback as T,FrenchMacroBaseline,MacroFiscalLoop};
function ckET(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS EU trade: $m\n";}
$start='2026-11-25';$sha=str_repeat('a',64);
$e=E::request(E::initial($start,true),$start);
$e=E::certify($e,$start,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$start,'agreement',['effective_on'=>'2026-11-27','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$terms=['regime'=>'customs_scenario','effective_on'=>'2026-11-26','import_duty_bps'=>500,'import_friction_bps'=>400,'export_friction_bps'=>600,'instrument_sha'=>$sha];
$x=E::certify($e,$start,'trade_regime',$terms);
$obs=fn(array $s)=>T::observe(['state'=>['world_id'=>'world-test','day'=>$s['day'],'eu_withdrawal'=>$s],'etag'=>'"'.$sha.'"'],'world-test',$s['day']);
$before=$obs(E::advance($x,'2026-11-26'));
$unknown=$obs(E::advance($e,'2026-11-27'));$after=$obs(E::advance($x,'2026-11-27'));
ckET($before['status']==='member'&&$before['import_duty_bps']===0,'no premature duties');
ckET($unknown['status']==='unmodelled_post_exit'&&$unknown['import_duty_bps']===0,'missing terms explicit not zero trade forecast');
ckET($after['status']==='certified_scenario'&&$after['import_duty_bps']===500,'effective certified terms');
$b=FrenchMacroBaseline::load();
$run=fn(?array $eu)=>MacroFiscalLoop::step($b,null,$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],10000,10000,null,null,null,0,null,0,0,$eu);
$old=$run(null);$zero=$run($before);$new=$run($after);$m=$new['macro'];$r=$m['eu_trade'];
ckET($zero['revenue']===$old['revenue']&&$zero['macro']['real_gdp']===$old['macro']['real_gdp'],'zero regime economically neutral');
ckET($r['customs_revenue_minor']===intdiv($r['nominal_taxable_imports_minor']*500,10000)&&$new['revenue']===$old['revenue']+$r['customs_revenue_minor'],'duties on surviving nominal imports once');
ckET($m['exports']<$old['macro']['exports']&&$m['imports']<$old['macro']['imports']&&$m['consumption']<$old['macro']['consumption'],'trade friction and purchasing power');
ckET($m['real_gdp']===$m['consumption']+$m['npish_consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy'],'GDP identity');
ckET($new['treasury']['primary_balance']===$new['revenue']-$new['expense'],'budget identity');
ckET(E::restore(E::advance($x,'2026-11-27'))===E::advance($x,'2026-11-27'),'legal terms replay');
