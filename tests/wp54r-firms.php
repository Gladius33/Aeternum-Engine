<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{FirmEconomy,DomesticPolicySimulation,DomesticPolicyAuthority,FrenchMacroBaseline};
function ckFirm(bool $ok,string $s):void{if(!$ok)throw new RuntimeException($s);echo "PASS firms: $s\n";}
$config=FirmEconomy::config();$start='2026-11-25';$s=FirmEconomy::initial($start,$config);$calm=$s;$recovery=$s;$boom=$s;
$stress=['macro'=>['real_output_index_bps'=>8000,'trade_exposure_bps'=>1000],'treasury'=>['banking'=>['credit_availability_bps'=>5000]]];
$good=['macro'=>['real_output_index_bps'=>10000],'treasury'=>['banking'=>['credit_availability_bps'=>10000]]];
$growth=$good;$growth['macro']['real_output_index_bps']=10300;
$day=$start;
for($i=1;$i<=400;$i++){
    $day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
    $s=FirmEconomy::advance($s,$day,$config,$stress);$calm=FirmEconomy::advance($calm,$day,$config,$good);
    $recovery=FirmEconomy::advance($recovery,$day,$config,$i<=70?$stress:$good);
    $boom=FirmEconomy::advance($boom,$day,$config,$growth);
    if($i===60)ckFirm($s['relocation_projects']===40&&$s['capacity_index_bps']===10000,'plans are not completed relocations');
    if($i===100)ckFirm($recovery['relocation_projects']===0&&$recovery['capacity_index_bps']===10000,'sustained relief cancels planned relocation');
}
ckFirm($s['capacity_index_bps']<10000&&$s['production_delta_bps']<0&&$s['employment_delta_bps']<0,'completed projects affect national capacity');
ckFirm($calm['capacity_index_bps']===10000&&$boom['capacity_index_bps']>10000,'stability versus investment recovery');
ckFirm(count(array_unique(array_column($s['firms'],'capacity_bps')))>1,'independent agent parameters');
final class AFirm implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();
$catalog=['stress'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>365,
    'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>-2500,'employment_delta_bps'=>-2500]];
$make=fn(?array $c)=>new DomesticPolicySimulation('world-test',$start,'EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400,'retired'=>300],$catalog,$b,null,null,null,false,false,true,$c);
$a=new AFirm();$x=$make($config);$z=$make(null);
for($i=1;$i<=260;$i++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');
    $obs=['version'=>'wp54r-trade-feedback-v1','world_id'=>'world-test','day'=>$a->day,'economic_sha'=>str_repeat('a',64),'exposure_bps'=>2000,'covered_pairs'=>2,'missing_policies'=>0];
    $r=$x->advance($a,$a->day,$obs);$old=$z->advance($a,$a->day,$obs);
    $m=$r['macro'];if($m['real_gdp']!==$m['consumption']+$m['npish_consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy'])throw new RuntimeException('GDP identity');
}
ckFirm($r['firms']['capacity_index_bps']<10000&&$r['macro']['investment']<$old['macro']['investment'],'completed firm projects transmit into macro');
ckFirm(!isset($old['firms']),'legacy model does not gain firm state');
$y=$make($config);$y->restore($x->snapshot(),$x->hash());ckFirm($y->hash()===$x->hash(),'firm history exact replay');
$bad=$x->snapshot();$bad['days'][259]['firms']['capacity_index_bps']++;
try{$make($config)->restore($bad,hash('sha256',json_encode($bad,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)));throw new RuntimeException('tamper accepted');}catch(DomainException $e){ckFirm(true,'forged capacity rejected');}
