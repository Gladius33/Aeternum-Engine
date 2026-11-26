<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CivilContinuityReport.php';
use Aeternum\WorldPolitics\{CivilContinuityReport as C, StrategicDefensePlanning as D};
function ck72(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP72 FAIL '.$s);echo "PASS WP72: $s\n";}
function reject72(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Expected invalid continuity observation');}
$d=['etag'=>'"'.str_repeat('a',64).'"','state'=>['world_id'=>'world-test','day'=>'2027-05-14','services_model'=>[],'days'=>[]]];
$p=['etag'=>'"'.str_repeat('b',64).'"','state'=>['world_id'=>'world-test','day'=>'2027-05-14','defense_reference'=>D::pin()]];
ck72(C::report($d,$p)['window']===null,'no fabricated pre-investiture history');
for($i=1;$i<=32;$i++){
    $day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$i.' days')->format('Y-m-d');
    $d['state']['days'][]=['day'=>$day,'services'=>['health'=>['daily_bed_demand_proxy'=>100,'served_bed_demand_proxy'=>80],
        'education'=>['pupils_proxy'=>200,'served_pupils_proxy'=>190],'pensions'=>['daily_reference_need_minor'=>1000000,'daily_paid_proxy_minor'=>900000],
        'primary_funding_bps'=>9500],'treasury'=>['primary_arrears'=>$i*1000]];
    $d['state']['day']=$p['state']['day']=$day;
}
$r=C::report($d,$p);
ck72($r['window']['complete_30_day_window']&&count($r['daily'])===30&&$r['window']['first_day']==='2027-05-17','bounded rolling window');
ck72($r['window']['health_coverage_bps']===8000&&$r['window']['school_coverage_bps']===9500&&$r['window']['pension_payment_coverage_bps']===9000,'demand weighted coverage');
ck72($r['window']['unserved_bed_days_proxy']===600&&$r['window']['unpaid_pension_flow_proxy_minor']===3000000&&$r['window']['closing_primary_arrears_minor']===32000,'stocks and flows distinct');
ck72($r['additional_budget_delta_minor']===null&&!$r['automatic_security_action']&&$r['actual_civil_emergency_capacity']===null,'read only no invented security capacity');
$bad=$d;$bad['state']['days'][31]['services']['health']['served_bed_demand_proxy']=101;reject72(fn()=>C::report($bad,$p));
$bad=$d;$bad['state']['days'][30]['day']='2027-05-15';reject72(fn()=>C::report($bad,$p));
$bad=$p;$bad['state']['day']='2027-06-01';reject72(fn()=>C::report($d,$bad));
