<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{PublicOpinion,DomesticPolicySimulation,DomesticPolicyAuthority,InstitutionalSimulation};
function ck35(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP35 FAIL: '.$s);echo "PASS WP35: $s\n";}
function no35(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck35(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
final class WP35Authority implements DomesticPolicyAuthority {
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return str_repeat('a',64);}
    public function clockDay(string $w):string{return $this->day;}
}
function fixture35():DomesticPolicySimulation {
    return new DomesticPolicySimulation('world-test','2026-11-25','EUR',1000000,1000,900,['workers'=>400,'retired'=>300],
        ['reform'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>400,'retired'=>100],'duration_days'=>2,
            'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>-1000,'employment_delta_bps'=>-1000]]);
}
function obs35(DomesticPolicySimulation $d,InstitutionalSimulation $i):array{return PublicOpinion::observe(
    ['state'=>$d->snapshot(),'etag'=>'"'.$d->hash().'"'],['state'=>$i->snapshot(),'etag'=>'"'.$i->hash().'"']);}
$p=new WP35Authority();$d=fixture35();$control=fixture35();
$d->propose($p,'reform','2026-11-26');$d->authorize($p,'reform');
$control->propose($p,'reform','2026-11-26');
$i=new InstitutionalSimulation('world-test','FR','2026-11-25');
$a=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());
$b=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());
$initial=$a->snapshot()['approval'];$p->day='2026-11-26';$d->advance($p,$p->day);$control->advance($p,$p->day);$i->advanceTo($p->day);
$r=$a->advance(obs35($d,$i));$c=$b->advance(obs35($control,$i));
ck35($c['approval']===$initial,'unapproved proposal has no effect on approval');
ck35($r['approval']<$initial&&$r['trust']<$c['trust'],'enacted adverse policy gradually reduces mood');
ck35($r['civic_dissatisfaction']>$c['civic_dissatisfaction']&&!$r['regime_changed'],'dissatisfaction never changes regime');
ck35(array_sum($r['intentions'])===10000&&$r['intentions']['undecided']>$c['intentions']['undecided'],'intentions conserve 10000 basis points');
ck35($a->snapshot()['cohort_approval']['workers']<520&&$a->snapshot()['cohort_approval']['retired']<540,'heterogeneous cohort response');
no35(fn()=>$a->advance(obs35($d,$i)),'same day cannot be applied twice');
$p->day='2026-11-27';$d->advance($p,$p->day);$i->advanceTo($p->day);$a->advance(obs35($d,$i));
$low=$a->snapshot()['approval'];
for($n=28;$n<=30;$n++){
    $p->day='2026-11-'.$n;$d->advance($p,$p->day);$i->advanceTo($p->day);$a->advance(obs35($d,$i));
}
ck35($a->snapshot()['approval']>$low,'sunset permits gradual recovery');
ck35(PublicOpinion::restore($a->snapshot())->hash()===$a->hash(),'deterministic replay');
$bad=$a->snapshot();$bad['approval']=1000;no35(fn()=>PublicOpinion::restore($bad),'tampered projection rejected');
$other=new InstitutionalSimulation('other-world','FR',$p->day);
no35(fn()=>obs35($d,$other),'cross-world source rejected');
$e=obs35($d,$i);$e['day']='2026-12-01';$e['constitutional_breaches']=1;$e['election_status']='constitutional_deadline_missed';$e['legislature']='dissolved';
$r=$a->advance($e);ck35($r['institutional_status']==='constitutional_deadline_breached'&&!$r['regime_changed'],'separate constitutional observation');
