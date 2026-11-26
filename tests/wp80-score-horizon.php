<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CampaignScore.php';
use Aeternum\WorldPolitics\{CampaignScore as S,CampaignLifecycle as C,HouseholdIncomeReference as H};
function ck80(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP80 FAIL '.$s);echo "PASS WP80: $s\n";}
$start=S::START;$world='long-score-test';$c=C::initial($world,$start);
$d=['etag'=>'"'.str_repeat('a',64).'"','state'=>['world_id'=>$world,'day'=>$start,'macro_baseline'=>['initial_unemployment_bps'=>750,'daily_social_benefits'=>1000000],'population_model'=>['opening_indicators'=>['population'=>1000000]],'days'=>[]]];
$p=['etag'=>'"'.str_repeat('b',64).'"','state'=>['world_id'=>$world,'day'=>$start,'household_reference'=>H::pin()]];
$o=['etag'=>'"'.str_repeat('c',64).'"','state'=>['world_id'=>$world,'day'=>$start,'campaign'=>$c,'history'=>[]]];
$day=new DateTimeImmutable($start);$prev=null;
for($n=1;$n<=7305;$n++){
    $day=$day->modify('+1 day');$date=$day->format('Y-m-d');
    $d['state']['days'][]=['day'=>$date,'macro'=>['price_index_scaled'=>100000000,'wage_index_scaled'=>100000000,'unemployment_bps'=>750,'social_expense_feedback'=>0],'population'=>['indicators'=>['population'=>1000000]]];
    $d['state']['day']=$p['state']['day']=$o['state']['day']=$date;
    $c=C::advance($c,$date,$n===7305?C::event($world,$date,'end-horizon','presidential_resignation'):null);
    $o['state']['campaign']=$c;$o['state']['history'][]=['source'=>['day'=>$date]];
    if($n===1||$n===365||$n%365===0||$n===7305){
        $r=S::report($d,$p,$o);ck80($r['elapsed_office_days']===$n&&$r['window_days']===min($n,365)&&$r['income_proxy_gain_bps']===0,'score checkpoint '.$n);
        if($prev!==null&&$r['score']<$prev)throw new RuntimeException('Unexpected score regression with constant income');$prev=$r['score'];
    }
}
ck80($r['terminal']&&$r['score']===20000&&$r['ended_on']===$date,'twenty-year terminal score');
ck80(S::report($d,$p,$o)===$r,'repeatable long score projection');
$bad=$d;$bad['state']['days'][7304]['day']=$start;
try{S::report($bad,$p,$o);throw new RuntimeException('Broken history accepted');}catch(DomainException $expected){}
echo "PASS WP80: broken final history rejected\n";
