<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/FinanceBudget.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,SovereignDebtLedger,FinanceBudget};
function ck49(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP49 FAIL: '.$s);echo "PASS WP49: $s\n";}
function no49(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck49(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
final class A49 implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();$c=SovereignDebtLedger::load($b,'2026-11-25');
ck49(count($c['source_pack']['observations'])===41&&array_sum($c['opening_holders'])===$b['opening_debt'],'observed pack and synthetic creditor total');
$bad=$c;$bad['opening_holders']['legacy-other-government-unknown']++;no49(fn()=>SovereignDebtLedger::assertValid($bad,$b,'2026-11-25'),'tampered holder config denied');
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0],'duration_days'=>1,'concessions'=>['workers'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn()=>new DomesticPolicySimulation('world-test','2026-11-25','EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400],$catalog,$b,$c);
$a=new A49();$s=$make();$found=false;
for($i=0;$i<42;$i++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');$r=$s->advance($a,$a->day);$t=$r['treasury'];
    if($t['debt']!==$t['opening_debt']+$t['new_borrowing']-$t['principal_paid']||array_sum($t['creditors'])!==$t['debt']
        ||$t['cash']!==$t['opening_cash']+$r['revenue']+$t['new_borrowing']-$t['primary_paid']-$t['interest']-$t['principal_paid']
        ||$t['principal_due']!==$t['principal_paid']+$t['principal_arrears']||$t['new_borrowing']>$t['funding_capacity'])
        throw new RuntimeException('Stock flow mismatch');
    if($t['scheduled_principal']>0)$found=true;
}
ck49($found,'dated maturity and exact daily cash principal reconciliation');
$snap=$s->snapshot();$p=SovereignDebtLedger::project($snap);
ck49(count($p['months'])===3&&count($p['years'])===2&&array_sum(array_column($p['months'],'debt_change'))===$p['debt']-$p['opening_debt'],'calendar aggregation');
$f=FinanceBudget::project(FinanceBudget::initial(),$snap);
ck49($p['debt']===$f['debt']&&$f['cash']===$snap['balance'],'finance reconciliation');
$x=$make();$x->restore($snap,$s->hash());ck49($x->snapshot()===$snap,'exact replay');
$tamper=$snap;$tamper['days'][0]['treasury']['creditors']['legacy-state-nonresident']++;$sha=hash('sha256',json_encode($tamper,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
no49(fn()=>$make()->restore($tamper,$sha),'tampered daily creditor balance denied');
$stress=SovereignDebtLedger::step($c,'2026-12-01',null,0,0,10000000000000);
ck49($stress['funding_gap']>0&&$stress['principal_arrears']>0&&$stress['interest_arrears']>0&&$stress['debt']>=$c['opening_debt'],'shortfall creates arrears not debt erasure');
$next=SovereignDebtLedger::step($c,'2026-12-02',['treasury'=>$stress],0,0,10000000000000);
ck49($next['market_stress']&&$next['funding_capacity']===$c['stress_daily_base_capacity']&&$next['principal_due']===$stress['principal_arrears'],'stress repricing and no duplicate maturity');
ck49($next['primary_arrears']===$stress['primary_arrears']+10000000000000-$next['primary_paid'],'unpaid primary outlays persist');
