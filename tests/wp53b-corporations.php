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
