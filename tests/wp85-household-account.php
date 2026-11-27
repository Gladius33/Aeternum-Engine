<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/HouseholdAccount.php';
use Aeternum\WorldPolitics\HouseholdAccount;
use Aeternum\WorldPolitics\HouseholdIncomeReference;
function ck85(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$ref=HouseholdAccount::reference();
ck85($ref['rdb']===18769&&array_sum($ref['components'])===18769,'2025 accounting identity');
$c=['female/00-14'=>5000000,'male/00-14'=>5000000];
$i=['population'=>68000000,'age_20_64'=>40000000];
$p=['start_day'=>'2027-05-14','opening_cohorts'=>$c,'opening_indicators'=>$i];
$b=['daily_real_gdp'=>800000000000,'daily_social_benefits'=>200000000000,'initial_unemployment_bps'=>750];
$r=['day'=>'2027-05-15','population'=>['cohorts'=>$c,'indicators'=>$i],
    'macro'=>['price_index_scaled'=>100000000,'wage_index_scaled'=>100000000,'nominal_gdp'=>800000000000,'unemployment_bps'=>750,'social_expense_feedback'=>0]];
$x=HouseholdAccount::day($b,$p,$r,0);
ck85($x['cases']['fixed_household_structure']['real_rdb_per_uc_proxy_index_bps']===10000,'opening normalization');
$y=HouseholdAccount::day($b,$p,$r,365);
ck85($y['cases']['fragmentation_sensitivity']['uc_index_bps']>10000,'fragmentation raises UC denominator');
$r['macro']['unemployment_bps']=1000;
$z=HouseholdAccount::day($b,$p,$r,1);
ck85($z['cases']['fixed_household_structure']['real_rdb_per_uc_proxy_index_bps']<10000,'unemployment lowers disposable income');
$d=['state'=>['world_id'=>'w','day'=>'2027-05-15','population_model'=>$p,'macro_baseline'=>$b,'days'=>[$r]],'etag'=>str_repeat('a',64)];
$di=['state'=>['world_id'=>'w','day'=>'2027-05-15','household_reference'=>HouseholdIncomeReference::pin()],'etag'=>str_repeat('b',64)];
$report=HouseholdAccount::report($d,$di);
ck85($report['window_days']===1&&!$report['score_eligible']&&$report['additional_budget_delta_minor']===null,'committed read-only account');
ck85(array_sum($report['latest']['cases']['fixed_household_structure']['components_reference_scaled'])===$report['latest']['cases']['fixed_household_structure']['rdb_reference_scaled'],'flow reconciliation');
$d['state']['days']=[];$d['state']['day']='2027-05-14';$di['state']['day']='2027-05-14';
ck85(HouseholdAccount::report($d,$di)['latest']===null,'no opening fiction');
$tax=$report['latest']['cases']['fixed_household_structure']['direct_tax_sensitivity'];
$baseline=$report['latest']['cases']['fixed_household_structure'];
ck85(array_column($tax,'direct_tax_rate_shift_bps')===[-100,0,100],'tax scenarios ordered');
ck85($tax[0]['rdb_reference_scaled']>$tax[1]['rdb_reference_scaled']&&$tax[1]['rdb_reference_scaled']>$tax[2]['rdb_reference_scaled'],'tax incidence direction');
ck85($tax[1]['rdb_reference_scaled']===$baseline['rdb_reference_scaled']&&$tax[1]['real_rdb_per_uc_proxy_index_bps']===$baseline['real_rdb_per_uc_proxy_index_bps'],'zero shift reconciles baseline');
ck85($tax[0]['rdb_reference_scaled']-$tax[1]['rdb_reference_scaled']===$tax[1]['rdb_reference_scaled']-$tax[2]['rdb_reference_scaled'],'symmetric tax counterfactual');
ck85($report['direct_tax_sensitivity_is_counterfactual']&&!$report['score_eligible']&&$report['additional_budget_delta_minor']===null,'no fiscal or score mutation');
$d['state']['day']='2027-05-15';$di['state']['day']='2027-05-15';
$rejected=false;try{HouseholdAccount::report($d,$di);}catch(DomainException $e){$rejected=true;}
ck85($rejected,'missing committed history rejected');
$d['state']['day']='2027-05-16';$di['state']['day']='2027-05-16';$r['day']='2027-05-16';$d['state']['days']=[$r];
$rejected=false;try{HouseholdAccount::report($d,$di);}catch(DomainException $e){$rejected=true;}
ck85($rejected,'truncated short window rejected');
$rows=[];
for($day=1;$day<=366;$day++){
    $row=$r;$row['day']=(new DateTimeImmutable('2027-05-14'))->modify('+'.$day.' days')->format('Y-m-d');$rows[]=$row;
}
$d['state']['days']=$rows;$d['state']['day']=$rows[365]['day'];$di['state']['day']=$rows[365]['day'];
$long=HouseholdAccount::report($d,$di);
ck85($long['window_days']===365&&$long['complete_365_day_window']&&$long['latest']['day']===$rows[365]['day'],'365-day moving window across leap day');
$d['state']['days']=array_slice($rows,1);
ck85(HouseholdAccount::report($d,$di)===$long,'only committed trailing window affects household report');
$d['state']['days']=array_slice($rows,2);
$rejected=false;try{HouseholdAccount::report($d,$di);}catch(DomainException $e){$rejected=true;}
ck85($rejected,'364-day history rejected after first year');
$case='fixed_household_structure';
ck85($long['rolling_direct_tax_means'][$case][1]['rolling_mean_index_bps']===$long['rolling_means_bps'][$case],'rolling central tax reconciliation');
ck85($long['rolling_direct_tax_means'][$case][0]['rolling_mean_index_bps']>$long['rolling_direct_tax_means'][$case][2]['rolling_mean_index_bps'],'rolling tax sensitivity');
ck85($long['observed_simulation_ranges'][$case]['min_bps']<=$long['rolling_means_bps'][$case]&&$long['observed_simulation_ranges'][$case]['max_bps']>=$long['rolling_means_bps'][$case]&&$long['ranges_are_not_confidence_intervals'],'range semantics');
echo "WP88/WP89 household accounting OK\n";
