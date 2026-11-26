<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,FrenchDemography,SovereignDebtLedger,FrenchPublicServices,PresidentialMandate,CampaignLifecycle,PublicOpinion};
function ck54r(bool $v,string $msg):void{if(!$v)throw new RuntimeException('WP54r '.$msg);echo "PASS WP54r: $msg\n";}
final class A54r implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$start='2026-11-25';$b=FrenchMacroBaseline::load();$p=FrenchDemography::load($start,$b['initial_unemployment_bps']);$c=FrenchPublicServices::load($start,$p,$b);$debt=SovereignDebtLedger::load($b,$start,true);
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>1,'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn(bool $feedback=true)=>new DomesticPolicySimulation('world-test',$start,'EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400,'retired'=>300],$catalog,$b,$debt,$p,$c,true,$feedback);
$a=new A54r();$yes=$make();$no=$make();$legacy=$make(false);
foreach([$yes,$no,$legacy] as $sim)$sim->corporationDecision($a,'establish-quarterly');
$approved=false;$target=0;
for($n=1;$n<=180;$n++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');
    $ry=$yes->advance($a,$a->day);$rn=$no->advance($a,$a->day);$rl=$legacy->advance($a,$a->day);
    if(!$approved&&($ry['corporations']['branches']['industry']['report']['status']??null)==='pending_state_review'){
        $yes->corporationDecision($a,'approve:industry');$approved=true;
    }
    $m=$ry['macro'];$f=$m['corporation_payroll'];$target=max($target,$f['target_premium_bps']);
    ck54r($m['tax_feedback']===$f['payroll_tax_feedback_minor']+$f['nonpayroll_tax_feedback_minor'],'tax decomposition day '.$n);
    if($m['real_gdp']!==$m['consumption']+$m['npish_consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy'])throw new RuntimeException('GDP identity');
}
ck54r($approved&&$target>0,'approved branch vote changes aggregate wage target');
ck54r($ry['macro']['wage_index_scaled']>$rn['macro']['wage_index_scaled'],'wage changes relative to otherwise identical control');
ck54r($ry['macro']['corporation_payroll']['gross_daily_payroll_minor']!==$rn['macro']['corporation_payroll']['gross_daily_payroll_minor'],'payroll feedback reaches ledger');
ck54r(!isset($rl['macro']['corporation_payroll']),'old model unchanged');
$x=$make();$x->restore($yes->snapshot(),$yes->hash());ck54r($x->hash()===$yes->hash(),'wage feedback exact replay');
$x=$make(false);$x->restore($legacy->snapshot(),$legacy->hash());ck54r($x->hash()===$legacy->hash(),'legacy exact replay');
foreach([250,750] as $approval){
    $v=PresidentialMandate::initial('world-test',$start,PresidentialMandate::fixture());
    while($v['day']<'2026-12-21')$v=PresidentialMandate::advance($v,(new DateTimeImmutable($v['day']))->modify('+1 day')->format('Y-m-d'),$approval);
    ck54r(($v['officeholder']===CampaignLifecycle::PLAYER)===($approval===750),'fictional election changes with aggregate approval '.$approval);
}
$o=new PublicOpinion('world-test',$start,PublicOpinion::fixture());$o->enableDialogue();$o->enableCampaign();$o->enablePresidentialMandate(PresidentialMandate::fixture(),true);
for($n=1;$n<=15;$n++){
    $day=(new DateTimeImmutable($start))->modify('+'.$n.' days')->format('Y-m-d');
    $o->advance(['world_id'=>'world-test','day'=>$day,'domestic_sha'=>str_repeat('a',64),'institutional_sha'=>str_repeat('b',64),'pressure'=>['workers'=>400,'retired'=>300],'active_policies'=>[],'production_index_bps'=>10000,'employment_index_bps'=>10000,'constitutional_breaches'=>0,'legislature'=>'sitting','election_status'=>null]);
}
ck54r(PublicOpinion::restore($o->snapshot())->hash()===$o->hash(),'dynamic election replay');
