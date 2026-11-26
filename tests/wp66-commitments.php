<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/InternationalCommitments.php';
use Aeternum\WorldPolitics\InternationalCommitments as C;
function ck66(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP66 FAIL '.$s);echo "PASS WP66: $s\n";}
function no66(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected refusal');}
$c=new C('world-test','2027-05-14');$initial=$c->snapshot();
ck66($initial['classification']==='fictional_contract_fixtures_not_observed'&&$initial['budget_delta_minor']===null,'no fictional treasury booking');
$first=$c->decide('ua-civil-fixture','withhold','stop-one');
ck66($first===$c->decide('ua-civil-fixture','withhold','stop-one'),'idempotent decision');
no66(fn()=>$c->decide('ua-civil-fixture','resume','stop-one'));
$c->advance('2027-06-13');$s=$c->snapshot();
ck66($s['records']['ua-civil-fixture']['totals']['arrears_minor']===1000000000&&$s['records']['ua-civil-fixture']['totals']['released_minor']===0,'withholding creates arrears not cancellation');
$c->decide('ua-civil-fixture','resume','resume-one');$c->advance('2027-06-14');
ck66($c->snapshot()['records']['ua-civil-fixture']['totals']['arrears_minor']===0,'resume settles arrears in scenario only');
no66(fn()=>$c->decide('eu-contribution-fixture','request_termination','eu-terminate'));
$c->decide('ua-supply-fixture','request_termination','terminate-one');
ck66($c->snapshot()['records']['ua-supply-fixture']['totals']['released_minor']===0,'request not legal release');
$c->advance('2027-06-21');
$r=$c->snapshot()['records']['ua-supply-fixture']['totals'];
ck66($r['scenario_settled_minor']===2000000000&&$r['released_minor']<=4000000000,'past settlement preserved');
$c->advance('2028-05-14');$s=$c->snapshot();
ck66(C::restore($s)->snapshot()===$s,'event replay');
$bad=$s;$bad['totals']['released_minor']++;no66(fn()=>C::restore($bad));
$a=new C('world-test','2027-05-14');$b=new C('world-test','2027-05-14');
foreach([$a,$b] as $x)$x->decide('ua-supply-fixture','request_deferral','defer-one');
$a->advance('2028-05-14');
for($d=new DateTimeImmutable('2027-05-15');$d->format('Y-m-d')<='2028-05-14';$d=$d->modify('+1 day'))$b->advance($d->format('Y-m-d'));
ck66($a->snapshot()===$b->snapshot(),'chunk invariant negotiation and settlements');
ck66($s['totals']['original_minor']===$s['totals']['future_minor']+$s['totals']['arrears_minor']+$s['totals']['scenario_settled_minor']+$s['totals']['released_minor'],'stock reconciliation');
