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

$elections=0;$entries=0;$covered=0;$nationalEntries=0;$coverageRows=0;
foreach($s['countries'] as $cc){
    $d=$c->country($cc);$records=$d['parliamentary_election_reference']['results'];
    ck115(count($records)===count(array_unique(array_column($records,'election_code'))),'historical election IDs distinct: '.$cc);
    $present=false;
    foreach($records as $r){if($r['parties']!==[]){++$elections;$entries+=count($r['parties']);$present=true;}}
    $covered+=(int)$present;
    foreach($d['national_election_supplements'] as $r)$nationalEntries+=count($r['parties']);
    $cov=$c->coverage($cc);
    $coverageRows+=count($cov['fields']);
    ck115(count($cov['fields'])===22&&$cov['snapshot_sha']===$c->hash()&&count(array_filter($cov['fields'],fn($r)=>$r['fully_closed']||$r['authorizes_runtime']))===0,'coverage is evidence not authority: '.$cc);
    $opening=$c->openingReference($cc);
    ck115($opening['actual_opening_state']===null&&$opening['runtime_admission']===false&&$opening['scenario_start_day']==='2027-05-14','future opening not fabricated: '.$cc);
    ck115(count($opening['annual_2027_projections'])>0&&count(array_filter($opening['annual_2027_projections'],fn($r)=>$r['year']!==2027||!in_array($r['classification'],['projection','projection_unverified_excerpt'],true)))===0,'annual forecast preserved: '.$cc);
    ck115(count(array_filter($opening['latest_non_null_historical_references'],fn($r)=>$r['year']>2026||$r['value']===null||str_contains($r['classification'],'projection')))===0,'historical reference separated: '.$cc);
}
ck115($elections===114&&$entries===1077&&$covered===40&&$nationalEntries===18&&$coverageRows===924,'IPU and national evidence exact totals');
ck115($c->country('ZA')['national_election_supplements'][0]['seats_reported_sum']===400,'ZA official national seat fallback');
ck115($c->country('US')['parliamentary_election_reference']['results'][0]['parties']===[],'unpublished latest US party table not replaced by prior election');
ck115($c->country('CN')['parliamentary_election_reference']['results'][0]['parties']===[]&&$c->country('SA')['parliamentary_election_reference']['results'][0]['parties']===[],'no fabricated direct-election party table');
