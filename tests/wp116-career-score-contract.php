<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CareerScoreContract.php';
require_once __DIR__.'/../games/world-politics/src/CampaignScore.php';
use Aeternum\WorldPolitics\{CareerScoreContract as V2,CampaignScore as V1};
function check116(bool $ok,string $why):void{if(!$ok)throw new RuntimeException('WP116: '.$why);}
function reject116(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Expected rejection');}
for($d=0;$d<=V2::HORIZON;$d++){
    foreach([-10000,-500,0,500,1000000] as $g){
        $v=V2::points($d,0,$g);$old=V1::points($d,$g);
        check116($v['score']===$old['score']&&$v['tenure_points']===$old['tenure_points'],'W=0 v1 equivalence');
    }
    foreach([0,intdiv($d,3),intdiv($d,2),$d] as $w){
        $p=V2::points($d-$w,$w,500);
        check116($p['score']===V2::points($d,0,500)['score'],'equal opportunity at equal elapsed day');
        check116($p['career_credit_points']+$p['tenure_points']===V2::timePoints($d),'marginal floor');
    }
}
check116(V2::points(365,365,0)['score']===1998,'one year waiting plus one year tenure');
check116(V2::points(0,365,0)['score']===999,'credit at accession');
check116(V2::elapsed('2028-02-28','2028-03-01')===2,'leap day');
reject116(fn()=>V2::elapsed('2027-02-29','2027-03-01'));
reject116(fn()=>V2::elapsed('2028-03-01','2028-02-28'));
reject116(fn()=>V2::points(7305,1,0));
reject116(fn()=>V2::points(PHP_INT_MAX,1,0));
reject116(fn()=>V2::points(1,-1,0));
reject116(fn()=>V2::points(1,0,-10001));
reject116(fn()=>V2::points(1,0,1000001));
reject116(fn()=>V2::points(366,0,0,365));
$s=V2::START;$a='2028-05-13';$b='2029-05-13';
$p=V2::project('opening',$s,null,$a,1,365,true,true,null);
check116($p['score']===null&&!$p['rankable'],'pre-office unranked');
$p=V2::project('opening',$s,$a,$a,1,365,true,true,0);
check116($p['score']===999&&!$p['rankable'],'accession provisional');
$p=V2::project('opening',$s,$a,$b,1,365,true,true,500);
$q=V2::project('opening',$s,$s,$b,1,0,true,true,500);
check116($p['score']===$q['score']&&$p['rankable']&&!$p['official_eligible'],'equal time, experimental only');
check116(V2::project('opening',$s,$a,$b,1,364,true,true,0)['score']===null,'participation gap');
check116(V2::project('opening',$s,$a,$b,1,365,false,true,0)['score']===null,'nonlawful accession excluded');
check116(V2::project('research',$s,$a,$b,1,365,true,true,0)['score']===null,'research excluded');
check116(V2::project('opening',$s,$a,$b,1,365,true,true,null)['score']===null,'missing economic evidence');
check116(V2::project('opening',$s,$a,$b,1,365,true,false,0)['score']===null,'unverified participation');
check116(V2::project('late_takeover',$a,$a,$b,1,0,true,true,0)['credit_days']===0,'late takeover no credit');
check116(V2::project('opening',$s,$a,$b,2,0,true,true,0,true)['credit_days']===0,'later series no renewed credit');
reject116(fn()=>V2::project('opening',$s,$a,$b,2,365,true,true,0));
reject116(fn()=>V2::project('opening',$a,$a,$b,1,0,true,true,0));
reject116(fn()=>V2::project('opening',$s,$b,$a,1,0,true,true,0));
$pins=array_fill_keys(['scenario','rules','economy','data','difficulty','participation','autonomy','population_scope','mandate_authority'],str_repeat('a',64));
$c=V2::cohort($pins,'FR',$s,7305,'opening');
check116(V2::cohort(array_reverse($pins,true),'FR',$s,7305,'opening')===$c,'canonical pin order');
foreach(array_keys($pins) as $k){$changed=$pins;$changed[$k]=str_repeat('b',64);check116(V2::cohort($changed,'FR',$s,7305,'opening')!==$c,'pin separation '.$k);}
check116(V2::cohort($pins,'DE',$s,7305,'opening')!==$c,'country isolation');
check116(V2::cohort($pins,'FR',$s,7305,'late_takeover')!==$c,'late cohort isolation');
check116(V2::cohort($pins,'FR',$s,7305,'research')!==$c,'research cohort isolation');
check116(V2::cohort($pins,'FR',$s,365,'opening')!==$c,'horizon isolation');
unset($pins['data']);reject116(fn()=>V2::cohort($pins,'FR',$s,7305,'opening'));
echo "PASS WP116: score contract, all horizon days, v1 parity, credit, eligibility and cohorts\n";
