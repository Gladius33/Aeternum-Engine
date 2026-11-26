<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{IndustrialCapital,DomesticPolicySimulation,DomesticPolicyAuthority,FrenchMacroBaseline,FrenchDemography,FrenchPublicServices,SovereignDebtLedger};
function ckI(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "PASS industry: $msg\n";}
$start='2026-11-25';$s=IndustrialCapital::initial($start);$poor=$s;$day=$start;
for($i=1;$i<=350;$i++){
    $day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');$action=$i===1?'start-industrial-pilot':null;
    $s=IndustrialCapital::advance($s,$day,$action,100000000000,true,null);
    $poor=IndustrialCapital::advance($poor,$day,$action,0,false,null);
    if($i===60)ckI($s['total_paid_minor']>0&&$s['capacity_delta_bps']===0,'paid construction not yet productive');
}
ckI($s['mode']==='completed'&&$s['capacity_delta_bps']>0&&$s['total_paid_minor']===72000000000,'commissioned capital and total paid');
ckI($poor['total_paid_minor']===0&&$poor['capacity_delta_bps']===0,'unfunded projects stalled');
final class AIndustrial implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return $p==='test-revenue'?str_repeat('a',64):null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();$p=FrenchDemography::load($start,$b['initial_unemployment_bps']);$c=FrenchPublicServices::load($start,$p,$b);$debt=SovereignDebtLedger::load($b,$start,true);
$catalog=['test-revenue'=>['revenue_delta'=>100000000000,'expense_delta'=>0,'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>60,'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn(bool $on=true)=>new DomesticPolicySimulation('world-test',$start,'EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400,'retired'=>300],$catalog,$b,$debt,$p,$c,true,false,false,null,false,$on);
$a=new AIndustrial();$x=$make();$z=$make();
foreach([$x,$z] as $sim){$sim->propose($a,'test-revenue','2026-11-26');$sim->authorize($a,'test-revenue');}
$x->corporationDecision($a,'start-industrial-pilot');
$a->day='2026-11-26';$r=$x->advance($a,$a->day);$old=$z->advance($a,$a->day);
$paid=$r['industrial']['paid_today_minor'];$m=$r['macro'];$om=$old['macro'];
ckI($paid>0&&$r['expense']===$old['expense']+$paid&&$r['closing_balance']===$old['closing_balance']-$paid,'primary capex and cash reconcile');
ckI($m['government_consumption']===$om['government_consumption']&&$m['investment']-$om['investment']===$m['industrial_real_investment_minor']&&$m['imports']-$om['imports']===$m['industrial_real_imports_minor'],'no double-counting capital as government consumption');
ckI($r['services']===$old['services'],'ordinary service funding not diluted');
for($i=2;$i<=40;$i++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');$r=$x->advance($a,$a->day);$m=$r['macro'];
    if($m['real_gdp']!==$m['consumption']+$m['npish_consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy'])throw new RuntimeException('GDP identity');
}
$replay=$make();$replay->restore($x->snapshot(),$x->hash());ckI($replay->hash()===$x->hash(),'exact industrial replay');
try{$make(false)->corporationDecision(new AIndustrial(),'start-industrial-pilot');throw new RuntimeException('legacy accepted');}catch(DomainException $e){ckI(true,'legacy model gate');}
