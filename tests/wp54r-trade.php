<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation,FrenchMacroBaseline,TradeMacroFeedback};
function ckTrade(bool $ok,string $s):void{if(!$ok)throw new RuntimeException($s);echo "PASS trade: $s\n";}
final class ATrade implements DomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return null;}
    public function clockDay(string $w):string{return $this->day;}
}
$b=FrenchMacroBaseline::load();$start='2026-11-25';
$make=fn()=>new DomesticPolicySimulation('world-test',$start,'EUR',$b['assumed_opening_cash'],$b['daily_revenue'],$b['daily_primary_expense'],['workers'=>400,'retired'=>300],[],$b,null,null,null,false,false,true);
$a=new ATrade();$x=$make();$z=$make();
for($n=1;$n<=60;$n++){
    $a->day=(new DateTimeImmutable($a->day))->modify('+1 day')->format('Y-m-d');
    $q=['from'=>'state_fr','to'=>'state_de','status'=>'effective','effective_on'=>'2026-11-26','effect_bps'=>6000];
    $p=['effective_on'=>'2026-11-26','central_usd'=>100000,'model_assumption_bps'=>6000];
    $miss=$p;$miss['central_usd']=null;$foreign=$q;$foreign['to']='state_es';
    $read=['state'=>['world_id'=>'world-test','day'=>$a->day,'policies'=>['a'=>$q,'b'=>$q,'c'=>$foreign],'projections'=>['a'=>$p,'b'=>$p,'c'=>$miss]],'etag'=>'"'.str_repeat('a',64).'"'];
    $obs=TradeMacroFeedback::observe($read,'world-test',$a->day);
    if($obs['exposure_bps']!==600||$obs['covered_pairs']!==1||$obs['missing_policies']!==1)throw new RuntimeException('overlap or missing coverage');
    $read['state']['projections']=[];$read['state']['policies']=[];
    $empty=TradeMacroFeedback::observe($read,'world-test',$a->day);
    $r=$x->advance($a,$a->day,$obs);$control=$z->advance($a,$a->day,$empty);
    $m=$r['macro'];
    if($m['real_gdp']!==$m['consumption']+$m['npish_consumption']+$m['investment']+$m['government_consumption']+$m['exports']-$m['imports']+$m['statistical_discrepancy'])throw new RuntimeException('GDP identity');
}
ckTrade($r['macro']['exports']<$control['macro']['exports']&&$r['macro']['imports']<$control['macro']['imports'],'trade volumes respond');
ckTrade($r['macro']['real_gdp']<$control['macro']['real_gdp']&&$r['macro']['investment']<$control['macro']['investment'],'trade shock reaches GDP and investment');
ckTrade($r['revenue']!==$control['revenue']&&$r['treasury']['debt']!==$control['treasury']['debt']||$r['closing_balance']!==$control['closing_balance'],'fiscal feedback');
$replay=$make();$replay->restore($x->snapshot(),$x->hash());ckTrade($replay->hash()===$x->hash(),'exact replay');

require_once __DIR__.'/../games/world-politics/src/GlobalReferenceBundle.php';
require_once __DIR__.'/../games/world-politics/src/WorldEconomicSimulation.php';
$bundle=\Aeternum\WorldPolitics\GlobalReferenceBundle::published();
$principal=new class implements \Aeternum\WorldPolitics\EconomicPolicyPrincipal {
    public function actorId():string{return 'trusted-test';}
    public function allows(string $world,string $actor,string $permission):bool{return $world==='observed-test';}
    public function legalApproval(string $world,string $state,string $action):bool{return $world==='observed-test';}
};
foreach(['state_ru'=>29,'state_de'=>824] as $peer=>$expected){
    $sim=new \Aeternum\WorldPolitics\WorldEconomicSimulation('observed-test','2026-11-25',$bundle->actors,$bundle->references);
    foreach(['first','second'] as $id){
        $sim->proposeTradeRestriction($principal,'state_fr',$peer,10000,10000,10000,'2026-11-26',$id);
        $sim->authorizeRestriction($principal,$id,'approve-'.$id);
    }
    $sim->advanceTo($principal,'2026-11-26','clock');
    $read=['state'=>$sim->snapshot(),'etag'=>$sim->hash()];
    $o=TradeMacroFeedback::observe($read,'observed-test','2026-11-26');
    ckTrade($o['exposure_bps']===$expected&&$o['covered_pairs']===1,'published turnover weighted once: '.$peer);
    $bad=$read;$bad['state']['projections']['first']['trade_feedback_basis_usd']=1;
    try{TradeMacroFeedback::observe($bad,'observed-test','2026-11-26');throw new RuntimeException('bad basis accepted');}
    catch(DomainException $e){ckTrade(true,'bad published denominator rejected');}
}
