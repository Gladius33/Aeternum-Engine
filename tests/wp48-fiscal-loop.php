<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/FinanceBudget.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,MacroFiscalLoop,FinanceBudget};
function ck48(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP48 FAIL: '.$s);echo "PASS WP48: $s\n";}
function no48(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck48(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
final class A48 implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return str_repeat('a',64);}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();
ck48(count($b['source_pack']['observations'])===32&&$b['opening_debt']===359550000000000,'observations and units');
ck48($b['daily_real_gdp']===$b['daily_consumption']+$b['daily_investment']+$b['daily_government_consumption']+$b['daily_exports']-$b['daily_imports']+$b['daily_statistical_discrepancy'],'observed anchors reconcile');
$bad=$b;$bad['opening_debt']++;no48(fn()=>FrenchMacroBaseline::assertValid($bad),'altered baseline denied');
$catalog=['tax-test'=>['revenue_delta'=>10000000000,'expense_delta'=>0,'pressure_delta'=>['workers'=>0],'duration_days'=>10,
    'concessions'=>['workers'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>-1000,'employment_delta_bps'=>-1000]];
$make=fn()=>new DomesticPolicySimulation('world-test','2026-11-25','EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400],$catalog,$b);
$a=new A48();$s=$make();$control=$make();
$s->propose($a,'tax-test','2026-11-26');$s->authorize($a,'tax-test');
$a->day='2026-11-26';$first=$s->advance($a,$a->day);$base=$control->advance($a,$a->day);
ck48($first['macro']['tax_feedback']===0&&$first['macro']['social_expense_feedback']===0,'no same-day circular fiscal response');
ck48($first['macro']['real_gdp']<$base['macro']['real_gdp'],'effective policy shifts activity');
$a->day='2026-11-27';$second=$s->advance($a,$a->day);$other=$control->advance($a,$a->day);
ck48($second['macro']['tax_feedback']<$other['macro']['tax_feedback'],'lagged output reduces receipts');
ck48($second['macro']['social_expense_feedback']>=$other['macro']['social_expense_feedback'],'lagged unemployment and prices change social expense');
for($n=0;$n<20;$n++){$a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');$s->advance($a,$a->day);}
$snap=$s->snapshot();$borrow=0;
foreach($snap['days'] as $row){$t=$row['treasury'];$m=$row['macro'];
    if($row['closing_balance']!==$t['opening_cash']+$row['revenue']-$row['expense']-$t['interest']+$t['new_borrowing']||$t['debt']!==$t['opening_debt']+$t['new_borrowing'])
        throw new RuntimeException('Treasury mismatch');
    if($m['real_gdp']!==$m['consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy'])throw new RuntimeException('GDP identity');
    $borrow+=$t['new_borrowing'];
}
ck48($borrow>0,'borrowing and interest enter authoritative cash ledger');
$restored=$make();$restored->restore($snap,$s->hash());ck48($restored->snapshot()===$snap,'exact lagged replay');
$p=MacroFiscalLoop::project($snap);$f=FinanceBudget::project(FinanceBudget::initial(),$snap);
ck48($p['latest']['debt']===$f['debt']&&$f['cash']===$snap['balance'],'macro finance reconciliation');
$tamper=$snap;$tamper['days'][1]['macro']['tax_feedback']++;$sha=hash('sha256',json_encode($tamper,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
no48(fn()=>$make()->restore($tamper,$sha),'recomputed digest cannot bypass replay');
