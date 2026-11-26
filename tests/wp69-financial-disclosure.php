<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/InternationalFinanceDisclosure.php';
require_once __DIR__.'/../games/world-politics/src/UkraineAidReference.php';
use Aeternum\WorldPolitics\{InternationalFinanceDisclosure as D, InternationalCommitments as C, UkraineAidReference as U, CommitmentRisk as R};
function ck69b(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP69B FAIL '.$s);echo "PASS WP69B: $s\n";}
$c=new C('world-test','2027-05-14');$r=U::report();
$d=['etag'=>'"'.str_repeat('a',64).'"','state'=>['world_id'=>'world-test','day'=>'2027-05-14','macro_baseline'=>['aid_reference_v2'=>$r],'days'=>[]]];
$p=['etag'=>'"'.str_repeat('b',64).'"','state'=>['world_id'=>'world-test','day'=>'2027-05-14','commitments'=>$c->snapshot(),'commitment_risk'=>R::VERSION]];
$x=D::report($d,$p);
ck69b(count($x['historical_observations'])===8&&$x['historical_observations']['concessional_treasury_loans']['reported_minor']===8400000000,'reported loans separate');
ck69b($x['historical_observations']['afd_2024_2027_loan_ceiling']['kind']==='loan_ceiling_not_disbursement'&&$x['historical_observations']['eu_support_loan_2026_2027']['kind']==='eu_wide_programme_not_france_share','distinct exposure scopes');
ck69b($x['actual_receivables_minor']===null&&$x['actual_contingent_liabilities_minor']===null&&!$x['historical_rows_additive']&&!$x['additional_treasury_booking'],'unknown actual amounts not zero');
ck69b($x['fictional_commitment_totals']['original_minor']===24000000000&&$x['additional_budget_delta_minor']===null,'no double counting');
$bad=$p;$bad['state']['day']='2027-05-15';
try{D::report($d,$bad);throw new RuntimeException('Mixed dates accepted');}catch(DomainException $expected){}
$bad=$d;$bad['state']['macro_baseline']['aid_reference_v2']['source_pack']['observed']['civil_guide']['concessional_treasury_loans']++;
try{D::report($bad,$p);throw new RuntimeException('Tampered historical reference accepted');}catch(DomainException $expected){}
