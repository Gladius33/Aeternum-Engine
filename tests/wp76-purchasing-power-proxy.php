<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/HouseholdPurchasingPowerProxy.php';
use Aeternum\WorldPolitics\{HouseholdPurchasingPowerProxy as P, HouseholdIncomeReference as H};
function ck76(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP76 FAIL '.$s);echo "PASS WP76: $s\n";}
$pin=H::pin();$anchor=$pin['pack']['annual'][5];$base=['unemployment_bps'=>750,'daily_social_benefits'=>1000000,'population'=>1000000];
$row=['day'=>'2027-05-15','macro'=>['price_index_scaled'=>100000000,'wage_index_scaled'=>100000000,'unemployment_bps'=>750,'social_expense_feedback'=>0],
    'population'=>['indicators'=>['population'=>1000000]]];
$start=P::day($anchor,$base,$row);
foreach($start['cases'] as $c)ck76($c['real_per_population_proxy_index_bps']===10000,'normalized opening');
$x=$row;$x['macro']['price_index_scaled']=105000000;$p=P::day($anchor,$base,$x);
ck76($p['cases']['constant_nominal_residual']['real_per_population_proxy_index_bps']<10000&&$p['cases']['price_indexed_residual']['real_per_population_proxy_index_bps']>$p['cases']['constant_nominal_residual']['real_per_population_proxy_index_bps'],'inflation erosion and residual sensitivity');
$x=$row;$x['macro']['unemployment_bps']=1000;ck76(P::day($anchor,$base,$x)['cases']['constant_nominal_residual']['real_per_population_proxy_index_bps']<10000,'unemployment channel');
$x=$row;$x['macro']['wage_index_scaled']=105000000;ck76(P::day($anchor,$base,$x)['cases']['constant_nominal_residual']['real_per_population_proxy_index_bps']>10000,'wage channel');
$x=$row;$x['population']['indicators']['population']=1010000;ck76(P::day($anchor,$base,$x)['cases']['constant_nominal_residual']['real_per_population_proxy_index_bps']<10000,'population denominator');
$d=['etag'=>'"'.str_repeat('a',64).'"','state'=>['world_id'=>'world-test','day'=>'2027-05-14','macro_baseline'=>['initial_unemployment_bps'=>750,'daily_social_benefits'=>1000000],'population_model'=>['opening_indicators'=>['population'=>1000000]],'days'=>[]]];
$p=['etag'=>'"'.str_repeat('b',64).'"','state'=>['world_id'=>'world-test','day'=>'2027-05-14','household_reference'=>$pin]];
ck76(P::report($d,$p)['rolling_means_bps']===null,'no fictitious pre-investiture record');
for($i=1;$i<=366;$i++){
    $day=(new DateTimeImmutable('2027-05-14'))->modify('+'.$i.' days')->format('Y-m-d');
    $x=$row;$x['day']=$day;$d['state']['days'][]=$x;$d['state']['day']=$p['state']['day']=$day;
}
$r=P::report($d,$p);
ck76($r['window_days']===365&&$r['complete_365_day_window']&&$r['rolling_means_bps']['constant_nominal_residual']===10000,'rolling 365 actual rows');
ck76(!$r['score_eligible']&&$r['actual_real_rdb_per_uc_index_bps']===null&&!$r['actual_uc_modelled'],'proxy never accepted for ranking');
$bad=$d;$bad['state']['days'][365]['day']='2027-05-14';
try{P::report($bad,$p);throw new RuntimeException('Noncontiguous proxy history accepted');}catch(DomainException $expected){}
