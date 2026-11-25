<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
require __DIR__.'/../games/world-politics/src/DomesticPolicySimulation.php';
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
use Aeternum\WorldPolitics\{PublicOpinion,DomesticPolicySimulation,DomesticPolicyAuthority,InstitutionalSimulation};
function ck36(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP36 FAIL: '.$s);echo "PASS WP36: $s\n";}
function no36(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck36(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
final class WP36Authority implements DomesticPolicyAuthority {
    public string $day='2026-11-25';
    public function allows(string $w,string $a):bool{return $w==='world-test';}
    public function enactedInstrument(string $w,string $p):?string{return str_repeat('a',64);}
    public function clockDay(string $w):string{return $this->day;}
}
function fixture36():DomesticPolicySimulation{
    return new DomesticPolicySimulation('world-test','2026-11-25','EUR',1000000,1000,900,['workers'=>400,'retired'=>300],
        ['reform'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>500,'retired'=>300],'duration_days'=>12,
        'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]]);
}
function evidence36(DomesticPolicySimulation $d,InstitutionalSimulation $i):array{return PublicOpinion::observe(
    ['state'=>$d->snapshot(),'etag'=>'"'.$d->hash().'"'],['state'=>$i->snapshot(),'etag'=>'"'.$i->hash().'"']);}
$p=new WP36Authority();$d=fixture36();$control=fixture36();
$d->propose($p,'reform','2026-11-26');$d->authorize($p,'reform');$control->propose($p,'reform','2026-11-26');
$i=new InstitutionalSimulation('world-test','FR','2026-11-25');
$a=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());$a->enableDialogue();
$b=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());$b->enableDialogue();
no36(fn()=>$a->enableDialogue(),'cannot enable twice');
$seen=[];$max=0;
for($n=1;$n<=60;$n++){
    $p->day=(new DateTimeImmutable('2026-11-25'))->modify('+'.$n.' days')->format('Y-m-d');
    $d->advance($p,$p->day);$control->advance($p,$p->day);$i->advanceTo($p->day);
    $r=$a->advance(evidence36($d,$i));$c=$b->advance(evidence36($control,$i));
    $x=$r['dialogue'];$w=$x['groups']['workers-union'];$ret=$x['groups']['retirees-association'];
    $seen[$w['phase']]=true;$max=max($max,$w['participation_index']);
    if($x['regime_changed']||$ret['collective_action']==='abstract_work_withdrawal'||$c['dialogue']['groups']['workers-union']['phase']!=='idle'
        ||$x['service_continuity_index']<800||$x['service_continuity_index']>1000||$r['regime_changed'])
        throw new RuntimeException('Daily civic invariants failed');
}
ck36(isset($seen['consultation_requested'],$seen['dialogue_open'],$seen['unresolved'],$seen['relief_observed']),'distinct phases from consultation to observed relief');
ck36($max>0,'prolonged fictional grievance leads to abstract collective action');
ck36($a->snapshot()['dialogue']['groups']['workers-union']['participation_index']===0,'recovery ends action gradually');
ck36($a->snapshot()['dialogue']['groups']['workers-union']['last_resolution']['legal_agreement']===false,'observed relief is not a legally certified agreement');
ck36($b->snapshot()['dialogue']['service_continuity_index']===1000,'mere proposal does not affect collective action');
ck36(PublicOpinion::restore($a->snapshot())->hash()===$a->hash(),'journal replay includes civic state');
$bad=$a->snapshot();$bad['dialogue']['groups']['workers-union']['meetings']=900;
no36(fn()=>PublicOpinion::restore($bad),'tampered negotiation history rejected');
$legacy=new PublicOpinion('world-test','2026-11-25',PublicOpinion::fixture());
ck36(!isset(PublicOpinion::restore($legacy->snapshot())->snapshot()['dialogue']),'old opinion worlds not upgraded silently');
