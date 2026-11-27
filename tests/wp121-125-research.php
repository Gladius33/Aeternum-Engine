<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/ResearchExperiment.php';
use Aeternum\WorldPolitics\{ResearchExperiment as E,ResearchModels as M};
function ck125(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
function no125(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('Expected rejection');}
$p=['kind'=>'housing-construction','years'=>3,'target_per_year'=>400000,'baseline_per_year'=>200000,'capacity_per_year'=>100000,'unit_cost_eur'=>20000,'completion_bps'=>8000];
$r=E::run(['type'=>'policy-capacity','parameters'=>$p]);
ck125($r['result']['total_completed']===240000&&$r['result']['total_outlay_eur']===6000000000,'capacity accounting');
ck125($r===E::run(['type'=>'policy-capacity','parameters'=>array_reverse($p,true)]),'deterministic manifest');
ck125(str_contains(E::csv($r),'additional_target'),'CSV export');
$h=M::hours(['workers'=>1000,'baseline_weekly_hours'=>35,'target_weekly_hours'=>28,'productivity_change_bps'=>0,'replacement_bps'=>5000]);
ck125($h['effective_volume_change_bps']===-1000,'work volume');
$i=M::income(['nominal_growth_bps'=>300,'price_growth_bps'=>200,'uc_growth_bps'=>50,'uncertainty_bps'=>100]);
ck125(count($i['rows'])===27&&$i['minimum_bps']<$i['central_bps']&&$i['central_bps']<$i['maximum_bps'],'bounded sensitivity');
$c=M::calibration();ck125($c['holdout_observations']===2&&!$c['enough_for_official_ranking'],'held-out benchmark not certification');
$coverage=M::coverage();ck125(count($coverage['rows'])===4&&$coverage['approved_programme_count']===0,'documentary gaps not unlocked');
no125(fn()=>E::run(['type'=>'policy-capacity','parameters'=>$p+['world_id'=>'world-secret']]));
no125(fn()=>E::run(['type'=>'policy-capacity','parameters'=>array_replace($p,['years'=>21])]));
no125(fn()=>E::run(['type'=>'household-backtest','parameters'=>[]],['version'=>'old']));
no125(fn()=>E::run(['type'=>'shell','parameters'=>[]]));
echo "PASS WP121-125: bounded models, historical validation, pinned research, exports and isolation\n";
