<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/FrenchMacroBaseline.php';
use Aeternum\WorldPolitics\{FrenchMacroBaseline as B,MacroFiscalLoop as M,EuropeanTradeFeedback as E,FrancMacroFeedback as F};
function ckFM(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);echo "PASS FRF macro: $m\n";}
$b=B::load();$sha=str_repeat('a',64);
$obs=fn($day,$mark)=>['version'=>E::VERSION_V3,'world_id'=>'world','day'=>$day,'organization_sha'=>$sha,'status'=>'unmodelled_post_exit','regime'=>null,'import_duty_bps'=>0,'import_friction_bps'=>0,'export_friction_bps'=>0,'frf_value_index_bps'=>$mark,'prior_domestic_sha'=>$sha];
$step=fn($prior,$mark,$day)=>M::step($b,$prior,$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],10000,10000,null,null,null,0,null,null,0,$obs($day,$mark));
E::validate($obs('2026-11-27',8000),'world','2026-11-27',E::VERSION_V3);
$par=$step(null,10000,'2026-11-27');$shock=$step(null,8000,'2026-11-27');$up=$step(null,12000,'2026-11-27');
ckFM($shock['macro']['real_gdp']===$par['macro']['real_gdp']&&$shock['macro']['price_index_scaled']>$par['macro']['price_index_scaled']&&$up['macro']['price_index_scaled']<$par['macro']['price_index_scaled'],'depreciation raises price level, appreciation lowers it, no contemporaneous real volume effect');
$next=$step($shock,8000,'2026-11-28');$fx=$next['macro']['frf_feedback'];
ckFM($fx['price_level_shock_bps']===0&&$fx['real_import_loss_minor']>0&&$fx['real_import_loss_minor']===$fx['real_consumption_shortfall_minor']+$fx['real_investment_shortfall_minor'],'persistent exchange rate no repeated level shock, balanced import shortfall');
ckFM($fx['automatic_import_substitution_minor']===0&&$next['macro']['real_wage_index_bps']<10000,'no fictional substitution, real wage pressure');
foreach([$par,$shock,$up,$next] as $r){$m=$r['macro'];ckFM($m['real_gdp']===$m['consumption']+$m['npish_consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy'],'GDP identity');}
$v=F::volumes(8000,100000,100000,100000,100000,1000000,1000000);ckFM($v['real_export_delta_minor']===0,'no export gain without spare capacity');
$v=F::volumes(8000,100000,100000,100000,100000,999000,1000000);ckFM($v['real_export_delta_minor']===1000,'export gain bounded by spare capacity');
