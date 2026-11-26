<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,SovereignDebtLedger,FinancialCrisis};
function ck50(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP50 FAIL: '.$s);echo "PASS WP50: $s\n";}
function no50(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck50(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
final class A50 implements DomesticPolicyAuthority{
    public string $day='2026-11-25';public bool $allow=true;
    public function allows(string $w,string $a):bool{return $this->allow;}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();$c=SovereignDebtLedger::load($b,'2026-11-25',true);
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0],'duration_days'=>1,'concessions'=>['workers'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn($cfg)=>new DomesticPolicySimulation('world-test','2026-11-25','EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400],$catalog,$b,$cfg);
$a=new A50();$s=$make($c);$legacy=$make(SovereignDebtLedger::load($b,'2026-11-25'));
no50(fn()=>$legacy->crisisDecision($a,'moratorium'),'legacy scenario blocked');
$a->allow=false;no50(fn()=>$s->crisisDecision($a,'moratorium'),'permission enforced');$a->allow=true;
$s->crisisDecision($a,'moratorium');
ck50($s->snapshot()['days']===[],'decision does not retroactively change treasury');
no50(fn()=>$s->crisisDecision($a,'resume'),'one decision per day');
$next=function($sim,$a){$a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');return $sim->advance($a,$a->day);};
$r=$next($s,$a);$t=$r['treasury'];ck50($t['crisis']['moratorium']&&$t['interest']===0&&$t['interest_arrears']>0&&$t['debt']===$t['opening_debt'],'moratorium retains debt and accrues arrears');
ck50($t['banking']['credit_availability_bps']<10000,'bank credit stress proxy');
$s->crisisDecision($a,'request-support');$r=$next($s,$a);ck50($r['treasury']['crisis']['support_decision']==='declined'&&$r['treasury']['crisis']['support_disbursed']===0,'independent fictional support refusal');
while($a->day<'2026-12-01')$r=$next($s,$a);
ck50($r['treasury']['principal_arrears']>0&&$r['treasury']['principal_paid']===0,'matured principal not extinguished');
$s->crisisDecision($a,'resume');$r=$next($s,$a);ck50(!$r['treasury']['crisis']['moratorium'],'resume payments');
$s->crisisDecision($a,'request-restructuring');$r=$next($s,$a);$t=$r['treasury'];
ck50($t['crisis']['restructuring_decision']==='accepted'&&$t['crisis']['principal_written_off']>0,'fictional overdue creditor agreement');
foreach($s->snapshot()['days'] as $row){$t=$row['treasury'];if($t['debt']!==$t['opening_debt']+$t['new_borrowing']-$t['principal_paid']-$t['crisis']['principal_written_off']||array_sum($t['creditors'])!==$t['debt']||$t['cash']!==$t['opening_cash']+$row['revenue']+$t['new_borrowing']-$t['primary_paid']-$t['interest']-$t['principal_paid'])throw new RuntimeException('Crisis accounting mismatch');}
ck50(true,'exact stock and cash reconciliation');
$x=$make($c);$x->restore($s->snapshot(),$s->hash());ck50($x->snapshot()===$s->snapshot(),'replay decisions and daily outcomes');
$bad=$s->snapshot();$bad['crisis_decisions'][0]['action']='resume';$sha=hash('sha256',json_encode($bad,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));no50(fn()=>$make($c)->restore($bad,$sha),'invalid decision history rejected');
$t=SovereignDebtLedger::step($c,'2026-11-26',null,0,0,1000000000000,'request-support');
ck50($t['crisis']['support_decision']==='accepted'&&$t['crisis']['support_disbursed']>0&&$t['creditors']['new-support-external']===$t['crisis']['support_disbursed'],'support is a new creditor liability');
