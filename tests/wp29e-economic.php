<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\{DomesticPolicyAuthority,DomesticPolicySimulation};
$p=new class implements DomesticPolicyAuthority {
    public string $day='2026-11-25';
    public function allows(string $world,string $ability):bool{return $world==='extended';}
    public function enactedInstrument(string $world,string $policy):?string{return str_repeat('a',64);}
    public function clockDay(string $world):string{return $this->day;}
};
$config=['reform'=>['revenue_delta'=>20,'expense_delta'=>5,'pressure_delta'=>['workers'=>200],'duration_days'=>2,
    'concessions'=>['workers'=>120],'concession_daily_cost'=>7,'production_delta_bps'=>-100,'employment_delta_bps'=>50]];
$make=fn()=>new DomesticPolicySimulation('extended','2026-11-24','EUR',1000,100,90,['workers'=>400],$config);
$s=$make();$s->propose($p,'reform','2026-11-25');$s->authorize($p,'reform');
$r=$s->advance($p,$p->day);
if ($r['expense']!==102 || $r['closing_balance']!==1018 || $r['pressure']['workers']!==420
    || $r['economic']!==['net_flow'=>18,'concession_cost'=>7,'production_index_bps'=>9900,'employment_index_bps'=>10050])
    throw new RuntimeException('Extended fiscal and concession identity failed');
$p->day='2026-11-26';$s->advance($p,$p->day);
$p->day='2026-11-27';$r=$s->advance($p,$p->day);
if ($r['economic']['concession_cost']!==0 || $r['economic']['production_index_bps']!==10000)
    throw new RuntimeException('Policy sunset failed');
$c=$make();$c->restore($s->snapshot(),$s->hash());
if ($c->hash()!==$s->hash()) throw new RuntimeException('Extended replay failed');
echo "PASS WP29E: concession cost, pressure, economic indices, sunset and replay\n";
