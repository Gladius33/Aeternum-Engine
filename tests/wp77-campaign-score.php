<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CampaignScore.php';
use Aeternum\WorldPolitics\{CampaignScore as S, CampaignLifecycle as C, HouseholdIncomeReference as H};
function ck77(bool $ok,string $s):void{if(!$ok)throw new RuntimeException('WP77 FAIL '.$s);echo "PASS WP77: $s\n";}
function no77(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected score rejection');}
ck77(S::points(0,0)['score']===0&&S::points(365,0)['score']===999&&S::points(366,0)['score']===1002,'Gregorian tenure floor');
ck77(S::points(365,500)['score']===1499&&S::points(365,-500)['score']===499&&S::points(1,-500)['score']===0,'income points and nonnegative floor');
no77(fn()=>S::points(7306,0));
$pin=H::pin();$c=C::initial('world-test',S::START);
$d=['etag'=>'"'.str_repeat('a',64).'"','state'=>['world_id'=>'world-test','day'=>S::START,'macro_baseline'=>['initial_unemployment_bps'=>750,'daily_social_benefits'=>1000000],'population_model'=>['opening_indicators'=>['population'=>1000000]],'days'=>[]]];
$p=['etag'=>'"'.str_repeat('b',64).'"','state'=>['world_id'=>'world-test','day'=>S::START,'household_reference'=>$pin]];
$o=['etag'=>'"'.str_repeat('c',64).'"','state'=>['world_id'=>'world-test','day'=>S::START,'campaign'=>$c,'history'=>[]]];
$r=S::report($d,$p,$o);ck77($r['score']===0&&$r['window_days']===0&&$r['rankable_in_alpha']&&!$r['official_ranking_eligible'],'opening alpha only');
for($i=1;$i<=366;$i++){
    $day=(new DateTimeImmutable(S::START))->modify('+'.$i.' days')->format('Y-m-d');
    $row=['day'=>$day,'macro'=>['price_index_scaled'=>100000000,'wage_index_scaled'=>100000000,'unemployment_bps'=>750,'social_expense_feedback'=>0],
        'population'=>['indicators'=>['population'=>1000000]]];
    $d['state']['days'][]=$row;$d['state']['day']=$p['state']['day']=$o['state']['day']=$day;
    $c=C::advance($c,$day,$i===366?C::event('world-test',$day,'end-campaign','presidential_resignation'):null);
    $o['state']['campaign']=$c;$o['state']['history'][]=['source'=>['day'=>$day]];
    if($i===1)ck77(S::report($d,$p,$o)['early_window_provisional'],'early score marked provisional');
}
$r=S::report($d,$p,$o);
ck77($r['score']===1002&&$r['terminal']&&$r['elapsed_office_days']===366&&$r['window_days']===365&&!$r['early_window_provisional'],'terminal tenure frozen and rolling window');
$bad=$o;$bad['state']['campaign']['ended_on']='2027-05-14';no77(fn()=>S::report($d,$p,$bad));
$bad=$d;array_pop($bad['state']['days']);no77(fn()=>S::report($bad,$p,$o));
$bad=$p;$bad['state']['world_id']='other-world';no77(fn()=>S::report($d,$bad,$o));
$cfg=['start_day'=>S::START,'currency'=>'EUR'];$bundle=str_repeat('d',64);
$dp=$p['state']+['candidate_reference'=>['sha'=>str_repeat('e',64)],'defense_reference'=>['sha'=>str_repeat('f',64)]];
$cohort=S::cohort($bundle,$cfg,$dp);$dp['world_id']='another-world';
ck77(S::cohort($bundle,$cfg,$dp)===$cohort,'equivalent worlds comparable');
ck77(S::cohort(str_repeat('a',64),$cfg,$dp)!==$cohort&&S::cohort($bundle,$cfg+['policy'=>1],$dp)!==$cohort,'different data/config separate leagues');
