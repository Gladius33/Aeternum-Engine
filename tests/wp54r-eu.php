<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/EuropeanWithdrawal.php';
use Aeternum\WorldPolitics\EuropeanWithdrawal as E;
function ckEU(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS EU: $m\n";}
function noEU(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid evidence accepted');}
$day='2026-11-25';$s=E::request(E::initial($day),$day);$sha=str_repeat('a',64);
ckEU(E::advance($s,'2029-01-01')['membership'],'intent alone does not exit');
$s=E::certify($s,$day,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
ckEU($s['deadline']==='2028-11-25'&&$s['membership'],'two calendar years');
$e=['new_deadline'=>'2029-03-01','unanimous_european_council'=>false,'france_agrees'=>true,'decision_sha'=>$sha];
noEU(fn()=>E::certify($s,$day,'extension',$e));
$x=E::certify($s,$day,'extension',array_replace($e,['unanimous_european_council'=>true]));
ckEU(E::advance($x,'2028-11-25')['membership']&&!E::advance($x,'2029-03-01')['membership'],'unanimous extension');
$a=['effective_on'=>'2027-04-01','parliament_consent'=>false,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha];
noEU(fn()=>E::certify($s,$day,'agreement',$a));
$y=E::certify($s,$day,'agreement',array_replace($a,['parliament_consent'=>true]));
$y=E::advance($y,'2027-04-01');
ckEU(!$y['membership']&&$y['exit_route']==='agreement'&&!$y['currency_changed']&&!$y['tariff_regime_implemented'],'agreement exit independent of currency and tariff');
$z=E::advance($s,'2028-11-25');ckEU(!$z['membership']&&$z['exit_route']==='deadline','deadline exit');
$r=E::certify($s,$day,'revocation',['national_decision_sha'=>$sha,'notice_sha'=>$sha,'unequivocal_unconditional'=>true]);
ckEU(E::advance($r,'2030-01-01')['membership'],'certified revocation');
foreach([$x,$y,$z,$r] as $v)ckEU(E::restore($v)===$v,'exact procedural replay');
$bad=$y;$bad['exit_on']='2027-03-01';noEU(fn()=>E::restore($bad));
