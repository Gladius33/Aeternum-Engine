<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CommitmentRisk.php';
use Aeternum\WorldPolitics\InternationalCommitments as C;
use Aeternum\WorldPolitics\CommitmentRisk as R;
function ck69(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP69 FAIL '.$s);echo "PASS WP69: $s\n";}
$a=new C('world-test','2027-05-14');$b=new C('world-test','2027-05-14');
$b->decide('ua-civil-fixture','withhold','stop-ua');$b->decide('eu-contribution-fixture','withhold','stop-eu');
foreach([$a,$b] as $x)$x->advance('2027-06-12');
ck69(R::report($b->snapshot())['open_arrears_minor']===0,'no default before maturity');
foreach([$a,$b] as $x)$x->advance('2027-07-13');
$ok=R::report($a->snapshot());$bad=R::report($b->snapshot());
ck69($ok['open_arrears_minor']===0&&$bad['open_arrears_minor']===6000000000,'distinct default counterfactual');
ck69($bad['actor_signals']['state_ua']<0&&!isset($bad['actor_signals']['eu'])&&$bad['partners']['eu']['diplomatic_signal']<0,'EU risk not assigned to member states');
ck69($bad['illustrative_open_interest_minor']===intdiv(30000000*30,365)+intdiv(150000000*30,365),'integer stress interest');
ck69($bad['budget_delta_minor']===null&&$bad['actual_penalties_minor']===null&&!$bad['automatic_sanctions_or_treaties'],'no fabricated penalties or sanctions');
$b->decide('ua-civil-fixture','resume','resume-ua');$b->advance('2027-07-14');
$after=R::report($b->snapshot());
ck69($after['actor_signals']['state_ua']===0&&$after['open_arrears_minor']===5000000000,'settled exposure removed without erasing EU arrears');
ck69(R::report(C::restore($b->snapshot())->snapshot())===$after,'risk replay');
