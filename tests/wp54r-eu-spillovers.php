<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanSpillovers.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,EuropeanSpillovers as S};
function ckES(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS EU spillovers: $m\n";}
function noES(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid evidence accepted');}
$start='2026-11-25';$sha=str_repeat('a',64);$e=E::request(E::initial($start,true,true),$start);
$e=E::certify($e,$start,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$start,'agreement',['effective_on'=>'2026-11-27','parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$budget=['effective_on'=>'2026-11-27','gross_retained_bps'=>0,'returns_retained_bps'=>0,'settlement_daily_minor'=>0,'settlement_days'=>0,'instrument_sha'=>$sha];
$x=E::certify($e,$start,'budget_terms',$budget);
$w=array_fill_keys(E::PARTNERS,0);$w['DE']=5001;$w['IT']=4999;
$alloc=['effective_on'=>'2026-11-27','export_loss_weights'=>$w,'import_loss_weights'=>$w,'instrument_sha'=>$sha];
$bad=$alloc;$bad['export_loss_weights']['DE']=5000;noES(fn()=>E::certify($x,$start,'partner_allocation',$bad));
$y=E::certify($x,$start,'partner_allocation',$alloc);
$project=fn(array $s,array $days=[])=>S::project(
    ['state'=>['world_id'=>'world-test','day'=>$s['day'],'eu_withdrawal'=>$s],'etag'=>'"'.$sha.'"'],
    ['state'=>['world_id'=>'world-test','day'=>$s['day'],'eu_trade_version'=>'wp54r-eu-trade-v2','days'=>$days],'etag'=>'"'.$sha.'"']);
$p=$project(E::advance($y,'2026-11-26'));
ckES($p['membership_scope']['current_modelled_eu_members']===27&&$p['budget']['cumulative']['remaining_eu_financing_gap_minor']===0,'no premature effects');
$day='2026-11-27';$row=['day'=>$day,'macro'=>['eu_trade'=>['status'=>'certified_scenario','real_import_loss_minor'=>101,'real_export_loss_minor'=>53,'partner_export_loss_proxy_minor'=>101,'partner_import_loss_proxy_minor'=>53]]];
$p=$project(E::advance($y,$day),[$row]);
ckES($p['membership_scope']['current_modelled_eu_members']===26&&$p['membership_scope']['mechanical_scope_removed_eur']===2970400000000&&$p['membership_scope']['remaining_eu_real_gdp_change_minor']===null,'mechanical scope loss is not economic contraction');
ckES(array_sum(array_column($p['trade']['partners'],'today_gross_export_loss_proxy_minor'))===101&&array_sum(array_column($p['trade']['partners'],'today_gross_import_loss_proxy_minor'))===53,'conservation of odd directional flows');
$u=$project(E::advance($x,$day),[$row]);
ckES($u['trade']['unallocated_export_loss_proxy_minor']===101&&$u['trade']['partners']['DE']['today_gross_export_loss_proxy_minor']===null,'missing weights stay missing');
$u=$project(E::advance($e,$day),[['day'=>$day,'macro'=>['eu_trade'=>['status'=>'unmodelled_post_exit']]]]);
ckES($u['trade']['unmodelled_post_exit_days']===1&&$u['budget']['status']==='unmodelled_post_exit'&&$u['budget']['annual_static_gap_before_settlement_minor']===null,'unmodelled not zero forecast');
$b=S::budget(E::advance($x,'2027-11-26'));
ckES($b['elapsed_modelled_days']===365&&$b['cumulative']['remaining_eu_financing_gap_minor']===950000000000&&$b['cumulative']['consolidated_net_delta_minor']===0,'exact annual gap and mirror identity');
$s=E::certify($e,$start,'budget_terms',array_replace($budget,['settlement_daily_minor'=>1000000000,'settlement_days'=>10]));
$b2=S::budget(E::advance($s,'2027-11-26'));
ckES($b2['cumulative']['settlement_paid_by_france_minor']===10000000000&&$b2['cumulative']['remaining_eu_financing_gap_minor']===940000000000&&$b2['today']['settlement_paid_by_france_minor']===0,'finite settlement offset once');
$rev=E::certify($y,$start,'revocation',['national_decision_sha'=>$sha,'notice_sha'=>$sha,'unequivocal_unconditional'=>true]);
ckES($rev['budget_terms']===null&&$rev['partner_allocation']===null&&S::budget(E::advance($rev,'2029-01-01'))['elapsed_modelled_days']===0,'revocation clears prospective terms');
ckES(E::restore(E::advance($y,$day))===E::advance($y,$day)&&!$p['monetary']['euro_collapse_inferred'],'replay and monetary independence');
