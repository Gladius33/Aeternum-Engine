<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
use Aeternum\WorldPolitics\DomesticPolicyAuthority;
use Aeternum\WorldPolitics\DomesticPolicySimulation;
final class WP29Principal implements DomesticPolicyAuthority {
    public bool $legal=false;
    public string $day='2026-11-25';
    public function allows(string $world,string $ability):bool {return $world==='demo';}
    public function enactedInstrument(string $world,string $policy):?string {return $this->legal ? str_repeat('a',64):null;}
    public function clockDay(string $world):string {return $this->day;}
}
function check29(bool $ok,string $name):void {
    if (!$ok) throw new RuntimeException($name);
    echo "PASS WP29A: $name\n";
}
function reject29(callable $f,string $name):void {
    try {$f();} catch (DomainException|InvalidArgumentException $e) {check29(true,$name);return;}
    throw new RuntimeException("Expected rejection: ".$name);
}
function sim29(string $world='demo',int $base=100):DomesticPolicySimulation {
    return new DomesticPolicySimulation($world,'2026-11-24','EUR',1000,$base,90,
        ['workers'=>400,'retired'=>200],[
            'reform'=>['revenue_delta'=>20,'expense_delta'=>5,'pressure_delta'=>['workers'=>200,'retired'=>-100],'duration_days'=>2],
            'unapproved'=>['revenue_delta'=>100,'expense_delta'=>0,'pressure_delta'=>['workers'=>500,'retired'=>500],'duration_days'=>10]
        ]);
}
$p=new WP29Principal();$s=sim29();
reject29(fn()=>sim29('other')->propose($p,'reform','2026-11-26'),'cross-world authority denied');
reject29(fn()=>$s->propose($p,'reform','2026-11-24'),'retroactive proposal denied');
$s->propose($p,'reform','2026-11-26');
$s->propose($p,'unapproved','2026-11-25');
reject29(fn()=>$s->authorize($p,'reform'),'no legal proof');
$p->legal=true;$s->authorize($p,'reform');
reject29(fn()=>$s->authorize($p,'reform'),'duplicate enactment denied');
reject29(fn()=>$s->advance($p,'2026-11-26'),'uncertified day denied');
$r=$s->advance($p,'2026-11-25');
check29($r['active_policies']===[] && $r['closing_balance']===1010,'pending and future laws inert');
reject29(fn()=>$s->advance($p,'2026-11-25'),'duplicate day denied');
reject29(fn()=>$s->authorize($p,'unapproved'),'late enactment denied');
$p->day='2026-11-27';reject29(fn()=>$s->advance($p,$p->day),'no skipped days');
$p->day='2026-11-26';$r=$s->advance($p,$p->day);
check29($r['closing_balance']===1035 && $r['revenue']===120 && $r['expense']===95,'integer fiscal identity');
check29($r['pressure']===['retired'=>175,'workers'=>450],'heterogeneous gradual effect');
$p->day='2026-11-27';$r=$s->advance($p,$p->day);
check29($r['closing_balance']===1060 && $r['pressure']['workers']===487,'multi-day accumulation');
$p->day='2026-11-28';$r=$s->advance($p,$p->day);
check29($r['active_policies']===[] && $r['closing_balance']===1070 && $r['pressure']['workers']===466,'sunset and gradual recovery');
$snapshot=$s->snapshot();$copy=sim29();$copy->restore($snapshot,$s->hash());
check29($copy->hash()===$s->hash(),'exact replay after restart');
reject29(fn()=>sim29('demo',101)->restore($snapshot,$s->hash()),'pinned assumptions cannot change');
$bad=$snapshot;$bad['days'][0]['closing_balance']++;
reject29(fn()=>sim29()->restore($bad,$s->hash()),'checksum mismatch');
$badSha=hash('sha256',json_encode($bad,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
$fresh=sim29();$before=$fresh->hash();
reject29(fn()=>$fresh->restore($bad,$badSha),'replay detects fabricated ledger even with recomputed checksum');
check29($fresh->hash()===$before,'failed restore is atomic in memory');
$p->day='2026-11-29';check29($copy->advance($p,$p->day)===$s->advance($p,$p->day),'deterministic continuation');
