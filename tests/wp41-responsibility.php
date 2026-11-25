<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';
require __DIR__.'/../games/world-politics/src/PublicOpinion.php';
use Aeternum\WorldPolitics\{BicameralLegislation,PublicOpinion};
function ck41(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP41 FAIL: '.$s);echo "PASS WP41: $s\n";}
function no41(callable $f,string $s):void{try{$f();}catch(DomainException|InvalidArgumentException $e){ck41(true,$s);return;}throw new RuntimeException('Expected rejection: '.$s);}
function ev41(BicameralLegislation $l,string $kind,array $data,string $id,string $bill='bill-one'):array{
    return $l->certify(['world_id'=>'world-test','country'=>'FR','day'=>$l->snapshot()['day'],'evidence_id'=>$id,'bill_id'=>$bill,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data]);
}
function setup41():BicameralLegislation{
    $l=new BicameralLegislation('world-test','2026-11-25');$l->enableResponsibility();$l->draft('bill-one','education-reform');
    ev41($l,'file',['government_project'=>true,'accelerated'=>true,'joint_opposition'=>false,'filing_certified'=>true],'file-one');
    return $l;
}
function engage41(BicameralLegislation $l,bool $approved=true,string $bill='bill-one',string $id='engage'):array{
    return ev41($l,'responsibility_engage',['at'=>$l->snapshot()['day'].'T10:00:00Z','council_deliberated'=>$approved,'version'=>'standard',
        'text_sha'=>$l->snapshot()['bills'][$bill]['texts']['standard'],'reading_delay_certified'=>true],$id,$bill);
}
$a=setup41();no41(fn()=>engage41($a,false),'prior Council deliberation required');
engage41($a);$a->advance('2026-11-26');
ck41($a->snapshot()['bills']['bill-one']['status']==='responsibility_pending','24 hours not elapsed at midnight');
$a->advance('2026-11-27');
ck41($a->snapshot()['bills']['bill-one']['next_chamber']==='senate'&&$a->snapshot()['bills']['bill-one']['status']==='navette','Assembly adoption only, Senate still required');
ck41(BicameralLegislation::restore($a->snapshot())->snapshot()===$a->snapshot(),'automatic expiry exact replay');
$a->draft('bill-two','education-reform');
ev41($a,'file',['government_project'=>true,'accelerated'=>true,'joint_opposition'=>false,'filing_certified'=>true],'file-two','bill-two');
no41(fn()=>engage41($a,true,'bill-two','engage-two'),'one distinct ordinary text per session');
$b=setup41();engage41($b);
no41(fn()=>ev41($b,'responsibility_file',['at'=>'2026-11-25T11:00:00Z','motion_id'=>'motion','signatures'=>57],'few'),'58 signatures required');
ev41($b,'responsibility_file',['at'=>'2026-11-25T11:00:00Z','motion_id'=>'motion','signatures'=>58],'motion-file');
$b->advance('2026-11-27');
no41(fn()=>ev41($b,'responsibility_vote',['at'=>'2026-11-27T10:59:59Z','motion_id'=>'motion','votes_for'=>289],'early'),'48 hours exact');
$r=ev41($b,'responsibility_vote',['at'=>'2026-11-27T11:00:00Z','motion_id'=>'motion','votes_for'=>289],'vote');
ck41($r['bill']['status']==='rejected'&&$r['responsibility']['government']==='resignation_required','289 votes censure government and reject bill');
$op=new PublicOpinion('world-test','2026-11-26',PublicOpinion::fixture());$op->enableDialogue();$op->enableCampaign();
$e=['world_id'=>'world-test','day'=>'2026-11-27','domestic_sha'=>str_repeat('a',64),'institutional_sha'=>str_repeat('b',64),
    'pressure'=>['workers'=>400,'retired'=>300],'active_policies'=>[],'production_index_bps'=>10000,'employment_index_bps'=>10000,
    'constitutional_breaches'=>0,'legislature'=>'sitting','election_status'=>null,'responsibility'=>$r['responsibility']];
$o=$op->advance($e);
ck41($o['campaign']['status']==='active'&&$op->snapshot()['campaign']['cabinet_status']==='resignation_required'&&!$o['regime_changed'],'censure not presidential defeat');
ck41($o['approval']<527&&PublicOpinion::restore($op->snapshot())->hash()===$op->hash(),'fictional opinion reaction replays');
no41(fn()=>ev41($b,'responsibility_appoint',['at'=>'2026-11-27T12:00:00Z','appointment_id'=>'appointment','prime_minister'=>'new-pm','resignation_presented'=>false],'bad-appoint'),'Article 50 resignation required');
ev41($b,'responsibility_appoint',['at'=>'2026-11-27T12:00:00Z','appointment_id'=>'appointment','prime_minister'=>'new-pm','resignation_presented'=>true],'appoint');
ck41($b->snapshot()['responsibility']['crisis']['government']==='in_office'&&$b->snapshot()['bills']['bill-one']['status']==='rejected','appointment does not revive censured bill');
ck41(BicameralLegislation::restore($b->snapshot())->snapshot()===$b->snapshot(),'censure and succession replay');
$c=setup41();engage41($c);ev41($c,'responsibility_file',['at'=>'2026-11-25T11:00:00Z','motion_id'=>'motion','signatures'=>58],'file-motion');
$c->advance('2026-11-27');$r=ev41($c,'responsibility_vote',['at'=>'2026-11-27T11:00:00Z','motion_id'=>'motion','votes_for'=>288],'vote');
ck41($r['bill']['next_chamber']==='senate'&&$r['responsibility']['government']==='in_office','288 votes do not pass censure');
$bad=$c->snapshot();$bad['responsibility']['engagement_count']=99;
no41(fn()=>BicameralLegislation::restore($bad),'tampered engagement count rejected');
