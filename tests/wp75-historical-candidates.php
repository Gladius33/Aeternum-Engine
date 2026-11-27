<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/HistoricalCandidateReference.php';
use Aeternum\WorldPolitics\HistoricalCandidateReference as H;
function ck75(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP75 FAIL '.$s);echo "PASS WP75: $s\n";}
$p=H::pin();$r=H::report($p);
ck75(count($r['roster_compatible_profiles'])===12&&array_sum(array_column($r['reference']['candidates'],'votes'))===35132947,'all official first-round candidates and votes');
foreach($r['roster_compatible_profiles'] as $person)ck75($person['kind']==='real'&&$person['as_of']==='2022-04-10'&&count($person['programme'])===2&&$person['programme'][0]['sources']!==[],'dated real profile with two sourced partial measures');
ck75(!$r['selection_enabled']&&!$r['future_eligibility_certified']&&!$r['historical_votes_used_as_2027_support'],'no automatic future candidature');
$bad=$p;$bad['pack']['candidates'][0]['votes']++;
try{H::report($bad);throw new RuntimeException('Invalid votes accepted');}catch(DomainException $expected){}

ck75($r['curated_measure_count']===24&&$r['programme_partial_scope']==='employment_wages_retirement_2022'&&!$r['programme_content_curated'],'equal partial coverage, not complete manifesto');
$legacy=H::report(H::pin(true));ck75($legacy['curated_measure_count']===0&&$legacy['roster_compatible_profiles'][0]['programme']===[],'legacy pin remains valid');
