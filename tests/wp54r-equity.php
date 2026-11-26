<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{FirmEconomy,PublicEquityLedger,DomesticPolicySimulation,DomesticPolicyAuthority,FrenchMacroBaseline,FrenchDemography,FrenchPublicServices,SovereignDebtLedger};
function ckEq(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);echo "PASS equity: $msg\n";}
$config=FirmEconomy::config();$yes=null;$no=null;
foreach($config as $id=>$r){if($r['resilience']%3===0)$no??=$id;else $yes??=$id;}
ckEq($yes!==null&&$no!==null,'independent fictional acceptance profiles');
$start='2026-11-25';$s=PublicEquityLedger::initial($start);$poor=$s;$decline=$s;$arrears=$s;$day=$start;
$q=PublicEquityLedger::quote($yes,$config);$price=$q['price_minor'];
for($i=1;$i<=31;$i++){
    $day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
    $action=$i===1?'offer-voluntary-equity:'.$yes:null;
    $s=PublicEquityLedger::advance($s,$day,$action,$config,$price,true);
    $poor=PublicEquityLedger::advance($poor,$day,$action,$config,$price-1,true);
    $arrears=PublicEquityLedger::advance($arrears,$day,$action,$config,$price,false);
    $decline=PublicEquityLedger::advance($decline,$day,$i===1?'offer-voluntary-equity:'.$no:null,$config,$price*10,true);
    if($i===30)ckEq($s['positions']===[]&&$s['paid_today_minor']===0,'offer not ownership');
}
ckEq($s['positions'][$yes]['stake_bps']===5100&&$s['paid_today_minor']===$price&&$s['financial_assets_at_cost_minor']===$price,'cash-financed compensated fictional ownership');
ckEq($poor['offers'][$yes]['status']==='unfunded'&&$arrears['positions']===[]&&$decline['offers'][$no]['status']==='declined','no unfunded or non-consensual purchase');
$day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
$s=PublicEquityLedger::advance($s,$day,null,$config,$price,true);
ckEq($s['paid_today_minor']===0&&$s['total_paid_minor']===$price,'one-time financial transaction');
final class AEquity implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return $p==='test-revenue'?str_repeat('a',64):null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();$p=FrenchDemography::load($start,$b['initial_unemployment_bps']);$c=FrenchPublicServices::load($start,$p,$b);$debt=SovereignDebtLedger::load($b,$start,true);
$catalog=['test-revenue'=>['revenue_delta'=>500000000000,'expense_delta'=>0,'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>60,'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn(bool $eq=true)=>new DomesticPolicySimulation('world-test',$start,'EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400,'retired'=>300],$catalog,$b,$debt,$p,$c,true,false,false,$config,$eq);
$a=new AEquity();$x=$make();$z=$make();
foreach([$x,$z] as $sim){$sim->propose($a,'test-revenue','2026-11-26');$sim->authorize($a,'test-revenue');}
$x->corporationDecision($a,'offer-voluntary-equity:'.$yes);
for($i=1;$i<=31;$i++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');
    $r=$x->advance($a,$a->day);$old=$z->advance($a,$a->day);
    if($r['macro']!==$old['macro']||$r['expense']!==$old['expense']||$r['treasury']['debt']!==$old['treasury']['debt'])throw new RuntimeException('Financial exchange inflated GDP/expense/debt');
    if($r['treasury']['cash_before_equity_minor']-$r['treasury']['equity_acquisition_minor']!==$r['closing_balance'])throw new RuntimeException('Cash settlement mismatch');
}
ckEq($r['public_equity']['paid_today_minor']===$price&&$r['closing_balance']===$old['closing_balance']-$price,'domestic cash and assets settle without changing primary spending');
$replay=$make();$replay->restore($x->snapshot(),$x->hash());ckEq($replay->hash()===$x->hash(),'exact equity replay');
try{$make(false)->corporationDecision(new AEquity(),'offer-voluntary-equity:'.$yes);throw new RuntimeException('Legacy accepted new command');}catch(DomainException $e){ckEq(true,'legacy model gate');}
