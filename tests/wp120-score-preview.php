<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CareerScorePreview.php';
use Aeternum\WorldPolitics\{CareerScorePreview as P,CareerScoreContract as C};
function ck120(bool $b,string $m):void{if(!$b)throw new RuntimeException('WP120 '.$m);}
function reject120(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('WP120 expected rejection');}
$start=C::START;$access='2028-05-13';$end='2029-05-13';
$s=['series_id'=>'s','human_tenure_from'=>$access,'accession_on'=>$access,'through_day'=>$end,
    'status'=>'closed','tenure_days'=>365,'tenure_participation_verified'=>true,
    'verified_credit_days'=>365,'maximum_unverified_credit_days'=>365];
$seen=[];
$p=P::series($s,'opening',function($d)use(&$seen,$access,$end){ck120($d>=$access&&$d<=$end,'no predecessor or future data');$seen[]=$d;return $d===$access?12000:12600;});
ck120($p['score']===2498&&$p['career_credit_points']===999&&$p['purchasing_power_points']===500,'equal-time compensation and accession baseline');
ck120($p['terminal']&&!$p['rankable']&&!$p['official_eligible']&&count($seen)===366,'terminal preview only');
$direct=$s;$direct['human_tenure_from']=$direct['accession_on']=$start;$direct['tenure_days']=730;
$direct['verified_credit_days']=$direct['maximum_unverified_credit_days']=0;
$q=P::series($direct,'opening',fn($d)=>$d===$start?10000:10500);
ck120($q['score']===$p['score']&&$q['window_days']===365,'equal opportunity and bounded window');
$gap=$s;$gap['tenure_participation_verified']=false;
ck120(P::series($gap,'opening',fn()=>throw new RuntimeException('not read'))['score']===null,'gap excluded');
$gap=$s;$gap['verified_credit_days']=null;
ck120(P::series($gap,'opening',fn()=>10000)['score']===null,'unsealed credit excluded');
$late=$s;$late['verified_credit_days']=null;$late['maximum_unverified_credit_days']=0;
ck120(P::series($late,'late_takeover',fn()=>10000)['credit_days']===0,'late credit zero');
reject120(fn()=>P::series($s,'late_takeover',fn()=>10000));
$opening=$s;$opening['through_day']=$access;$opening['tenure_days']=0;
ck120(P::series($opening,'opening',fn()=>throw new RuntimeException('not read'))['status']==='economic_window_pending','no invented accession gain');
reject120(fn()=>P::series($s,'opening',fn()=>0));
reject120(fn()=>P::series($s,'opening',fn()=>throw new DomainException('missing')));
$negative=P::series($s,'opening',fn($d)=>$d===$access?10000:9000);
ck120($negative['purchasing_power_points']===-1000&&$negative['score']===998,'negative gain');
echo "PASS WP120 private career score preview: baseline, credit, window, gap, takeover, terminal\n";
