<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,FrenchDemography,SovereignDebtLedger};
function ck51(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP51 FAIL: '.$s);echo "PASS WP51: $s\n";}
function no51(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck51(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
final class A51 implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();$c=FrenchDemography::load('2026-11-25',$b['initial_unemployment_bps']);
ck51(count($c['source_pack']['observations'])===51&&count($c['opening_cohorts'])===28,'51 dated observations and 28 cohorts');
ck51(array_sum($c['opening_cohorts'])===69082000&&$c['opening_indicators']['female']===35531000&&$c['opening_indicators']['male']===33551000,'rounded source normalized to exact sex totals');
ck51($c['annual_births']-$c['annual_deaths']===-6000&&$c['statistical_adjustment_not_physical_flow']===60000,'natural balance and nonphysical adjustment separated');
$bad=$c;$bad['opening_cohorts']['female/00-14']++;no51(fn()=>FrenchDemography::assertValid($bad,'2026-11-25',$b['initial_unemployment_bps']),'tampered config denied');
$debt=SovereignDebtLedger::load($b,'2026-11-25',true);
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0],'duration_days'=>1,'concessions'=>['workers'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn()=>new DomesticPolicySimulation('world-test','2026-11-25','EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400],$catalog,$b,$debt,$c);
$a=new A51();$s=$make();$age=0;
for($i=0;$i<400;$i++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');
    $r=$s->advance($a,$a->day);$p=$r['population'];$f=$p['flows'];$in=$p['indicators'];
    if($in['population']!==$p['opening_population']+$f['births']-$f['deaths']+$f['net_migration']
        ||array_sum($p['cohorts'])!==$in['population']||$in['under_20']+$in['age_20_64']+$in['age_65_plus']!==$in['population']
        ||min($p['cohorts'])<0)throw new RuntimeException('Population accounting');
    $age+=$p['aging_transfers'];
}
ck51($age>0,'400 day exact stock flow and simultaneous aging');
$snap=$s->snapshot();$p=FrenchDemography::project($snap);$latest=$p['latest'];$f=$latest['cumulative'];
ck51($p['indicators']['population']===69082000+$f['births']-$f['deaths']+$f['net_migration'],'cumulative balance');
ck51(count($p['years'])===2&&array_sum(array_column($p['months'],'population_change'))===$p['indicators']['population']-69082000,'calendar aggregates');
ck51($latest['lagged_output_adjustment_bps']!==0&&$latest['daily_nominal_gdp_per_capita_minor']>0,'macro feedback and per capita GDP');
$restored=$make();$restored->restore($snap,$s->hash());ck51($restored->snapshot()===$snap,'exact replay');
$t=$snap;$t['days'][0]['population']['cohorts']['female/00-14']++;$sha=hash('sha256',json_encode($t,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
no51(fn()=>$make()->restore($t,$sha),'tampered demographic day denied');
$macro=['nominal_gdp'=>$b['daily_real_gdp'],'unemployment_bps'=>$b['initial_unemployment_bps']];
$baseline=FrenchDemography::step($c,null,$macro);
$prior=['population'=>$baseline,'macro'=>['unemployment_bps'=>$b['initial_unemployment_bps']+1000]];
$stress=FrenchDemography::step($c,$prior,$macro);
ck51($stress['migration_factor_bps']===7000&&$stress['flows']['net_migration']<$baseline['flows']['net_migration'],'lagged macro migration sensitivity');
