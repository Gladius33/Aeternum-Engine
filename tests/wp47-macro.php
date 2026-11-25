<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/MacroeconomicProjection.php';
use Aeternum\WorldPolitics\{MacroeconomicProjection,FinanceBudget};
function ck47(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP47 FAIL: '.$s);echo "PASS WP47: $s\n";}
function no47(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck47(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
$d=['world_id'=>'world-test','day'=>'2026-11-28','balance'=>997000000,'policies'=>[],'days'=>[]];
for($i=0;$i<3;$i++)$d['days'][]=['day'=>'2026-11-'.(26+$i),'opening_balance'=>1000000000-$i*1000000,'revenue'=>10000000,'expense'=>11000000,
    'economic'=>['production_index_bps'=>10000,'employment_index_bps'=>10000]];
$e=['world_id'=>'world-test','day'=>$d['day'],'policies'=>[],'projections'=>[]];$f=FinanceBudget::initial();
$a=MacroeconomicProjection::project($d,$e,$f);$r=$a['latest'];
ck47($r['real_gdp']===25000000&&$r['real_gdp']===$r['consumption']+$r['investment']+$r['government_consumption']+$r['exports']-$r['imports'],'GDP expenditure identity');
ck47($r['unemployment_bps']===750&&$r['price_index_bps']>10000,'stable employment and accumulated prices');
ck47($a['cumulative_real_gdp']===75000000&&$a===MacroeconomicProjection::project($d,$e,$f),'deterministic history');
$tax=$d;$tax['days'][2]['revenue']+=600000;$tax['days'][2]['expense']+=100000;
$b=MacroeconomicProjection::project($tax,$e,$f);
ck47($b['latest']['consumption']<$r['consumption']&&$b['latest']['government_consumption']>$r['government_consumption'],'fiscal transmission');
ck47($b['days'][0]===$a['days'][0]&&$b['latest']['actual_revenue']===10600000,'no retroactive fiscal effect');
$trade=$e;$trade['policies']['trade']=['from'=>'state_fr','to'=>'state_de','status'=>'effective','effective_on'=>'2026-11-28'];
$trade['projections']['trade']=['central_usd'=>1000,'effective_on'=>'2026-11-28','model_assumption_bps'=>5000];
$c=MacroeconomicProjection::project($d,$trade,$f);
ck47($c['latest']['exports']<$r['exports']&&$c['days'][1]===$a['days'][1],'trade effective date and export exposure');
$trade['projections']['trade']['central_usd']=null;$m=MacroeconomicProjection::project($d,$trade,$f);
ck47(!$m['coverage_complete']&&$m['trade_reference_missing']===['trade']&&$m['latest']['exports']===$r['exports'],'missing trade reference not fabricated');
$poor=$d;$poor['days'][0]['opening_balance']=0;
$t=MacroeconomicProjection::project($poor,$e,$f);$treasury=FinanceBudget::project($f,$poor);
ck47($t['latest']['debt']===$treasury['debt']&&$t['latest']['cash']===0&&$t['latest']['new_borrowing']>0,'treasury debt reconciliation');
no47(fn()=>MacroeconomicProjection::project($d,array_replace($e,['world_id'=>'other']),$f),'cross world source rejected');
no47(fn()=>MacroeconomicProjection::project($d,array_replace($e,['day'=>'2026-11-27']),$f),'stale source rejected');
$bad=$d;unset($bad['days'][1]);$bad['days']=array_values($bad['days']);
no47(fn()=>MacroeconomicProjection::project($bad,$e,$f),'ledger gap rejected');
$pending=$trade;$pending['policies']['trade']['status']='authorized';no47(fn()=>MacroeconomicProjection::project($d,$pending,$f),'pending restriction not applied');
