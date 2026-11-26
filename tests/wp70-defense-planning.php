<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/StrategicDefensePlanning.php';
use Aeternum\WorldPolitics\StrategicDefensePlanning as D;
function ck70(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP70 FAIL '.$s);echo "PASS WP70: $s\n";}
$p=D::pin();$r=D::report($p,'world-test','2027-05-14');
ck70($r['reference']['observations']['mission_defense_plf_2027_minor']===6340000000000,'2027 proposed budget');
ck70($r['budget_options'][0]['annual_planning_envelope_minor']===5706000000000&&$r['budget_options'][2]['difference_from_plf_minor']===634000000000,'symmetric budget counterfactuals');
foreach($r['budget_options'] as $o)ck70(array_sum($o['illustrative_allocation_minor'])===$o['annual_planning_envelope_minor']&&!$o['booked']&&!$o['enacted'],'conserved allocation without booking');
ck70($r['actual_2027_appropriation_minor']===null&&$r['additional_budget_delta_minor']===null&&!$r['operational_readiness_inferred'],'unknown actual amounts not fabricated');
ck70($r['national_service']['reinstatement_requires_law']&&!$r['national_service']['player_can_unilaterally_activate'],'law required');
$bad=$p;$bad['pack']['sources']['budget']['status']='enacted';
try{D::report($bad,'world-test','2027-05-14');throw new RuntimeException('Tampered reference accepted');}catch(DomainException $expected){}
