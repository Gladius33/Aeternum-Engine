<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,FrenchDemography,SovereignDebtLedger,FrenchPublicServices};
function ck52(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP52 FAIL: '.$s);echo "PASS WP52: $s\n";}
function no52(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck52(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
final class A52 implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();$p=FrenchDemography::load('2026-11-25',$b['initial_unemployment_bps']);
$c=FrenchPublicServices::load('2026-11-25',$p,$b);
ck52(count($c['source_pack']['observations'])===33&&$c['full_beds']===367100&&$c['school_pupils']===11600000,'dated observed anchors');
$initial=FrenchPublicServices::plan($c,null);
ck52($initial['total_incremental_expense_minor']===0&&$initial['contributors_proxy']===30400000,'no baseline double count');
$older=$p['opening_indicators'];$older['age_65_plus']+=1000000;$older['age_20_64']-=1000000;
$prior=['population'=>['indicators'=>$older],'macro'=>['unemployment_bps'=>$b['initial_unemployment_bps']+500]];
$plan=FrenchPublicServices::plan($c,$prior);
ck52($plan['incremental_expense_minor']['health']>0&&$plan['incremental_expense_minor']['pensions']>0&&$plan['contributors_proxy']<$initial['contributors_proxy'],'aging and employment transmission');
$poor=FrenchPublicServices::settle($c,$plan,null,['primary_paid'=>50,'opening_primary_arrears'=>50],100);
ck52($poor['primary_funding_bps']===0&&$poor['pensions']['daily_paid_proxy_minor']===0&&$poor['next_day_pressure_delta']['retired']>0,'unfunded current spending and pressure');
$prev=$poor;for($n=0;$n<1000;$n++)$prev=FrenchPublicServices::settle($c,$plan,['services'=>$prev],['primary_paid'=>0,'opening_primary_arrears'=>0],100);
ck52($prev['operational_capacity_bps']<10000&&$prev['health']['unserved_bed_demand_proxy']>0&&$prev['education']['unserved_pupils_proxy']>0,'operational shortage under persistent stress');
$bad=$c;$bad['full_beds']++;no52(fn()=>FrenchPublicServices::assertValid($bad,'2026-11-25',$p,$b),'tampered config denied');
$debt=SovereignDebtLedger::load($b,'2026-11-25',true);
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>1,
    'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn()=>new DomesticPolicySimulation('world-test','2026-11-25','EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],
    ['workers'=>400,'retired'=>300],$catalog,$b,$debt,$p,$c);
$a=new A52();$s=$make();$sum=0;$seen=false;
for($n=0;$n<400;$n++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');$r=$s->advance($a,$a->day);$v=$r['services'];$sum+=$v['total_incremental_expense_minor'];
    if($v['total_incremental_expense_minor']!==0)$seen=true;
    if($r['expense']!==$b['daily_primary_expense']+$v['total_incremental_expense_minor']+$r['macro']['social_expense_feedback']
        ||$v['total_incremental_expense_minor']!==array_sum($v['incremental_expense_minor'])
        ||$v['health']['served_bed_demand_proxy']+$v['health']['unserved_bed_demand_proxy']!==$v['health']['daily_bed_demand_proxy']
        ||$v['pensions']['daily_paid_proxy_minor']+$v['pensions']['daily_unpaid_proxy_minor']!==$v['pensions']['daily_reference_need_minor'])
        throw new RuntimeException('Service accounting mismatch');
}
ck52($seen,'400 daily fiscal/service identities and nonzero demographic delta');
$snap=$s->snapshot();$view=FrenchPublicServices::project($snap);
ck52(array_sum(array_column($view['months'],'total_incremental_expense_minor'))===$sum&&count($view['years'])===2,'monthly and yearly totals');
$x=$make();$x->restore($snap,$s->hash());ck52($x->snapshot()===$snap,'exact replay');
$t=$snap;$t['days'][0]['services']['health']['operational_beds_proxy']++;$sha=hash('sha256',json_encode($t,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
no52(fn()=>$make()->restore($t,$sha),'tampered daily service state denied');
