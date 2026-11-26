<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/HouseholdHistoricalBacktest.php';
use Aeternum\WorldPolitics\{HouseholdHistoricalBacktest as B,HouseholdIncomeReference as H};
function ck83(bool $b,string $m):void{if(!$b)throw new RuntimeException('WP83 FAIL '.$m);echo "PASS WP83: $m\n";}
$p=H::pin();$r=B::report($p);
ck83($r['training_years']===[2021,2022,2023]&&$r['holdout_years']===[2024,2025]&&$r['sample_size']===5,'separate training and holdout');
ck83($r['metrics']['train']['fitted_uc_bps']['mean_absolute_error_bps']<=$r['metrics']['train']['midpoint_uc_bps']['mean_absolute_error_bps'],'training optimum includes original assumption');
ck83($r['metrics']['holdout']['fitted_uc_bps']['observations']===2&&$r['rows'][4]['observed_bps']===-70,'holdout published annual observation');
ck83($r['rows'][0]['midpoint_population_bps']>$r['rows'][0]['midpoint_uc_bps'],'actual demographic denominator matters');
ck83(B::report($p)===$r&&!$r['score_rules_changed']&&!$r['official_ranking_eligible'],'deterministic diagnostic, not certified ranking');
echo json_encode($r['metrics'],JSON_THROW_ON_ERROR).PHP_EOL;
