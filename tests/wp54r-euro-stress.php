<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanMonetaryStress.php';
use Aeternum\WorldPolitics\{EuropeanWithdrawal as E,EuropeanMonetaryStress as M};
function ckMS(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS euro stress: $m\n";}
function noMS(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid stress accepted');}
$day='2026-11-25';$sha=str_repeat('a',64);$e=E::initial($day,true,true,true);
$terms=['effective_on'=>'2026-11-26','refinancing_days'=>365,'euro_fx_move_bps'=>-2000,'country_refinancing'=>[
    'DE'=>['tranche_minor'=>10000000000000,'spread_change_bps'=>200],
    'IT'=>['tranche_minor'=>10000000000000,'spread_change_bps'=>-100]],'instrument_sha'=>$sha];
noMS(fn()=>E::certify($e,$day,'monetary_stress',array_replace($terms,['euro_fx_move_bps'=>-2001])));
$e=E::certify($e,$day,'monetary_stress',$terms);
$p=M::project($e);
ckMS($p['status']==='pending_effective_date'&&$p['assumed_euro_fx_move_bps']===null&&$p['refinancing']['covered_cumulative_interest_delta_minor']===0,'no premature effect');
ckMS(M::reserveValuation(-2000)<2020&&M::reserveValuation(0)===2020&&M::reserveValuation(2000)>2020,'symmetric FX scenarios not deterministic collapse');
$p=M::project(E::advance($e,'2027-11-25'));$de=$p['refinancing']['countries']['DE'];$it=$p['refinancing']['countries']['IT'];
ckMS($p['elapsed_refinancing_days']===365&&$de['refinanced_principal_minor']===10000000000000&&$de['annualized_interest_delta_at_end_minor']===200000000000,'refinanced tranche exact annual run rate');
ckMS($de['cumulative_interest_delta_minor']>0&&$de['cumulative_interest_delta_minor']<200000000000&&$it['cumulative_interest_delta_minor']<0,'accrual only as principal refinances, both signs');
ckMS($p['refinancing']['missing_countries']===25&&$p['refinancing']['countries']['FR']['tranche_minor']===null&&$p['refinancing']['eu_total_interest_delta_minor']===null,'missing country exposures not invented');
ckMS(!$de['existing_debt_repriced']&&!$p['refinancing']['actual_booked']&&$p['exchange_rate_change_bps']===null&&!$p['euro_collapse_inferred'],'no whole-stock repricing or forecast');
$q=M::project(E::advance($e,'2027-11-26'));
ckMS($q['status']==='completed'&&$q['refinancing']['covered_interest_delta_today_minor']===0&&$q['refinancing']['covered_cumulative_interest_delta_minor']===$p['refinancing']['covered_cumulative_interest_delta_minor'],'finite sensitivity window');
$e=E::request($e,$day);$e=E::certify($e,$day,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$day,'revocation',['national_decision_sha'=>$sha,'notice_sha'=>$sha,'unequivocal_unconditional'=>true]);
$e=E::advance($e,'2026-11-26');
ckMS($e['membership']&&M::project($e)['status']==='active'&&E::restore($e)===$e,'independent stress persists after revocation and replays');
