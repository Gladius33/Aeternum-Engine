<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/HouseholdIncomeReference.php';
use Aeternum\WorldPolitics\HouseholdIncomeReference as H;
function ck74(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP74 FAIL '.$s);echo "PASS WP74: $s\n";}
$p=H::pin();$r=H::report($p);
ck74(count($r['reference']['annual'])===6&&count($r['reference']['quarterly'])===4,'dated observations');
ck74($r['reference']['annual'][5]['rdb_tenth_billion_eur']===18769&&$r['reference']['annual'][5]['real_rdb_per_uc_growth_bps']===-70,'2025 provisional reference');
foreach($r['arithmetic_benchmark'] as $b)ck74(abs($b['rounding_residual_bps'])<=15,'rounded historical identity '.$b['year']);
ck74(H::realPerUcGrowth(0,0,0)===0&&H::realPerUcGrowth(500,500,0)===0,'nominal growth offset by prices');
ck74(H::realPerUcGrowth(0,0,100)<0,'consumption units matter');
$v=array_column($r['illustrative_sensitivity'],'real_per_uc_growth_bps');
ck74($v[0]>$v[1]&&$v[1]>$v[2]&&$v[2]>$v[3]&&$v[3]>$v[4],'price sensitivity monotone');
ck74(!$r['score_eligible']&&$r['investiture_real_rdb_per_uc_minor']===null&&!$r['benchmark_is_behavioral_calibration'],'no premature scoring or fake calibration');
$bad=$p;$bad['pack']['annual'][5]['rdb_tenth_billion_eur']++;
try{H::report($bad);throw new RuntimeException('Tampered reference accepted');}catch(DomainException $expected){}
