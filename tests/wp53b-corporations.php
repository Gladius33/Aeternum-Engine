<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/ProfessionalCorporations.php';
use Aeternum\WorldPolitics\ProfessionalCorporations as C;
function ck(bool $ok,string $s):void{if(!$ok)throw new RuntimeException($s);echo "PASS WP53b: $s\n";}
$d='2026-11-25';$v=C::initial($d);
$step=function(?string $action=null)use(&$d,&$v){$d=(new DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d');$v=C::step($v,$d,$action,['unemployment_bps'=>800]);};
$step('establish-quarterly');ck($v['mode']==='parallel'&&$v['next_report']==='2027-01-01','parallel corporations and quarterly reporting');
$step('impose-exclusive');ck($v['legal_conflict']&&$v['mode']==='exclusive_contested','contested prohibition distinct from legal enactment');
while($d<'2027-01-01')$step();
ck($v['worker_opposition']>0&&$v['employer_resistance']>0,'independent social and business response');
ck($v['branches']['industry']['report']['status']==='pending_state_review','autonomous two-college vote');
$step('return:industry');ck($v['branches']['industry']['report']['status']==='returned','return for revote');
while($d<'2027-01-09')$step();
ck($v['branches']['industry']['report']['round']===2&&$v['branches']['industry']['report']['status']==='pending_state_review','autonomous second vote');
$step('approve:industry');ck($v['branches']['industry']['wage_index_bps']>10000,'approval applies branch wage index');
$step('request-nationalisation:industry');ck(!$v['branches']['industry']['public_ownership_request']['ownership_transferred'],'request is not confiscation');
$step('restore-pluralism');ck(!$v['legal_conflict']&&$v['union_legal_status']==='protected','reversible institutional choice');
try{C::validateEvent($v,'approve:health');throw new RuntimeException('invalid decision accepted');}catch(DomainException $e){ck(true,'invalid state transition rejected');}
$step('establish-annual');ck($v['next_report']==='2028-01-01','annual reporting');

require_once __DIR__.'/../games/world-politics/src/WageFloorRegime.php';
require_once __DIR__.'/../games/world-politics/src/CorporationWageFeedback.php';
use Aeternum\WorldPolitics\WageFloorRegime as W;
use Aeternum\WorldPolitics\CorporationWageFeedback as F;
$old=W::national('2027-05-14',null);ck($old['monthly_net_minor']===142600,'opening national floor');
$prior=['social_economy'=>['wage_floor_regime'=>['national_reference'=>$old]],'macro'=>['price_index_scaled'=>103000000,'wage_index_scaled'=>100000000]];
$next=W::national('2027-06-01',$prior);ck($next['monthly_net_minor']===146878,'inflation adjustment without policy promise');
$prior['social_economy']['wage_floor_regime']['national_reference']=$next;$prior['macro']['price_index_scaled']=99000000;
ck(W::national('2027-06-02',$prior)['monthly_net_minor']===$next['monthly_net_minor'],'national nominal floor does not fall with recession');
$branch=C::initial('2027-12-29');$ctx=['active'=>true,'opening_floor_minor'=>150000];
$branch=C::step($branch,'2027-12-30',null,['unemployment_bps'=>800],$ctx);
$branch=C::step($branch,'2027-12-31',null,['unemployment_bps'=>800],$ctx);
$branch=C::step($branch,'2028-01-01',null,['unemployment_bps'=>800,'annual_inflation_bps'=>400],$ctx);
ck($branch['branches']['industry']['minimum_net_minor']===150000&&isset($branch['branches']['industry']['report']['proposed_minimum_minor']),'vote alone does not change branch floor');
$branch=C::step($branch,'2028-01-02','approve:industry',['unemployment_bps'=>800],$ctx);
ck($branch['branches']['industry']['minimum_net_minor']!==150000&&$branch['branches']['industry']['report']['state_review_kind']==='explicit_state_review','approved minimum takes effect');
ck(W::report($old,150000,$branch)['national_legal_floor_minor']===null&&F::weightedFloor($branch)>0,'branch regime distinct from national legal minimum');
$branch=C::step($branch,'2028-01-03',null,['unemployment_bps'=>800],['active'=>false,'opening_floor_minor'=>150000]);
ck(!isset($branch['minimum_wage_regime'])&&W::report($old,150000,$branch)['national_legal_floor_minor']===150000,'repeal restores national legal floor');
