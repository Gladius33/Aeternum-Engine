<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/GlobalReferenceBundle.php';
require __DIR__.'/../games/world-politics/src/DiplomaticSimulation.php';
use Aeternum\WorldPolitics\{GlobalReferenceBundle,DiplomaticSimulation,DiplomaticPrincipal};
function ckD(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS diplomacy: $m\n";}
final class Principal54d implements DiplomaticPrincipal{
    public function __construct(private readonly bool $clock){}
    public function actorId():string{return $this->clock?'clock-test':'human-test';}
    public function allows(string $w,string $a,string $p):bool{return $w==='world-test'&&($this->clock?$a==='clock-test'&&$p==='diplomacy.clock':$a==='state_fr'&&in_array($p,['diplomacy.propose','diplomacy.respond'],true));}
}
$reg=(new GlobalReferenceBundle())->actors;$start='2026-11-25';
$x=new DiplomaticSimulation('world-test',$start,$reg);$x->enableDailyReplies();
$legacy=new DiplomaticSimulation('world-test',$start,$reg);$human=new Principal54d(false);$clock=new Principal54d(true);
foreach([$x,$legacy] as $sim){
    $sim->propose($human,'state_fr','state_de','dialogue','offer-de');
    $sim->propose($human,'state_fr','state_us','trade_cooperation','offer-us');
    $sim->propose($human,'state_fr','state_gb','treaty_proposal','offer-gb');
    $sim->propose($human,'state_fr','state_it','sanctions_proposal','offer-it');
}
try{$x->advanceTo('2026-11-26',$human);throw new RuntimeException('Human clock accepted');}catch(DomainException $e){ckD(true,'clock authority');}
try{$x->respond($human,'state_de','offer-de',true,'fake-reply');throw new RuntimeException('Foreign impersonation accepted');}catch(DomainException $e){ckD(true,'foreign actor not player controlled');}
$day=$start;$count=0;
for($i=1;$i<=7;$i++){
    $day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
    $r=$x->advanceTo($day,$clock);$legacy->advanceTo($day);
    $count+=count($r['autonomous_replies']);
    foreach($r['autonomous_replies'] as $reply)if($reply['binding_effect']!==false)throw new RuntimeException('Binding automatic reply');
    if($i===1)ckD($count===0,'no instant answer');
}
ckD($count===3,'three distinct delayed responses');
$state=$x->snapshot();
ckD($state['proposals']['offer-it']['status']==='pending_domestic_approval','sanctions require independent approval');
ckD($legacy->snapshot()['proposals']['offer-de']['status']==='pending_response','legacy worlds unchanged');
ckD(DiplomaticSimulation::fromSnapshot($state,$reg)->hash()===$x->hash(),'restore pinned state');
$day=(new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
ckD($x->advanceTo($day,$clock)['autonomous_replies']===[],'no repeated replies');
