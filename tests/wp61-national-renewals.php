<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/NationalRenewalCalendar.php';
use Aeternum\WorldPolitics\NationalRenewalCalendar as N;
function ck61(bool $ok,string $name):void {if(!$ok)throw new RuntimeException('WP61 FAIL '.$name);echo "PASS WP61: $name\n";}
function bad61(callable $f):void {try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection');}
$p=N::pack();
ck61(count($p['countries'])===43 && !isset($p['countries']['IR'],$p['countries']['IL'],$p['countries']['PS']),'43 target references');
ck61($p['countries']['CN']['designation']==='indirect'&&$p['countries']['SA']['designation']==='appointed','no false popular ballots');
ck61($p['countries']['US']['term_years']===2&&$p['countries']['MX']['term_years']===3,'legislature not executive term');
$us=N::schedule('2027-05','2047-05','US');
ck61(count($us['events'])===15&&N::usPoll(2028)==='2028-11-07'&&N::usPoll(2030)==='2030-11-05','twenty year US midterms and presidency');
$ch=N::schedule('2027-05','2047-05','CH');
$byKind=array_count_values(array_column($ch['events'],'kind'));
ck61($byKind['federal_council_renewal']===5&&$byKind['confederation_presidency_selection']===20,'Swiss collective office and annual presidency');
$all=N::schedule('2027-05','2047-05');
ck61($all===N::schedule('2027-05','2047-05')&&$all['changes_world_state']===false,'deterministic read-only recurrence');
ck61(count(array_unique(array_column($all['events'],'country')))===43,'all targets in twenty-year projection');
foreach($all['events'] as $e)ck61($e['certifies_result']===false&&($e['country']==='US'||$e['day']===null),'no fabricated exact day or result');
ck61(N::termLimit('FR',2,2)===false&&N::termLimit('FR',2,0)===true,'French consecutive limit');
ck61(N::termLimit('US',2,0)===false&&N::termLimit('US',1,0,true)===false&&N::termLimit('US',1,0)===true,'US total elections and succession');
ck61(N::termLimit('MX',1,0)===false&&N::termLimit('KR',1,1)===false&&N::termLimit('DE',5,5)===null,'single term and unimplemented rules');
bad61(fn()=>N::schedule('2027-13','2040-01'));bad61(fn()=>N::schedule('2027-05','2053-01'));bad61(fn()=>N::schedule('2028-05','2027-05'));bad61(fn()=>N::schedule('2027-05','2047-05','IR'));bad61(fn()=>N::termLimit('US',0,1));
ck61(N::report()['all_countries_playable']===false,'no premature availability');
