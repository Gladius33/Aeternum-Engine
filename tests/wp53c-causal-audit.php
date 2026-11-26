<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,FrenchDemography,SovereignDebtLedger,FrenchPublicServices,ProfessionalCorporations,IndustrialProgrammeCatalog,PublicOpinion,InstitutionalSimulation};
function ck53c(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP53c FAIL '.$s);echo "PASS WP53c: $s\n";}
final class Authority53c implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$start='2026-11-25';$b=FrenchMacroBaseline::load();$p=FrenchDemography::load($start,$b['initial_unemployment_bps']);
$c=FrenchPublicServices::load($start,$p,$b);$debt=SovereignDebtLedger::load($b,$start,true);
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>1,
    'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$make=fn()=>new DomesticPolicySimulation('world-test',$start,'EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],
    ['workers'=>400,'retired'=>300],$catalog,$b,$debt,$p,$c,true);
$a=new Authority53c();$control=$make();$parallel=$make();$exclusive=$make();
$parallel->corporationDecision($a,'establish-quarterly');$exclusive->corporationDecision($a,'establish-exclusive-quarterly');
ck53c(count(IndustrialProgrammeCatalog::companies()['companies'])===40,'40 historical identities');
ck53c(IndustrialProgrammeCatalog::packs(ProfessionalCorporations::initial($start))[3]['action']===null,'unimplemented pack cannot execute');
$opinions=[];foreach(['control','parallel','exclusive'] as $name){$o=new PublicOpinion('world-test',$start,PublicOpinion::fixture());$o->enableDialogue();$o->enableSocialPartners();$opinions[$name]=$o;}
$i=new InstitutionalSimulation('world-test','FR',$start);$last=[];
for($n=1;$n<=90;$n++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');$i->advanceTo($a->day);
    foreach(['control'=>$control,'parallel'=>$parallel,'exclusive'=>$exclusive] as $key=>$s){
        $r=$s->advance($a,$a->day);
        $o=$opinions[$key]->advance(PublicOpinion::observe(['state'=>$s->snapshot(),'etag'=>'"'.$s->hash().'"'],['state'=>$i->snapshot(),'etag'=>'"'.$i->hash().'"'],true));
        $m=$r['macro'];$treasury=$r['treasury'];
        if($m['real_gdp']!==$m['consumption']+$m['npish_consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy']
            ||$treasury['cash']!==$r['closing_balance']||$o['regime_changed'])throw new RuntimeException('Causal accounting invariant');
        $last[$key]=['dom'=>$r,'opinion'=>$o];
    }
}
$e=$last['exclusive'];$z=$last['control'];$q=$last['parallel'];
ck53c($e['dom']['pressure']['workers']>$q['dom']['pressure']['workers']&&$q['dom']['pressure']['workers']>=$z['dom']['pressure']['workers'],'differentiated social effect');
ck53c($e['dom']['macro']['investment']<$z['dom']['macro']['investment'],'employer resistance reaches actual macro investment');
ck53c($e['opinion']['approval']<$z['opinion']['approval'],'policy reaches opinion beyond database flags');
foreach(['control'=>$control,'parallel'=>$parallel,'exclusive'=>$exclusive] as $key=>$s){$v=$make();$v->restore($s->snapshot(),$s->hash());ck53c($v->hash()===$s->hash(),'domestic replay '.$key);}
$exclusive->corporationDecision($a,'request-company-nationalisation:renault');
$v=$make();$v->restore($exclusive->snapshot(),$exclusive->hash());ck53c($v->hash()===$exclusive->hash(),'pending company decision replay');
$a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');
$r=$exclusive->advance($a,$a->day);ck53c(!$r['corporations']['company_requests']['renault']['ownership_transferred'],'review is not a transfer');
try{$exclusive->corporationDecision($a,'request-nationalisation:industry');throw new RuntimeException('Branch request accepted');}catch(InvalidArgumentException $e){ck53c(true,'new branch nationalisation rejected');}
echo 'WP53c comparison '.json_encode(array_map(fn($v)=>['worker_pressure'=>$v['dom']['pressure']['workers'],'investment_minor'=>$v['dom']['macro']['investment'],'approval'=>$v['opinion']['approval']],$last),JSON_THROW_ON_ERROR)."\n";
