<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{DomesticPolicySimulation as D,DomesticPolicyAuthority,PublicOpinion as O};
function horizonCheck(bool $v,string $s):void{if(!$v)throw new RuntimeException($s);echo "PASS horizon: $s\n";}
$start='2026-11-25';$make=fn()=>new D('horizon-test',$start,'EUR',1000,10,10,['workers'=>400,'retired'=>300],[]);
$dom=$make();$opinion=new O('horizon-test',$start,O::fixture());$sha=str_repeat('a',64);
$p=new class implements DomesticPolicyAuthority{
    public string $day='';
    public function allows(string $world,string $ability):bool{return $world==='horizon-test';}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{return $this->day;}
};
$date=new DateTimeImmutable($start);
for($i=1;$i<=7305;$i++){
    $p->day=$date->modify('+'.$i.' days')->format('Y-m-d');$dom->advance($p,$p->day);
    $opinion->advance(['world_id'=>'horizon-test','day'=>$p->day,'domestic_sha'=>$sha,'institutional_sha'=>$sha,'pressure'=>['workers'=>400,'retired'=>300],'active_policies'=>[],'production_index_bps'=>null,'employment_index_bps'=>null,'constitutional_breaches'=>0,'legislature'=>'sitting','election_status'=>null]);
}
horizonCheck(D::MAX_DAYS===7305&&O::MAX_DAYS===7305&&count($dom->snapshot()['days'])===7305&&count($opinion->snapshot()['history'])===7305,'domestic and opinion cores cross former ten-year limit');
$copy=$make();$copy->restore($dom->snapshot(),$dom->hash());horizonCheck($copy->hash()===$dom->hash()&&O::restore($opinion->snapshot())->hash()===$opinion->hash(),'long horizon deterministic replay');

$p->day=$date->modify('+7306 days')->format('Y-m-d');$reject=false;try{$dom->advance($p,$p->day);}catch(DomainException $e){$reject=true;}horizonCheck($reject,'day 7306 exceeds explicit bound');
