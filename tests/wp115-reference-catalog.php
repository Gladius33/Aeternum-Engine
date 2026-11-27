<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/InternationalReferenceCatalog.php';
use Aeternum\WorldPolitics\InternationalReferenceCatalog;
function ck115(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS WP115: $m\n";}
$c=new InternationalReferenceCatalog();
$s=$c->summary();
ck115(count($s['countries'])===42&&$s['numeric_non_null']===6413&&$s['numeric_slots']===8228,'all initial reference editions ingested');
ck115($s['runtime_admission']===false&&$s['unresolved_baseline_fields']===924,'no implicit playable state or gap closure');
ck115((new InternationalReferenceCatalog(null,$c->hash()))->hash()===$c->hash(),'repeatable content pin');
try{new InternationalReferenceCatalog(null,str_repeat('0',64));throw new RuntimeException('wrong pin accepted');}catch(DomainException $e){ck115(str_contains($e->getMessage(),'pinned catalogue'),'wrong pin rejected');}
foreach(['FR','IL','IR','PS','XX'] as $cc){
    try{$c->country($cc);throw new RuntimeException('invalid country accepted');}catch(DomainException $e){ck115(true,'country outside WP115 scope rejected: '.$cc);}
}
ck115($c->country('CH')['officeholder_reference']['data']['government_mode']==='collective_executive_not_single_prime_minister','Swiss collective executive preserved');
ck115($c->country('JP')['officeholder_reference']['data']['head_of_state_name']===null,'source title not invented personal identity');
ck115($c->country('US')['officeholder_reference']['data']['head_of_state_name']===$c->country('US')['officeholder_reference']['data']['head_of_government_name'],'US shared executive identity');
$sa=$c->series('SA','unemployment_rate');
ck115(count($sa)===3&&count(array_filter($sa,fn($r)=>$r['value']===null))===2,'missing unemployment not converted to zero');
$de=$c->series('DE','real_gdp_growth');
ck115(count($de)===3&&count(array_filter($de,fn($r)=>$r['classification']==='projection'))===2,'forecast not promoted to observed');
ck115(count($c->series('DE','population_total'))===1,'no aggregate duplication');
foreach($s['countries'] as $cc){
    $r=$c->country($cc);
    ck115($r['runtime_admission']===false&&count($r['unresolved_fields'])===22&&$r['renewal_reference']['certifies_result']===false,'country references isolated: '.$cc);
}
